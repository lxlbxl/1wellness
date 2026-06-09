<?php
// Default title if not set
$pageTitle = isset($pageTitle) ? $pageTitle : '1wellness Admin';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo htmlspecialchars($pageTitle); ?>
    </title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Fonts: Playfair Display (Serif) & Inter (Sans) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap"
        rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Alpine.js -->
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

    <style>
        :root {
            --color-moss: #2C3E35;
            --color-sage: #E3E8E1;
            --color-clay: #D97757;
            --color-cream: #FDFCF8;
            --color-border: #EAEAE5;
            --color-text-mute: #6B7C70;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--color-cream);
            color: var(--color-moss);
            overflow-x: hidden;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-family: 'Playfair Display', serif;
        }

        /* Aesthetic Backgrounds */
        .bg-noise {
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
            opacity: 0.03;
            pointer-events: none;
        }

        .blob-1 {
            background-color: #E3E8E1;
            filter: blur(100px);
        }

        .blob-2 {
            background-color: #F2E6D8;
            filter: blur(100px);
        }

        /* Component Styling */
        .luxury-card {
            background-color: white;
            border: 1px solid var(--color-border);
            border-radius: 1.5rem;
            /* rounded-3xl */
            transition: all 0.3s ease;
        }

        .luxury-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 40px -10px rgba(44, 62, 53, 0.08);
            border-color: #D4D4D0;
        }

        .action-button {
            transition: all 0.2s ease;
        }

        .action-button:hover {
            transform: scale(1.02);
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #E3E8E1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #D1D8D0;
        }
    </style>
</head>

<body class="relative min-h-screen">

    <!-- Background Texture & Gradient Blobs -->
    <div class="fixed inset-0 bg-noise z-0"></div>
    <div class="fixed top-[-10%] left-[-10%] w-[500px] h-[500px] blob-1 rounded-full opacity-60 z-0"></div>
    <div class="fixed bottom-[-10%] right-[-10%] w-[600px] h-[600px] blob-2 rounded-full opacity-60 z-0"></div>

    <!-- Navigation Wrapper -->
    <div class="relative z-20 bg-white/80 backdrop-blur-md border-b border-[#EAEAE5]">
        <?php include __DIR__ . '/nav.php'; ?>
    </div>

    <!-- Main Content -->
    <div class="relative z-10 max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">