<?php
/**
 * SQL Runner - Web-based SQL execution tool
 * Upload to your server and access via browser
 * DELETE THIS FILE AFTER USE FOR SECURITY
 */

// Security: Basic auth protection
$AUTH_PASSWORD = '1w_sql_2026'; // Change this!

session_start();

// Check authentication
if (!isset($_SESSION['sql_auth']) || $_SESSION['sql_auth'] !== true) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if ($_POST['password'] === $AUTH_PASSWORD) {
            $_SESSION['sql_auth'] = true;
        } else {
            $auth_error = 'Invalid password';
        }
    }

    if (!isset($_SESSION['sql_auth']) || $_SESSION['sql_auth'] !== true) {
        ?>
        <!DOCTYPE html>
        <html>

        <head>
            <title>SQL Runner - Authentication</title>
            <style>
                body {
                    font-family: 'Segoe UI', sans-serif;
                    background: #1a1a2e;
                    color: #eee;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    margin: 0;
                }

                .login-box {
                    background: #16213e;
                    padding: 40px;
                    border-radius: 12px;
                    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
                }

                h2 {
                    margin: 0 0 20px;
                    color: #e94560;
                }

                input {
                    width: 100%;
                    padding: 12px;
                    margin: 10px 0;
                    border: none;
                    border-radius: 6px;
                    background: #0f3460;
                    color: #fff;
                }

                button {
                    width: 100%;
                    padding: 12px;
                    background: #e94560;
                    border: none;
                    border-radius: 6px;
                    color: white;
                    font-weight: bold;
                    cursor: pointer;
                }

                button:hover {
                    background: #c73e54;
                }

                .error {
                    color: #ff6b6b;
                    font-size: 14px;
                }
            </style>
        </head>

        <body>
            <div class="login-box">
                <h2>🔐 SQL Runner</h2>
                <?php if (isset($auth_error)): ?>
                    <p class="error">
                        <?php echo $auth_error; ?>
                    </p>
                <?php endif; ?>
                <form method="POST">
                    <input type="password" name="password" placeholder="Enter password" required autofocus>
                    <button type="submit">Access</button>
                </form>
            </div>
        </body>

        </html>
        <?php
        exit;
    }
}

// Database connection
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();
$results = [];
$error = '';
$success = '';

// Handle SQL execution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sql'])) {
    $sql = trim($_POST['sql']);

    if (!empty($sql)) {
        try {
            $conn = $db->getConnection();

            // Split by semicolons for multiple statements
            $statements = array_filter(array_map('trim', explode(';', $sql)));

            foreach ($statements as $stmt) {
                if (empty($stmt))
                    continue;

                $isSelect = stripos(trim($stmt), 'SELECT') === 0 ||
                    stripos(trim($stmt), 'SHOW') === 0 ||
                    stripos(trim($stmt), 'DESCRIBE') === 0;

                if ($isSelect) {
                    $result = $conn->query($stmt);
                    $rows = $result->fetchAll(PDO::FETCH_ASSOC);
                    $results[] = [
                        'sql' => $stmt,
                        'type' => 'select',
                        'data' => $rows,
                        'count' => count($rows)
                    ];
                } else {
                    $affected = $conn->exec($stmt);
                    $results[] = [
                        'sql' => $stmt,
                        'type' => 'exec',
                        'affected' => $affected
                    ];
                }
            }

            $success = count($statements) . ' statement(s) executed successfully';

        } catch (PDOException $e) {
            $error = $e->getMessage();
        }
    }
}

// Pre-built queries
$prebuiltQueries = [
    'Add missing columns' => "-- Add missing columns to tables
ALTER TABLE users ADD COLUMN IF NOT EXISTS name VARCHAR(255);
ALTER TABLE sales ADD COLUMN IF NOT EXISTS product_name VARCHAR(255);
ALTER TABLE assessments ADD COLUMN IF NOT EXISTS score DECIMAL(5,2);",

    'Show all tables' => "SHOW TABLES;",

    'Describe users' => "DESCRIBE users;",

    'Describe assessments' => "DESCRIBE assessments;",

    'Describe sales' => "DESCRIBE sales;",

    'Recent assessments' => "SELECT id, name, email, assessment_type, score, status, created_at 
FROM assessments 
ORDER BY created_at DESC 
LIMIT 10;",

    'Recent sales' => "SELECT id, name, email, product_name, amount, payment_status, created_at 
FROM sales 
ORDER BY created_at DESC 
LIMIT 10;",

    'Recent users' => "SELECT id, name, first_name, email, type, condition_type, created_at 
FROM users 
ORDER BY created_at DESC 
LIMIT 10;",

    'Update name from first_name' => "UPDATE users SET name = first_name WHERE name IS NULL OR name = '';",
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SQL Runner - 1wellness</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: #eee;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        h1 {
            color: #e94560;
            margin: 0 0 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        h1 span {
            font-size: 28px;
        }

        .subtitle {
            color: #888;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .warning {
            background: #e94560;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .grid {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 20px;
        }

        .sidebar {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 15px;
        }

        .sidebar h3 {
            margin: 0 0 15px;
            font-size: 14px;
            color: #888;
            text-transform: uppercase;
        }

        .sidebar button {
            width: 100%;
            padding: 10px;
            margin-bottom: 8px;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            border-radius: 6px;
            color: #fff;
            text-align: left;
            cursor: pointer;
            font-size: 13px;
            transition: background 0.2s;
        }

        .sidebar button:hover {
            background: rgba(233, 69, 96, 0.3);
        }

        .main {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        textarea {
            width: 100%;
            height: 180px;
            padding: 15px;
            border: 2px solid #0f3460;
            border-radius: 12px;
            background: #0f3460;
            color: #fff;
            font-family: 'Consolas', monospace;
            font-size: 14px;
            resize: vertical;
        }

        textarea:focus {
            outline: none;
            border-color: #e94560;
        }

        .actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.1s;
        }

        .btn:active {
            transform: scale(0.98);
        }

        .btn-primary {
            background: #e94560;
            color: white;
        }

        .btn-primary:hover {
            background: #c73e54;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .message {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .message.success {
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid #4caf50;
        }

        .message.error {
            background: rgba(233, 69, 96, 0.2);
            border: 1px solid #e94560;
        }

        .result {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .result-header {
            font-size: 12px;
            color: #888;
            margin-bottom: 10px;
            font-family: monospace;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th,
        td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        th {
            background: rgba(233, 69, 96, 0.2);
            color: #e94560;
            font-weight: 600;
        }

        tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .empty {
            color: #666;
            font-style: italic;
        }

        .affected {
            color: #4caf50;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .sidebar {
                order: 2;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <h1><span>⚡</span> SQL Runner</h1>
        <p class="subtitle">Database:
            <?php echo defined('DB_TYPE') ? DB_TYPE : 'Unknown'; ?> | Connected: ✓
        </p>

        <div class="warning">
            ⚠️ <strong>Security Warning:</strong> Delete this file immediately after use!
            <a href="?logout=1" style="color: #fff; margin-left: 20px;">Logout</a>
        </div>

        <?php if (isset($_GET['logout'])):
            $_SESSION['sql_auth'] = false;
            header('Location: sql_runner.php');
            exit; endif; ?>

        <?php if ($success): ?>
            <div class="message success">✓
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error">✗
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="grid">
            <div class="sidebar">
                <h3>Quick Queries</h3>
                <?php foreach ($prebuiltQueries as $name => $query): ?>
                    <button type="button" onclick="setQuery(<?php echo htmlspecialchars(json_encode($query)); ?>)">
                        <?php echo htmlspecialchars($name); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="main">
                <form method="POST">
                    <textarea name="sql" id="sqlInput"
                        placeholder="Enter SQL query..."><?php echo htmlspecialchars($_POST['sql'] ?? ''); ?></textarea>
                    <div class="actions">
                        <button type="submit" class="btn btn-primary">▶ Execute</button>
                        <button type="button" class="btn btn-secondary"
                            onclick="document.getElementById('sqlInput').value = '';">Clear</button>
                    </div>
                </form>

                <?php if (!empty($results)): ?>
                    <div class="results">
                        <?php foreach ($results as $result): ?>
                            <div class="result">
                                <div class="result-header">
                                    <?php echo htmlspecialchars(substr($result['sql'], 0, 100)); ?>
                                    <?php echo strlen($result['sql']) > 100 ? '...' : ''; ?>
                                </div>

                                <?php if ($result['type'] === 'select'): ?>
                                    <?php if (empty($result['data'])): ?>
                                        <p class="empty">No results found</p>
                                    <?php else: ?>
                                        <table>
                                            <thead>
                                                <tr>
                                                    <?php foreach (array_keys($result['data'][0]) as $col): ?>
                                                        <th>
                                                            <?php echo htmlspecialchars($col); ?>
                                                        </th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($result['data'] as $row): ?>
                                                    <tr>
                                                        <?php foreach ($row as $val): ?>
                                                            <td>
                                                                <?php echo htmlspecialchars($val ?? 'NULL'); ?>
                                                            </td>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        <p style="font-size: 12px; color: #888; margin-top: 10px;">
                                            <?php echo $result['count']; ?> row(s)
                                        </p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="affected">
                                        <?php echo $result['affected']; ?> row(s) affected
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function setQuery(sql) {
            document.getElementById('sqlInput').value = sql;
        }
    </script>
</body>

</html>