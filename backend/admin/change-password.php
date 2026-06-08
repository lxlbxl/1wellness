<?php
require_once 'auth.php';
require_once '../classes/Database.php';
require_once '../classes/BaseModel.php';
require_once '../classes/Admin.php';

$admin = new Admin();
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validate input
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            throw new Exception('All fields are required');
        }

        if ($newPassword !== $confirmPassword) {
            throw new Exception('New password and confirmation do not match');
        }

        if (strlen($newPassword) < 6) {
            throw new Exception('New password must be at least 6 characters long');
        }

        // Change password
        $adminId = $_SESSION['admin_id'];
        $admin->changePassword($adminId, $currentPassword, $newPassword);

        // Log the activity
        $admin->logActivity($adminId, 'password_changed', 'Admin changed their password');

        $message = 'Password changed successfully!';
        $messageType = 'success';

    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Get current admin info
$currentAdmin = $admin->getAdmin($_SESSION['admin_id']);

$pageTitle = 'Change Password - 1wellness Admin';
include 'includes/header.php';
?>

<!-- Header -->
<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
        <a href="dashboard.php" class="text-sm text-[#D97757] font-medium hover:underline mb-1 inline-block">&larr; Back
            to Dashboard</a>
        <h2 class="text-4xl font-serif text-[#2C3E35]">Change Password</h2>
        <p class="text-[#6B7C70] mt-1">Update your account security credentials</p>
    </div>
</div>

<div class="max-w-xl mx-auto">
    <?php if ($message): ?>
        <div
            class="mb-8 p-4 <?php echo $messageType === 'success' ? 'bg-[#F2F4F1] border-[#A4B4A6] text-[#2C3E35]' : 'bg-[#FDF1E8] border-[#D97757] text-[#D97757]'; ?> border rounded-xl flex items-center shadow-sm">
            <i
                class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> mr-3 text-lg"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="luxury-card p-8">
        <h3 class="text-xl font-serif text-[#2C3E35] mb-6 flex items-center">
            <i class="fas fa-lock w-6 text-[#D97757]"></i> Security Update
        </h3>

        <form method="POST" id="changePasswordForm" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-[#2C3E35] mb-2">Current Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-[#A4B4A6]">
                        <i class="fas fa-key"></i>
                    </span>
                    <input type="password" name="current_password" required
                        class="luxury-input w-full pl-10 pr-4 py-3 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35]">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-[#2C3E35] mb-2">New Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-[#A4B4A6]">
                        <i class="fas fa-unlock-alt"></i>
                    </span>
                    <input type="password" id="new_password" name="new_password" required
                        class="luxury-input w-full pl-10 pr-4 py-3 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35]">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-[#2C3E35] mb-2">Confirm New Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-[#A4B4A6]">
                        <i class="fas fa-check-circle"></i>
                    </span>
                    <input type="password" id="confirm_password" name="confirm_password" required
                        class="luxury-input w-full pl-10 pr-4 py-3 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35]">
                </div>
            </div>

            <div class="bg-[#F9FAF9] p-4 rounded-xl border border-[#EAEAE5]">
                <h4 class="text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">Password Requirements</h4>
                <ul class="text-sm text-[#6B7C70] space-y-1 ml-4 list-disc marker:text-[#D97757]">
                    <li>At least 6 characters long</li>
                    <li>Should contain a mix of letters and numbers</li>
                    <li>Avoid using common words or personal information</li>
                </ul>
            </div>

            <div class="pt-2 flex justify-end gap-4">
                <a href="dashboard.php"
                    class="px-6 py-3 border border-[#EAEAE5] rounded-xl text-[#6B7C70] hover:bg-[#F2F4F1] transition-colors font-medium">
                    Cancel
                </a>
                <button type="submit"
                    class="px-8 py-3 bg-[#2C3E35] text-white font-medium rounded-xl hover:bg-[#1a2621] transition-colors shadow-lg shadow-[#2C3E35]/20">
                    Update Password
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Form validation
    document.getElementById('changePasswordForm').addEventListener('submit', function (e) {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;

        if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('New password and confirmation do not match!');
            return false;
        }

        if (newPassword.length < 6) {
            e.preventDefault();
            alert('New password must be at least 6 characters long!');
            return false;
        }
    });

    // Real-time password confirmation validation
    document.getElementById('confirm_password').addEventListener('input', function () {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = this.value;

        if (confirmPassword && newPassword !== confirmPassword) {
            this.setCustomValidity('Passwords do not match');
            this.classList.add('border-red-500');
        } else {
            this.setCustomValidity('');
            this.classList.remove('border-red-500');
        }
    });
</script>

<?php include 'includes/footer.php'; ?>