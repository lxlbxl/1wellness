<?php
/**
 * Settings Class
 * Handles system configuration and settings
 */

class Settings
{
    private $db;
    private static $instance = null;

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
     * Get a setting value by key
     */
    public function get($key, $default = null)
    {
        if ($this->db->isFileStorage()) {
            $settings = $this->loadFromFile();
            return $settings[$key]['value'] ?? $default;
        } else {
            try {
                $stmt = $this->db->prepare("SELECT value, type FROM settings WHERE key = ?");
                $stmt->execute([$key]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($result) {
                    return $this->castValue($result['value'], $result['type']);
                }

                // Fallback: Check file storage even if DB is active (Migration/Sync issue)
                $fileSettings = $this->loadFromFile();
                if (isset($fileSettings[$key])) {
                    // Optionally sync back to DB here? For now just return value
                    return $fileSettings[$key]['value'] ?? $default;
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
                // Check if exists
                $stmt = $this->db->prepare("SELECT id FROM settings WHERE key = ?");
                $stmt->execute([$key]);
                $exists = $stmt->fetch();

                $serializedValue = $this->serializeValue($value, $type);

                $result = false;
                if ($exists) {
                    $stmt = $this->db->prepare("UPDATE settings SET value = ?, type = ?, updated_at = ? WHERE key = ?");
                    $result = $stmt->execute([$serializedValue, $type, date('Y-m-d H:i:s'), $key]);
                } else {
                    $stmt = $this->db->prepare("INSERT INTO settings (key, value, type, description, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
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
                $stmt = $this->db->query("SELECT * FROM settings");
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $settings = [];
                foreach ($results as $row) {
                    $settings[$row['key']] = $this->castValue($row['value'], $row['type']);
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
        switch ($type) {
            case 'integer':
                return intval($value);
            case 'boolean':
                return (bool) $value;
            case 'json':
                return json_decode($value, true);
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
