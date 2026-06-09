<?php
require_once 'auth.php';

$db = Database::getInstance();
$message = '';
$messageType = '';

// Handle AJAX requests for quick toggles
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? '';

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        exit;
    }

    switch ($action) {
        case 'toggle_active':
            $currentStatus = $_POST['current_status'] ?? 'active';
            $newStatus = $currentStatus === 'active' ? 'inactive' : 'active';

            try {
                if ($db->isFileStorage()) {
                    $usersFile = '../database/data/users.json';
                    if (file_exists($usersFile)) {
                        $users = json_decode(file_get_contents($usersFile), true) ?: [];
                        foreach ($users as &$user) {
                            if ($user['id'] == $id) {
                                $user['status'] = $newStatus;
                                $user['updated_at'] = date('Y-m-d H:i:s');
                                break;
                            }
                        }
                        file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
                    }
                } else {
                    $db->update('users', [
                        'status' => $newStatus,
                        'updated_at' => date('Y-m-d H:i:s')
                    ], "id = :id", [':id' => $id]);
                }
                echo json_encode(['success' => true, 'message' => "User status changed to {$newStatus}", 'new_status' => $newStatus]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error updating status: ' . $e->getMessage()]);
            }
            exit;

        case 'toggle_payment':
            $currentPaymentStatus = $_POST['current_payment_status'] ?? 'not_paid';
            $newPaymentStatus = $currentPaymentStatus === 'paid' ? 'not_paid' : 'paid';

            try {
                if ($db->isFileStorage()) {
                    $usersFile = '../database/data/users.json';
                    if (file_exists($usersFile)) {
                        $users = json_decode(file_get_contents($usersFile), true) ?: [];
                        foreach ($users as &$user) {
                            if ($user['id'] == $id) {
                                $user['payment_status'] = $newPaymentStatus;
                                $user['updated_at'] = date('Y-m-d H:i:s');
                                break;
                            }
                        }
                        file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
                    }
                } else {
                    // Update member_profiles subscription status
                    $profile = $db->fetch("SELECT id FROM member_profiles WHERE user_id = ?", [$id]);
                    if ($profile) {
                        $db->update('member_profiles', [
                            'subscription_status' => $newPaymentStatus === 'paid' ? 'active' : 'inactive',
                            'updated_at' => date('Y-m-d H:i:s')
                        ], "user_id = :uid", [':uid' => $id]);
                    } else {
                        // Create profile if not exists
                        $db->insert('member_profiles', [
                            'user_id' => $id,
                            'subscription_status' => $newPaymentStatus === 'paid' ? 'active' : 'inactive',
                            'subscription_tier' => '30-day',
                            'subscription_expiry' => date('Y-m-d', strtotime('+30 days')),
                            'start_date' => date('Y-m-d')
                        ]);
                    }
                    // Only update users table updated_at timestamp
                    $db->update('users', [
                        'updated_at' => date('Y-m-d H:i:s')
                    ], "id = :id", [':id' => $id]);
                }
                echo json_encode(['success' => true, 'message' => "Payment status changed to {$newPaymentStatus}", 'new_status' => $newPaymentStatus]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error updating payment status: ' . $e->getMessage()]);
            }
            exit;

        case 'update_plan_status':
            $subscriptionStatus = $_POST['subscription_status'] ?? 'inactive';
            $subscriptionExpiry = $_POST['subscription_expiry'] ?? date('Y-m-d', strtotime('+30 days'));

            try {
                if ($db->isFileStorage()) {
                    $usersFile = '../database/data/users.json';
                    if (file_exists($usersFile)) {
                        $users = json_decode(file_get_contents($usersFile), true) ?: [];
                        foreach ($users as &$user) {
                            if ($user['id'] == $id) {
                                $user['subscription_status'] = $subscriptionStatus;
                                $user['subscription_expiry'] = $subscriptionExpiry;
                                $user['payment_status'] = ($subscriptionStatus === 'active') ? 'paid' : 'not_paid';
                                $user['updated_at'] = date('Y-m-d H:i:s');
                                break;
                            }
                        }
                        file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
                    }
                } else {
                    // Update or create member_profiles
                    $profile = $db->fetch("SELECT id FROM member_profiles WHERE user_id = ?", [$id]);
                    if ($profile) {
                        $db->update('member_profiles', [
                            'subscription_status' => $subscriptionStatus,
                            'subscription_expiry' => $subscriptionExpiry,
                            'updated_at' => date('Y-m-d H:i:s')
                        ], "user_id = :uid", [':uid' => $id]);
                    } else {
                        // Create profile if not exists
                        $db->insert('member_profiles', [
                            'user_id' => $id,
                            'subscription_status' => $subscriptionStatus,
                            'subscription_tier' => '30-day',
                            'subscription_expiry' => $subscriptionExpiry,
                            'start_date' => date('Y-m-d')
                        ]);
                    }
                    // Only update users table updated_at timestamp
                    $db->update('users', [
                        'updated_at' => date('Y-m-d H:i:s')
                    ], "id = :id", [':id' => $id]);
                }
                echo json_encode(['success' => true, 'message' => 'Plan status updated successfully', 'subscription_status' => $subscriptionStatus, 'subscription_expiry' => $subscriptionExpiry]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error updating plan status: ' . $e->getMessage()]);
            }
            exit;
    }
}

// Handle regular form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $condition_type = trim($_POST['condition_type'] ?? '');

            if ($name && $email) {
                $userData = [
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'condition_type' => $condition_type,
                    'status' => 'active',
                    'payment_status' => 'not_paid',
                    'created_at' => date('Y-m-d H:i:s')
                ];

                if ($db->isFileStorage()) {
                    $usersFile = '../database/data/users.json';
                    $users = [];
                    if (file_exists($usersFile)) {
                        $users = json_decode(file_get_contents($usersFile), true) ?: [];
                    }
                    $userData['id'] = count($users) + 1;
                    $users[] = $userData;
                    file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
                } else {
                    $stmt = $db->prepare("INSERT INTO users (name, email, phone, condition_type, status, created_at) VALUES (?, ?, ?, ?, 'active', ?)");
                    $stmt->execute([$name, $email, $phone, $condition_type, $userData['created_at']]);
                }

                $message = 'User created successfully!';
                $messageType = 'success';
            } else {
                $message = 'Name and email are required!';
                $messageType = 'error';
            }
            break;

        case 'update':
            $id = $_POST['id'] ?? '';
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $condition_type = trim($_POST['condition_type'] ?? '');

            if ($id && $name && $email) {
                if ($db->isFileStorage()) {
                    $usersFile = '../database/data/users.json';
                    if (file_exists($usersFile)) {
                        $users = json_decode(file_get_contents($usersFile), true) ?: [];
                        foreach ($users as &$user) {
                            if ($user['id'] == $id) {
                                $user['name'] = $name;
                                $user['email'] = $email;
                                $user['phone'] = $phone;
                                $user['condition_type'] = $condition_type;
                                $user['updated_at'] = date('Y-m-d H:i:s');
                                break;
                            }
                        }
                        file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
                    }
                } else {
                    $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, phone = ?, condition_type = ?, updated_at = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $phone, $condition_type, date('Y-m-d H:i:s'), $id]);
                }

                $message = 'User updated successfully!';
                $messageType = 'success';
            } else {
                $message = 'ID, name and email are required!';
                $messageType = 'error';
            }
            break;

        case 'delete':
            $id = $_POST['id'] ?? '';

            if ($id) {
                if ($db->isFileStorage()) {
                    $usersFile = '../database/data/users.json';
                    if (file_exists($usersFile)) {
                        $users = json_decode(file_get_contents($usersFile), true) ?: [];
                        $users = array_filter($users, function ($user) use ($id) {
                            return $user['id'] != $id;
                        });
                        file_put_contents($usersFile, json_encode(array_values($users), JSON_PRETTY_PRINT));
                    }
                } else {
                    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$id]);
                }

                $message = 'User deleted successfully!';
                $messageType = 'success';
            } else {
                $message = 'User ID is required!';
                $messageType = 'error';
            }
            break;

        case 'activate':
            $id = $_POST['id'] ?? '';
            if ($id) {
                $userModel = new User();
                $u = $userModel->find($id);
                if ($u) {
                    $creds = $userModel->generateCredentials($u['name'] ?? $u['first_name']);
                    $hash = password_hash($creds['password'], PASSWORD_DEFAULT);

                    $userModel->update($id, [
                        'username' => $creds['username'],
                        'password_hash' => $hash,
                        'type' => 'customer',
                        'priority_access' => 1,
                        'status' => 'active'
                    ]);

                    // Create Member Profile if not exists
                    $existingProfile = $db->fetch("SELECT id FROM member_profiles WHERE user_id = ?", [$id]);
                    if (!$existingProfile) {
                        $db->insert('member_profiles', [
                            'user_id' => $id,
                            'pcos_type' => $u['condition_type'] ?? 'General',
                            'subscription_tier' => '30-day',
                            'subscription_status' => 'active',
                            'subscription_expiry' => date('Y-m-d', strtotime('+30 days')),
                            'start_date' => date('Y-m-d')
                        ]);
                    }

                    $message = "User activated! Credentials: Username: {$creds['username']} | Password: {$creds['password']}";
                    $messageType = 'success';
                }
            }
            break;

        case 'reset_password':
            $id = $_POST['id'] ?? '';
            if ($id) {
                $userModel = new User();
                $u = $userModel->find($id);
                if ($u) {
                    $newPassword = bin2hex(random_bytes(4));
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

                    $userModel->update($id, [
                        'password_hash' => $hash
                    ]);

                    $message = "Password reset for {$u['name']}! New Password: {$newPassword}";
                    $messageType = 'success';
                } else {
                    $message = 'User not found!';
                    $messageType = 'error';
                }
            } else {
                $message = 'User ID is required!';
                $messageType = 'error';
            }
            break;
    }
}

// Pagination settings
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$conditionFilter = isset($_GET['condition']) ? trim($_GET['condition']) : '';

// Get all users
$allUsers = [];
if ($db->isFileStorage()) {
    $usersFile = '../database/data/users.json';
    if (file_exists($usersFile)) {
        $allUsers = json_decode(file_get_contents($usersFile), true) ?: [];
    }
} else {
    $sql = "SELECT u.*, mp.subscription_expiry, mp.subscription_status as profile_status, mp.subscription_tier 
            FROM users u 
            LEFT JOIN member_profiles mp ON u.id = mp.user_id 
            ORDER BY u.created_at DESC";
    $stmt = $db->query($sql);
    $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Apply filters
$filteredUsers = $allUsers;

if ($search) {
    $searchLower = strtolower($search);
    $filteredUsers = array_filter($filteredUsers, function ($user) use ($searchLower) {
        return
            stripos($user['name'] ?? '', $searchLower) !== false ||
            stripos($user['email'] ?? '', $searchLower) !== false ||
            stripos($user['phone'] ?? '', $searchLower) !== false;
    });
}

if ($statusFilter) {
    $filteredUsers = array_filter($filteredUsers, function ($user) use ($statusFilter) {
        $userStatus = $user['status'] ?? 'active';
        return $userStatus === $statusFilter;
    });
}

if ($conditionFilter) {
    $filteredUsers = array_filter($filteredUsers, function ($user) use ($conditionFilter) {
        return ($user['condition_type'] ?? '') === $conditionFilter;
    });
}

$totalUsers = count($filteredUsers);
$totalPages = ceil($totalUsers / $perPage);
$page = min($page, max(1, $totalPages));
$offset = ($page - 1) * $perPage;

// Get paginated users
$users = array_slice(array_values($filteredUsers), $offset, $perPage);

$pageTitle = 'Manage Users - 1wellness Admin';
include 'includes/header.php';
?>

<!-- Header -->
<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
        <div class="flex items-center gap-2 mb-2">
            <a href="dashboard.php" class="text-[#6B7C70] hover:text-[#2C3E35] text-sm"><i
                    class="fas fa-arrow-left mr-1"></i> Dashboard</a>
            <span class="text-[#EAEAE5]">/</span>
            <span class="text-[#6B7C70] text-sm font-medium">Users</span>
        </div>
        <h2 class="text-4xl font-serif text-[#2C3E35]">
            User Management
        </h2>
    </div>
</div>

<!-- Messages -->
<?php if ($message): ?>
    <div
        class="mb-6 p-4 rounded-2xl flex items-center gap-3 <?php echo $messageType === 'success' ? 'bg-[#E3E8E1] text-[#2C3E35]' : 'bg-[#FFEBEE] text-[#E57373]'; ?>">
        <div
            class="w-6 h-6 rounded-full flex items-center justify-center <?php echo $messageType === 'success' ? 'bg-[#2C3E35] text-white' : 'bg-[#E57373] text-white'; ?>">
            <i class="fas <?php echo $messageType === 'success' ? 'fa-check' : 'fa-exclamation'; ?> text-xs"></i>
        </div>
        <span class="font-medium text-sm"><?php echo htmlspecialchars($message); ?></span>
    </div>
<?php endif; ?>

<!-- Add User Form -->
<div class="luxury-card p-6 mb-8" x-data="{ expanded: false }">
    <div class="flex justify-between items-center cursor-pointer" @click="expanded = !expanded">
        <h3 class="text-lg font-serif text-[#2C3E35] flex items-center gap-2">
            <span class="w-8 h-8 rounded-full bg-[#FAFAF8] flex items-center justify-center border border-[#EAEAE5]">
                <i class="fas fa-user-plus text-[#D97757] text-sm"></i>
            </span>
            Add New User
        </h3>
        <button class="text-[#6B7C70] transform transition-transform" :class="{'rotate-180': expanded}">
            <i class="fas fa-chevron-down"></i>
        </button>
    </div>

    <div x-show="expanded" x-collapse style="display: none;">
        <form method="POST"
            class="mt-6 pt-6 border-t border-[#EAEAE5] grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <input type="hidden" name="action" value="create">
            <div>
                <label class="block text-xs font-bold text-[#6B7C70] uppercase tracking-wide mb-2">Name *</label>
                <input type="text" name="name" required
                    class="w-full px-4 py-2 bg-[#FAFAF8] border border-[#EAEAE5] rounded-xl focus:outline-none focus:border-[#2C3E35] focus:ring-1 focus:ring-[#2C3E35] transition-colors"
                    placeholder="Jane Doe">
            </div>
            <div>
                <label class="block text-xs font-bold text-[#6B7C70] uppercase tracking-wide mb-2">Email *</label>
                <input type="email" name="email" required
                    class="w-full px-4 py-2 bg-[#FAFAF8] border border-[#EAEAE5] rounded-xl focus:outline-none focus:border-[#2C3E35] focus:ring-1 focus:ring-[#2C3E35] transition-colors"
                    placeholder="jane@example.com">
            </div>
            <div>
                <label class="block text-xs font-bold text-[#6B7C70] uppercase tracking-wide mb-2">Phone</label>
                <input type="tel" name="phone"
                    class="w-full px-4 py-2 bg-[#FAFAF8] border border-[#EAEAE5] rounded-xl focus:outline-none focus:border-[#2C3E35] focus:ring-1 focus:ring-[#2C3E35] transition-colors"
                    placeholder="+1234567890">
            </div>
            <div>
                <label class="block text-xs font-bold text-[#6B7C70] uppercase tracking-wide mb-2">Condition</label>
                <select name="condition_type"
                    class="w-full px-4 py-2 bg-[#FAFAF8] border border-[#EAEAE5] rounded-xl focus:outline-none focus:border-[#2C3E35] focus:ring-1 focus:ring-[#2C3E35] transition-colors">
                    <option value="">Select Condition</option>
                    <option value="acne">Acne</option>
                    <option value="pcos">PCOS</option>
                    <option value="weight">Weight Management</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="md:col-span-2 lg:col-span-4 flex justify-end mt-2">
                <button type="submit"
                    class="bg-[#2C3E35] text-white px-6 py-2 rounded-full hover:bg-[#3D5245] transition-colors shadow-lg shadow-[#2C3E35]/10 flex items-center gap-2">
                    <i class="fas fa-plus text-xs"></i> Add Member
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Search and Filter Bar -->
<div class="luxury-card p-6 mb-4">
    <form method="GET" class="flex flex-col md:flex-row gap-4" id="filterForm">
        <div class="flex-1">
            <div class="relative">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-[#6B7C70]"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Search by name, email, or phone..."
                    class="w-full pl-12 pr-4 py-3 bg-[#FAFAF8] border border-[#EAEAE5] rounded-xl focus:outline-none focus:border-[#2C3E35] focus:ring-1 focus:ring-[#2C3E35] transition-colors">
            </div>
        </div>
        <div class="flex gap-2">
            <select name="status" onchange="document.getElementById('filterForm').submit()"
                class="px-4 py-3 bg-[#FAFAF8] border border-[#EAEAE5] rounded-xl focus:outline-none focus:border-[#2C3E35] text-sm">
                <option value="">All Statuses</option>
                <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
            <select name="condition" onchange="document.getElementById('filterForm').submit()"
                class="px-4 py-3 bg-[#FAFAF8] border border-[#EAEAE5] rounded-xl focus:outline-none focus:border-[#2C3E35] text-sm">
                <option value="">All Conditions</option>
                <option value="pcos" <?php echo $conditionFilter === 'pcos' ? 'selected' : ''; ?>>PCOS</option>
                <option value="acne" <?php echo $conditionFilter === 'acne' ? 'selected' : ''; ?>>Acne</option>
                <option value="weight" <?php echo $conditionFilter === 'weight' ? 'selected' : ''; ?>>Weight</option>
                <option value="other" <?php echo $conditionFilter === 'other' ? 'selected' : ''; ?>>Other</option>
            </select>
            <select name="per_page" onchange="document.getElementById('filterForm').submit()"
                class="px-4 py-3 bg-[#FAFAF8] border border-[#EAEAE5] rounded-xl focus:outline-none focus:border-[#2C3E35] text-sm">
                <option value="10" <?php echo $perPage === 10 ? 'selected' : ''; ?>>10 per page</option>
                <option value="25" <?php echo $perPage === 25 ? 'selected' : ''; ?>>25 per page</option>
                <option value="50" <?php echo $perPage === 50 ? 'selected' : ''; ?>>50 per page</option>
                <option value="100" <?php echo $perPage === 100 ? 'selected' : ''; ?>>100 per page</option>
            </select>
            <?php if ($search || $statusFilter || $conditionFilter): ?>
                <a href="manage-users.php"
                    class="px-4 py-3 text-[#D97757] hover:text-[#BF6649] text-sm font-medium flex items-center">
                    <i class="fas fa-times mr-1"></i> Clear
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Users Table -->
<div class="luxury-card overflow-hidden">
    <div class="px-6 py-4 border-b border-[#EAEAE5] flex justify-between items-center bg-[#FAFAF8]">
        <h3 class="font-serif text-[#2C3E35] text-lg">Registered Users</h3>
        <span class="text-xs font-medium bg-[#E3E8E1] text-[#2C3E35] px-2 py-1 rounded-full">
            Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $perPage, $totalUsers); ?> of
            <?php echo $totalUsers; ?>
        </span>
    </div>

    <?php if (empty($users)): ?>
        <div class="p-12 text-center">
            <div class="w-16 h-16 bg-[#F2F4F1] rounded-full flex items-center justify-center mx-auto mb-4 text-[#6B7C70]">
                <i class="fas fa-users text-2xl"></i>
            </div>
            <h3 class="text-[#2C3E35] font-serif text-xl mb-1">No users found</h3>
            <p class="text-[#6B7C70] text-sm">Get started by adding a new user above.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[#EAEAE5]">
                <thead class="bg-[#FDFCF8]">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-[#6B7C70] uppercase tracking-wider">User</th>
                        <th
                            class="px-6 py-4 text-left text-xs font-bold text-[#6B7C70] uppercase tracking-wider hidden sm:table-cell">
                            Contact</th>
                        <th
                            class="px-6 py-4 text-left text-xs font-bold text-[#6B7C70] uppercase tracking-wider hidden md:table-cell">
                            Condition</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-[#6B7C70] uppercase tracking-wider">Plan
                            Status</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-[#6B7C70] uppercase tracking-wider">Active
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-[#6B7C70] uppercase tracking-wider">Paid</th>
                        <th
                            class="px-6 py-4 text-left text-xs font-bold text-[#6B7C70] uppercase tracking-wider hidden lg:table-cell">
                            Joined</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-[#6B7C70] uppercase tracking-wider">Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-[#EAEAE5]">
                    <?php foreach ($users as $user):
                        $userStatus = $user['status'] ?? 'active';
                        $paymentStatus = $user['payment_status'] ?? 'not_paid';
                        $isActive = $userStatus === 'active';
                        $isPaid = $paymentStatus === 'paid';
                        ?>
                        <tr class="hover:bg-[#FAFAF8] transition-colors group" data-user-id="<?php echo $user['id']; ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div
                                            class="h-10 w-10 rounded-full bg-[#E3E8E1] flex items-center justify-center text-[#2C3E35] font-serif font-bold text-lg">
                                            <?php echo strtoupper(substr($user['name'] ?? $user['first_name'] ?? 'U', 0, 1)); ?>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-[#2C3E35]">
                                            <?php echo htmlspecialchars($user['name'] ?? $user['first_name'] ?? 'Unknown User'); ?>
                                        </div>
                                        <div class="text-xs text-[#6B7C70]">ID: <?php echo htmlspecialchars($user['id']); ?>
                                        </div>
                                        <div class="text-xs text-[#6B7C70] sm:hidden mt-0.5">
                                            <?php echo htmlspecialchars($user['email']); ?><br>
                                            <span
                                                class="text-[10px]"><?php echo htmlspecialchars($user['phone'] ?? ''); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap hidden sm:table-cell">
                                <div class="text-sm text-[#2C3E35]"><?php echo htmlspecialchars($user['email']); ?></div>
                                <div class="text-xs text-[#6B7C70]"><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap hidden md:table-cell">
                                <?php if (!empty($user['condition_type'])):
                                    $bg = 'bg-[#F2F4F1]';
                                    $text = 'text-[#6B7C70]';
                                    if ($user['condition_type'] === 'pcos') {
                                        $bg = 'bg-[#FDF1E8]';
                                        $text = 'text-[#D97757]';
                                    } elseif ($user['condition_type'] === 'acne') {
                                        $bg = 'bg-[#FFEBEE]';
                                        $text = 'text-[#E57373]';
                                    }
                                    ?>
                                    <span
                                        class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded-full <?php echo $bg . ' ' . $text; ?>">
                                        <?php echo htmlspecialchars(ucfirst($user['condition_type'])); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-[#A4B4A6] text-xs italic">None</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $planStatusLabel = 'Not Paid';
                                $planStatusClass = 'bg-[#F2F4F1] text-[#6B7C70]';
                                $currentSubStatus = $user['profile_status'] ?? 'inactive';
                                $currentExpiry = $user['subscription_expiry'] ?? '';

                                if (!empty($user['subscription_expiry'])) {
                                    $expiry = new DateTime($user['subscription_expiry']);
                                    $today = new DateTime();
                                    $interval = $today->diff($expiry);
                                    $days = (int) $interval->format('%r%a');

                                    if ($days < 0) {
                                        $planStatusLabel = 'Expired';
                                        $planStatusClass = 'bg-[#FFEBEE] text-[#E57373]';
                                    } elseif ($days <= 3) {
                                        $planStatusLabel = 'Renewal';
                                        $planStatusClass = 'bg-[#FFF9C4] text-[#FBC02D]';
                                    } else {
                                        $planStatusLabel = 'Active';
                                        $planStatusClass = 'bg-[#E3E8E1] text-[#2C3E35]';
                                    }
                                } elseif ($isPaid) {
                                    $planStatusLabel = 'Active';
                                    $planStatusClass = 'bg-[#E3E8E1] text-[#2C3E35]';
                                }
                                ?>
                                <div class="flex flex-col">
                                    <div class="flex items-center gap-1">
                                        <span
                                            class="inline-flex px-2 py-0.5 text-[10px] font-bold uppercase rounded-full w-max <?php echo $planStatusClass; ?>">
                                            <?php echo $planStatusLabel; ?>
                                        </span>
                                        <button
                                            onclick="editPlanStatus(<?php echo $user['id']; ?>, '<?php echo $currentSubStatus; ?>', '<?php echo $currentExpiry; ?>')"
                                            class="text-[#6B7C70] hover:text-[#2C3E35] p-1 rounded hover:bg-[#E3E8E1] transition-colors"
                                            title="Edit Plan Status">
                                            <i class="fas fa-pen text-[10px]"></i>
                                        </button>
                                    </div>
                                    <?php if (!empty($user['subscription_expiry'])): ?>
                                        <span class="text-[9px] text-[#A4B4A6] mt-1">Exp:
                                            <?php echo date('M j, Y', strtotime($user['subscription_expiry'])); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button onclick="toggleActive(<?php echo $user['id']; ?>, '<?php echo $userStatus; ?>')"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-[#2C3E35] focus:ring-offset-2 <?php echo $isActive ? 'bg-[#2C3E35]' : 'bg-[#EAEAE5]'; ?>"
                                    title="Click to <?php echo $isActive ? 'deactivate' : 'activate'; ?> user">
                                    <span class="sr-only">Toggle active status</span>
                                    <span
                                        class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform <?php echo $isActive ? 'translate-x-6' : 'translate-x-1'; ?>"></span>
                                </button>
                                <span
                                    class="ml-2 text-xs text-[#6B7C70] status-text-<?php echo $user['id']; ?>"><?php echo $isActive ? 'Active' : 'Inactive'; ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button onclick="togglePayment(<?php echo $user['id']; ?>, '<?php echo $paymentStatus; ?>')"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-[#D97757] focus:ring-offset-2 <?php echo $isPaid ? 'bg-[#D97757]' : 'bg-[#EAEAE5]'; ?>"
                                    title="Click to mark as <?php echo $isPaid ? 'not paid' : 'paid'; ?>">
                                    <span class="sr-only">Toggle payment status</span>
                                    <span
                                        class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform <?php echo $isPaid ? 'translate-x-6' : 'translate-x-1'; ?>"></span>
                                </button>
                                <span
                                    class="ml-2 text-xs text-[#6B7C70] payment-text-<?php echo $user['id']; ?>"><?php echo $isPaid ? 'Paid' : 'Not Paid'; ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-[#6B7C70] hidden lg:table-cell">
                                <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <?php if (empty($user['password_hash']) || !$isActive): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="activate">
                                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                            <button type="submit"
                                                class="text-[#D97757] hover:text-[#B36248] w-8 h-8 rounded-full hover:bg-[#FDF1E8] flex items-center justify-center transition-colors"
                                                title="Activate Account">
                                                <i class="fas fa-bolt text-xs"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="ai-oversight.php?user_id=<?php echo $user['id']; ?>"
                                        class="text-[#D97757] hover:text-[#B36248] w-8 h-8 rounded-full hover:bg-[#FDF1E8] flex items-center justify-center transition-colors"
                                        title="AI Plan Oversight">
                                        <i class="fas fa-magic text-xs"></i>
                                    </a>
                                    <button onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)"
                                        class="text-[#6B7C70] hover:text-[#2C3E35] w-8 h-8 rounded-full hover:bg-[#E3E8E1] flex items-center justify-center transition-colors"
                                        title="Edit">
                                        <i class="fas fa-pen text-xs"></i>
                                    </button>
                                    <button
                                        onclick="resetPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name'] ?? $user['first_name'] ?? 'Unknown'); ?>')"
                                        class="text-[#FBC02D] hover:text-[#F57F17] w-8 h-8 rounded-full hover:bg-[#FFF9C4] flex items-center justify-center transition-colors"
                                        title="Reset Password">
                                        <i class="fas fa-key text-xs"></i>
                                    </button>
                                    <button
                                        onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name'] ?? $user['first_name'] ?? 'Unknown'); ?>')"
                                        class="text-[#E57373] hover:text-red-700 w-8 h-8 rounded-full hover:bg-[#FFEBEE] flex items-center justify-center transition-colors"
                                        title="Delete">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div
                class="px-6 py-4 border-t border-[#EAEAE5] bg-[#FAFAF8] flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="text-sm text-[#6B7C70]">
                    Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&per_page=<?php echo $perPage; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&condition=<?php echo urlencode($conditionFilter); ?>"
                            class="px-3 py-2 rounded-lg border border-[#EAEAE5] text-[#6B7C70] hover:bg-white hover:text-[#2C3E35] transition-colors">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);

                    if ($startPage > 1): ?>
                        <a href="?page=1&per_page=<?php echo $perPage; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&condition=<?php echo urlencode($conditionFilter); ?>"
                            class="px-3 py-2 rounded-lg border border-[#EAEAE5] text-[#6B7C70] hover:bg-white hover:text-[#2C3E35] transition-colors">1</a>
                        <?php if ($startPage > 2): ?>
                            <span class="px-2 text-[#A4B4A6]">...</span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="px-3 py-2 rounded-lg bg-[#2C3E35] text-white font-medium"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&per_page=<?php echo $perPage; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&condition=<?php echo urlencode($conditionFilter); ?>"
                                class="px-3 py-2 rounded-lg border border-[#EAEAE5] text-[#6B7C70] hover:bg-white hover:text-[#2C3E35] transition-colors"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                            <span class="px-2 text-[#A4B4A6]">...</span>
                        <?php endif; ?>
                        <a href="?page=<?php echo $totalPages; ?>&per_page=<?php echo $perPage; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&condition=<?php echo urlencode($conditionFilter); ?>"
                            class="px-3 py-2 rounded-lg border border-[#EAEAE5] text-[#6B7C70] hover:bg-white hover:text-[#2C3E35] transition-colors"><?php echo $totalPages; ?></a>
                    <?php endif; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&per_page=<?php echo $perPage; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&condition=<?php echo urlencode($conditionFilter); ?>"
                            class="px-3 py-2 rounded-lg border border-[#EAEAE5] text-[#6B7C70] hover:bg-white hover:text-[#2C3E35] transition-colors">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Edit User Modal -->
<div id="editModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-[#2C3E35]/40 backdrop-blur-sm transition-opacity"></div>

    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div
                class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-[#EAEAE5]">
                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div
                            class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-[#E3E8E1] sm:mx-0 sm:h-10 sm:w-10 text-[#2C3E35]">
                            <i class="fas fa-user-edit"></i>
                        </div>
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
                            <h3 class="text-lg font-serif leading-6 text-[#2C3E35]">Edit User</h3>
                            <div class="mt-4">
                                <form id="editForm" method="POST" class="space-y-4">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" id="editId">

                                    <div>
                                        <label
                                            class="block text-xs font-bold text-[#6B7C70] uppercase tracking-wide mb-1">Name</label>
                                        <input type="text" name="name" id="editName" required
                                            class="w-full px-3 py-2 bg-[#FAFAF8] border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] focus:ring-1 focus:ring-[#2C3E35]">
                                    </div>
                                    <div>
                                        <label
                                            class="block text-xs font-bold text-[#6B7C70] uppercase tracking-wide mb-1">Email</label>
                                        <input type="email" name="email" id="editEmail" required
                                            class="w-full px-3 py-2 bg-[#FAFAF8] border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] focus:ring-1 focus:ring-[#2C3E35]">
                                    </div>
                                    <div>
                                        <label
                                            class="block text-xs font-bold text-[#6B7C70] uppercase tracking-wide mb-1">Phone</label>
                                        <input type="tel" name="phone" id="editPhone"
                                            class="w-full px-3 py-2 bg-[#FAFAF8] border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] focus:ring-1 focus:ring-[#2C3E35]">
                                    </div>
                                    <div>
                                        <label
                                            class="block text-xs font-bold text-[#6B7C70] uppercase tracking-wide mb-1">Condition</label>
                                        <select name="condition_type" id="editCondition"
                                            class="w-full px-3 py-2 bg-[#FAFAF8] border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] focus:ring-1 focus:ring-[#2C3E35]">
                                            <option value="">Select Condition</option>
                                            <option value="acne">Acne</option>
                                            <option value="pcos">PCOS</option>
                                            <option value="weight">Weight Management</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>

                                    <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse gap-3">
                                        <button type="submit"
                                            class="inline-flex w-full justify-center rounded-full bg-[#2C3E35] px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#3D5245] sm:w-auto">
                                            Update User
                                        </button>
                                        <button type="button" onclick="closeEditModal()"
                                            class="mt-3 inline-flex w-full justify-center rounded-full bg-white px-5 py-2 text-sm font-semibold text-[#6B7C70] shadow-sm ring-1 ring-inset ring-[#EAEAE5] hover:bg-[#FAFAF8] sm:mt-0 sm:w-auto">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-[#2C3E35]/40 backdrop-blur-sm transition-opacity"></div>

    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div
                class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-[#EAEAE5]">
                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div
                            class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-[#FFEBEE] sm:mx-0 sm:h-10 sm:w-10 text-[#D32F2F]">
                            <i class="fas fa-triangle-exclamation"></i>
                        </div>
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                            <h3 class="text-lg font-serif leading-6 text-[#2C3E35]">Delete User</h3>
                            <div class="mt-2">
                                <p class="text-sm text-[#6B7C70]">
                                    Are you sure you want to delete user <strong id="deleteUserName"
                                        class="text-[#2C3E35]"></strong>?
                                    This action cannot be undone.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-[#FAFAF8] px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-3">
                    <form id="deleteForm" method="POST" class="inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteId">
                        <button type="submit"
                            class="inline-flex w-full justify-center rounded-full bg-[#D32F2F] px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#B71C1C] sm:ml-3 sm:w-auto">
                            Delete
                        </button>
                    </form>
                    <button type="button" onclick="closeDeleteModal()"
                        class="mt-3 inline-flex w-full justify-center rounded-full bg-white px-5 py-2 text-sm font-semibold text-[#6B7C70] shadow-sm ring-1 ring-inset ring-[#EAEAE5] hover:bg-gray-50 sm:mt-0 sm:w-auto">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reset Password Confirmation Modal -->
<div id="resetModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-[#2C3E35]/40 backdrop-blur-sm transition-opacity"></div>

    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div
                class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-[#EAEAE5]">
                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div
                            class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-[#FFF9C4] sm:mx-0 sm:h-10 sm:w-10 text-[#F57F17]">
                            <i class="fas fa-key"></i>
                        </div>
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                            <h3 class="text-lg font-serif leading-6 text-[#2C3E35]">Reset Password</h3>
                            <div class="mt-2">
                                <p class="text-sm text-[#6B7C70]">
                                    Are you sure you want to reset the password for <strong id="resetUserName"
                                        class="text-[#2C3E35]"></strong>?
                                    A new password will be generated and displayed.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-[#FAFAF8] px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-3">
                    <form id="resetForm" method="POST" class="inline">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="id" id="resetId">
                        <button type="submit"
                            class="inline-flex w-full justify-center rounded-full bg-[#F57F17] px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#E65100] sm:ml-3 sm:w-auto">
                            Reset Password
                        </button>
                    </form>
                    <button type="button" onclick="closeResetModal()"
                        class="mt-3 inline-flex w-full justify-center rounded-full bg-white px-5 py-2 text-sm font-semibold text-[#6B7C70] shadow-sm ring-1 ring-inset ring-[#EAEAE5] hover:bg-gray-50 sm:mt-0 sm:w-auto">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Plan Status Modal -->
<div id="planStatusModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-[#2C3E35]/40 backdrop-blur-sm transition-opacity"></div>

    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div
                class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-[#EAEAE5]">
                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div
                            class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-[#E3E8E1] sm:mx-0 sm:h-10 sm:w-10 text-[#2C3E35]">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
                            <h3 class="text-lg font-serif leading-6 text-[#2C3E35]">Edit Plan Status</h3>
                            <div class="mt-4">
                                <form id="planStatusForm" class="space-y-4">
                                    <input type="hidden" name="id" id="planStatusUserId">

                                    <div>
                                        <label
                                            class="block text-xs font-bold text-[#6B7C70] uppercase tracking-wide mb-1">Subscription
                                            Status</label>
                                        <select name="subscription_status" id="planStatusSelect"
                                            class="w-full px-3 py-2 bg-[#FAFAF8] border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] focus:ring-1 focus:ring-[#2C3E35]">
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                            <option value="expired">Expired</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            class="block text-xs font-bold text-[#6B7C70] uppercase tracking-wide mb-1">Subscription
                                            Expiry Date</label>
                                        <input type="date" name="subscription_expiry" id="planExpiryDate"
                                            class="w-full px-3 py-2 bg-[#FAFAF8] border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] focus:ring-1 focus:ring-[#2C3E35]">
                                    </div>

                                    <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse gap-3">
                                        <button type="button" onclick="savePlanStatus()"
                                            class="inline-flex w-full justify-center rounded-full bg-[#2C3E35] px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#3D5245] sm:w-auto">
                                            Update Plan Status
                                        </button>
                                        <button type="button" onclick="closePlanStatusModal()"
                                            class="mt-3 inline-flex w-full justify-center rounded-full bg-white px-5 py-2 text-sm font-semibold text-[#6B7C70] shadow-sm ring-1 ring-inset ring-[#EAEAE5] hover:bg-[#FAFAF8] sm:mt-0 sm:w-auto">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function editUser(user) {
        document.getElementById('editId').value = user.id;
        document.getElementById('editName').value = user.name;
        document.getElementById('editEmail').value = user.email;
        document.getElementById('editPhone').value = user.phone || '';
        document.getElementById('editCondition').value = user.condition_type || '';
        document.getElementById('editModal').classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }

    function deleteUser(id, name) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteUserName').textContent = name;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }

    function resetPassword(id, name) {
        document.getElementById('resetId').value = id;
        document.getElementById('resetUserName').textContent = name;
        document.getElementById('resetModal').classList.remove('hidden');
    }

    function closeResetModal() {
        document.getElementById('resetModal').classList.add('hidden');
    }

    // Toggle Active Status
    function toggleActive(id, currentStatus) {
        const formData = new FormData();
        formData.append('action', 'toggle_active');
        formData.append('id', id);
        formData.append('current_status', currentStatus);

        fetch('manage-users.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Update UI
                    const row = document.querySelector(`tr[data-user-id="${id}"]`);
                    const button = row.querySelector('td:nth-child(5) button');
                    const text = row.querySelector(`.status-text-${id}`);
                    const isActive = data.new_status === 'active';

                    button.className = `relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-[#2C3E35] focus:ring-offset-2 ${isActive ? 'bg-[#2C3E35]' : 'bg-[#EAEAE5]'}`;
                    button.setAttribute('onclick', `toggleActive(${id}, '${data.new_status}')`);
                    button.setAttribute('title', `Click to ${isActive ? 'deactivate' : 'activate'} user`);

                    const span = button.querySelector('span:last-child');
                    span.className = `inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${isActive ? 'translate-x-6' : 'translate-x-1'}`;

                    text.textContent = isActive ? 'Active' : 'Inactive';
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('An error occurred. Please try again.', 'error');
            });
    }

    // Toggle Payment Status
    function togglePayment(id, currentStatus) {
        const formData = new FormData();
        formData.append('action', 'toggle_payment');
        formData.append('id', id);
        formData.append('current_payment_status', currentStatus);

        fetch('manage-users.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Update UI
                    const row = document.querySelector(`tr[data-user-id="${id}"]`);
                    const button = row.querySelector('td:nth-child(6) button');
                    const text = row.querySelector(`.payment-text-${id}`);
                    const isPaid = data.new_status === 'paid';

                    button.className = `relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-[#D97757] focus:ring-offset-2 ${isPaid ? 'bg-[#D97757]' : 'bg-[#EAEAE5]'}`;
                    button.setAttribute('onclick', `togglePayment(${id}, '${data.new_status}')`);
                    button.setAttribute('title', `Click to mark as ${isPaid ? 'not paid' : 'paid'}`);

                    const span = button.querySelector('span:last-child');
                    span.className = `inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${isPaid ? 'translate-x-6' : 'translate-x-1'}`;

                    text.textContent = isPaid ? 'Paid' : 'Not Paid';
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('An error occurred. Please try again.', 'error');
            });
    }

    // Edit Plan Status
    function editPlanStatus(id, currentStatus, currentExpiry) {
        document.getElementById('planStatusUserId').value = id;
        document.getElementById('planStatusSelect').value = currentStatus || 'inactive';
        document.getElementById('planExpiryDate').value = currentExpiry || '';
        document.getElementById('planStatusModal').classList.remove('hidden');
    }

    function closePlanStatusModal() {
        document.getElementById('planStatusModal').classList.add('hidden');
    }

    function savePlanStatus() {
        const id = document.getElementById('planStatusUserId').value;
        const subscriptionStatus = document.getElementById('planStatusSelect').value;
        const subscriptionExpiry = document.getElementById('planExpiryDate').value;

        const formData = new FormData();
        formData.append('action', 'update_plan_status');
        formData.append('id', id);
        formData.append('subscription_status', subscriptionStatus);
        formData.append('subscription_expiry', subscriptionExpiry);

        fetch('manage-users.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    closePlanStatusModal();
                    // Reload page to show updated status
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('An error occurred. Please try again.', 'error');
            });
    }

    // Notification function
    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 px-6 py-4 rounded-xl shadow-lg z-50 animate-fade-in-up ${type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'
            }`;
        notification.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i> ${message}`;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
</script>

<?php include 'includes/footer.php'; ?>