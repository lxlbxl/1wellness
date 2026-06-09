<?php
require_once __DIR__ . '/../config/config.php';

// Session is already started in config.php if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function log_debug($message)
{
    $logFile = __DIR__ . '/../debug_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] [LOGIN] $message\n", FILE_APPEND);
}

// Function to get database connection based on DB_TYPE
function getDBConnection()
{
    if (defined('DB_TYPE') && DB_TYPE === 'pgsql') {
        $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }
    if (defined('DB_TYPE') && DB_TYPE === 'mysql') {
        // MySQL connection
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } else {
        // SQLite connection
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }
}

// Function to get admin by username
function getAdminByUsername($pdo, $username)
{
    $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE username = ?');
    $stmt->execute([$username]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_POST) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    log_debug("Login attempt for username: $username");

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
        log_debug("Failed: Empty username or password");
    } else {
        try {
            $pdo = getDBConnection();
            $user = getAdminByUsername($pdo, $username);

            if ($user) {
                $hash = $user['password_hash'] ?? ($user['password'] ?? null);
                if ($hash && password_verify($password, $hash)) {
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_username'] = $user['username'];
                    // Regenerate ID for security
                    session_regenerate_id(true);

                    log_debug("Success: Admin logged in - " . $user['username']);
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = 'Invalid username or password';
                    log_debug("Failed: Invalid password for user $username");
                }
            } else {
                $error = 'Invalid username or password';
                log_debug("Failed: User not found for username $username");
            }
        } catch (Exception $e) {
            $error = 'Database connection error: ' . $e->getMessage();
            log_debug("Error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - 1wellness</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .luxury-input {
            transition: all 0.2s ease;
        }

        .luxury-input:focus {
            box-shadow: 0 0 0 4px rgba(44, 62, 53, 0.1);
        }
    </style>
</head>

<body class="bg-[#FDFCF8] min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md">
        <!-- Brand -->
        <div class="text-center mb-12">
            <div
                class="w-16 h-16 bg-[#2C3E35] rounded-2xl flex items-center justify-center text-white text-3xl font-serif mx-auto mb-4 shadow-xl shadow-[#2C3E35]/20">
                L
            </div>
            <h1 class="text-3xl font-serif text-[#2C3E35]">1wellness Admin</h1>
            <p class="text-[#6B7C70] mt-2 text-sm uppercase tracking-widest font-medium">Control Center</p>
        </div>

        <!-- Login Card -->
        <div
            class="bg-white rounded-3xl shadow-xl shadow-[#2C3E35]/5 border border-[#EAEAE5] overflow-hidden p-8 md:p-10">

            <h2 class="text-xl font-serif text-[#2C3E35] mb-8 text-center">Sign in to your dashboard</h2>

            <?php if ($error): ?>
                    <div
                        class="mb-6 p-4 bg-[#FDF1E8] border border-[#D97757]/30 text-[#D97757] rounded-xl flex items-center text-sm shadow-sm">
                        <i class="fas fa-exclamation-circle mr-3 text-lg"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="space-y-6">
                <div>
                    <label for="username"
                        class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">Username</label>
                    <div class="relative">
                        <span
                            class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-[#A4B4A6]">
                            <i class="fas fa-user-circle"></i>
                        </span>
                        <input type="text" id="username" name="username" required placeholder="Enter your username"
                            class="luxury-input w-full pl-11 pr-4 py-3 bg-[#FAFAF8] border border-[#EAEAE5] rounded-xl focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] placeholder-[#B0C0B2]">
                    </div>
                </div>

                <div>
                    <label for="password"
                        class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">Password</label>
                    <div class="relative">
                        <span
                            class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-[#A4B4A6]">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" id="password" name="password" required placeholder="Enter your password"
                            class="luxury-input w-full pl-11 pr-4 py-3 bg-[#FAFAF8] border border-[#EAEAE5] rounded-xl focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] placeholder-[#B0C0B2]">
                    </div>
                </div>

                <div>
                    <button type="submit"
                        class="w-full py-4 bg-[#2C3E35] text-white font-medium rounded-xl hover:bg-[#1a2621] transition-all transform hover:scale-[1.02] shadow-lg shadow-[#2C3E35]/20 flex items-center justify-center gap-2">
                        <span>Sign In</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </form>
        </div>

        <div class="text-center mt-8">
            <p class="text-xs text-[#A4B4A6]">&copy; <?php echo date('Y'); ?> 1wellness. Secure Admin Environment.</p>
        </div>
    </div>

</body>

</html>