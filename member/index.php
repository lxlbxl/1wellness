<?php
/**
 * 1wellness Member Dashboard
 * Refactored & Modularized
 */

// Authentication Check
require_once __DIR__ . '/../backend/config/config.php';
require_once __DIR__ . '/../backend/classes/Database.php';
require_once __DIR__ . '/../backend/classes/MemberAuth.php';

$auth = new MemberAuth();
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$currentUser = $auth->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Area | 1wellness</title>
    
    <!-- Dependencies -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind Custom Config -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        sage: {
                            50: '#F2F4F1',
                            100: '#E3E8E1',
                            200: '#C7D1C3',
                            300: '#A4B4A6',
                            400: '#6B7C70',
                            500: '#2C3E35',
                            600: '#1A2620',
                        },
                        coral: {
                            400: '#D97757',
                            500: '#BF6649',
                        },
                        cream: {
                            50: '#FDFCF8',
                            100: '#F2e6d8',
                        }
                    },
                    fontFamily: {
                        serif: ['"Playfair Display"', 'serif'],
                        sans: ['"Inter"', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    
    <!-- Custom Styles -->
    <link rel="stylesheet" href="css/styles.css">
</head>

<body class="bg-cream-50 text-sage-500 font-sans antialiased min-h-screen pb-24 lg:pb-0">
    
    <!-- Sidebar Component -->
    <?php include 'components/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="lg:ml-80 min-h-screen p-6 lg:p-12">
        <div class="max-w-7xl mx-auto">
            
            <!-- Header Component -->
            <?php include 'components/header.php'; ?>

            <!-- View Sections -->
            <div id="viewContainer" class="relative">
                
                <!-- Dashboard View -->
                <?php include 'components/dashboard_view.php'; ?>

                <!-- Nourish View -->
                <?php include 'components/nourish_view.php'; ?>

                <!-- Weekly View -->
                <?php include 'components/weekly_view.php'; ?>

                <!-- Tracker View -->
                <?php include 'components/tracker_view.php'; ?>

                <!-- Profile View -->
                <?php include 'components/profile_view.php'; ?>

            </div>

        </div>
    </main>

    <!-- Modals (Recipe, Onboarding) -->
    <?php include 'components/modals.php'; ?>

    <!-- Dashboard Logic -->
    <script src="js/dashboard.js"></script>
    
    <script>
        // Initialize dashboard on page load
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof initialize === 'function') {
                initialize();
            }
        });
    </script>

</body>
</html>
