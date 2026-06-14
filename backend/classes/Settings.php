<?php
/**
 * Settings Class
 * Handles system configuration and settings
 */

class Settings
{
    private $db;
    private static $instance = null;
    private $colMap = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * The settings table exists in two column conventions:
     * schema.sql creates setting_key/setting_value/setting_type, while older
     * deployments use key/value/type. Detect once per request — previously
     * this class hardcoded key/value, so every read returned defaults and
     * every save silently failed on schema.sql databases.
     */
    private function cols()
    {
        if ($this->colMap !== null) {
            return $this->colMap;
        }
        $this->colMap = ['k' => 'setting_key', 'v' => 'setting_value', 't' => 'setting_type'];
        try {
            $stmt = $this->db->prepare("SELECT setting_key FROM settings LIMIT 1");
            $stmt->execute();
        } catch (Exception $e) {
            $this->colMap = ['k' => 'key', 'v' => 'value', 't' => 'type'];
        }
        return $this->colMap;
    }

    /**
     * Get a setting value by key
     */
    public function get($key, $default = null)
    {
        if ($this->db->isFileStorage()) {
            $settings = $this->loadFromFile();
            return $settings[$key]['value'] ?? $default;
        } else {
            try {
                $c = $this->cols();
                $stmt = $this->db->prepare("SELECT {$c['v']} AS value, {$c['t']} AS type FROM settings WHERE {$c['k']} = ?");
                $stmt->execute([$key]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($result) {
                    return $this->castValue($result['value'], $result['type']);
                }

                // Fallback: Check file storage even if DB is active (Migration/Sync issue)
                $fileSettings = $this->loadFromFile();
                if (isset($fileSettings[$key])) {
                    return $fileSettings[$key]['value'] ?? $default;
                }

                // Fallback: env var matching key in UPPER_CASE (e.g. notify_whatsapp_enabled → NOTIFY_WHATSAPP_ENABLED)
                if (function_exists('env')) {
                    $envVal = env(strtoupper($key));
                    if ($envVal !== null && $envVal !== false && $envVal !== '') {
                        return $envVal;
                    }
                }

                return $default;
            } catch (Exception $e) {
                error_log("Error getting setting $key: " . $e->getMessage());
                return $default;
            }
        }
    }

    /**
     * Set a setting value
     */
    public function set($key, $value, $type = 'string', $description = '')
    {
        if ($this->db->isFileStorage()) {
            $settings = $this->loadFromFile();
            $settings[$key] = [
                'value' => $value,
                'type' => $type,
                'description' => $description
            ];
            $this->saveToFile($settings);
            return true;
        } else {
            try {
                $c = $this->cols();
                // Check if exists
                $stmt = $this->db->prepare("SELECT id FROM settings WHERE {$c['k']} = ?");
                $stmt->execute([$key]);
                $exists = $stmt->fetch();

                $serializedValue = $this->serializeValue($value, $type);

                $result = false;
                if ($exists) {
                    $stmt = $this->db->prepare("UPDATE settings SET {$c['v']} = ?, {$c['t']} = ?, updated_at = ? WHERE {$c['k']} = ?");
                    $result = $stmt->execute([$serializedValue, $type, date('Y-m-d H:i:s'), $key]);
                } else {
                    $stmt = $this->db->prepare("INSERT INTO settings ({$c['k']}, {$c['v']}, {$c['t']}, description, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
                    $result = $stmt->execute([$key, $serializedValue, $type, $description, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
                }

                // Sync to file storage for backup/cross-DB compatibility
                if ($result) {
                    $fileSettings = $this->loadFromFile();
                    $fileSettings[$key] = [
                        'value' => $value,
                        'type' => $type,
                        'description' => $description
                    ];
                    $this->saveToFile($fileSettings);
                }

                return $result;
            } catch (Exception $e) {
                error_log("Error setting $key: " . $e->getMessage());
                return false;
            }
        }
    }

    /**
     * Get all settings
     */
    public function getAll()
    {
        if ($this->db->isFileStorage()) {
            $settings = $this->loadFromFile();
            $flat = [];
            foreach ($settings as $key => $data) {
                if (isset($data['value'])) {
                    $flat[$key] = $this->castValue($data['value'], $data['type'] ?? 'string');
                } else {
                    $flat[$key] = $data; // Fallback for already flat entries
                }
            }
            return $flat;
        } else {
            try {
                $c = $this->cols();
                $stmt = $this->db->query("SELECT * FROM settings");
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $settings = [];
                foreach ($results as $row) {
                    $rowKey = $row[$c['k']] ?? ($row['key'] ?? null);
                    if ($rowKey === null) {
                        continue;
                    }
                    $val = $row[$c['v']] ?? ($row['value'] ?? null);
                    $valType = $row[$c['t']] ?? ($row['type'] ?? 'string');
                    if (is_array($val)) {
                        $settings[$rowKey] = $val;
                    } else {
                        $settings[$rowKey] = $this->castValue($val, $valType);
                    }
                }
                return $settings;
            } catch (Exception $e) {
                error_log("Error getting all settings: " . $e->getMessage());
                return [];
            }
        }
    }

    private function castValue($value, $type)
    {
        // Defensive: if value is already an array, return as-is
        if (is_array($value)) return $value;

        switch ($type) {
            case 'integer':
                return intval($value);
            case 'boolean':
                return (bool) $value;
            case 'json':
                if (!is_string($value)) return $value;
                $decoded = json_decode($value, true);
                return is_array($decoded) ? $decoded : ($value ?: []);
            default:
                return $value;
        }
    }

    private function serializeValue($value, $type)
    {
        switch ($type) {
            case 'json':
                return json_encode($value);
            case 'boolean':
                return $value ? '1' : '0';
            default:
                return (string) $value;
        }
    }

    private function loadFromFile()
    {
        $file = dirname(DB_PATH) . '/settings.json';
        if (file_exists($file)) {
            return json_decode(file_get_contents($file), true) ?: [];
        }
        return [];
    }

    private function saveToFile($data)
    {
        $dir = dirname(DB_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($dir . '/settings.json', json_encode($data, JSON_PRETTY_PRINT));
    }
}
