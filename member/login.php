<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | 1wellness Member Area</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/images/brand/favicon-32.png">
    <link rel="shortcut icon" href="/images/brand/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap"
        rel="stylesheet">
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
    <style>
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease-out forwards;
        }
    </style>
</head>

<body class="bg-cream-50 font-sans text-sage-500 overflow-hidden">
    <div class="min-h-screen flex items-center justify-center p-6 relative">
        <!-- Background Blobs -->
        <div
            class="absolute top-0 right-0 w-[800px] h-[800px] bg-sage-100/40 rounded-full blur-[120px] -translate-y-1/2 translate-x-1/2">
        </div>
        <div
            class="absolute bottom-0 left-0 w-[600px] h-[600px] bg-coral-400/5 rounded-full blur-[100px] translate-y-1/2 -translate-x-1/2">
        </div>

        <div class="w-full max-w-md relative z-10 animate-fade-in-up">
            <!-- Logo -->
            <div class="flex flex-col items-center mb-12">
                <img src="/images/brand/logo-nav.png" alt="1wellness" class="h-20 w-auto">
            </div>

            <div
                class="bg-white/40 backdrop-blur-xl border border-white/40 p-10 rounded-[2.5rem] shadow-2xl shadow-sage-950/5">
                <div class="mb-8">
                    <h2 class="text-2xl font-serif text-sage-600 mb-2">Welcome Back</h2>
                    <p class="text-sage-400 text-sm">Sign in with your email or username to access your personalized
                        protocol.</p>
                </div>

                <form id="loginForm" class="space-y-6">
                    <div class="relative group">
                        <i data-lucide="user"
                            class="absolute left-4 top-1/2 -translate-y-1/2 text-sage-300 group-focus-within:text-sage-500 transition-colors w-5 h-5"></i>
                        <input type="text" id="email" required placeholder="Email or Username"
                            class="w-full h-14 bg-white/50 border border-sage-100 rounded-2xl pl-12 pr-4 text-sage-600 placeholder:text-sage-300 focus:outline-none focus:ring-2 focus:ring-sage-500/10 focus:border-sage-500 transition-all font-medium">
                    </div>

                    <div class="relative group">
                        <i data-lucide="lock"
                            class="absolute left-4 top-1/2 -translate-y-1/2 text-sage-300 group-focus-within:text-sage-500 transition-colors w-5 h-5"></i>
                        <input type="password" id="password" required placeholder="Password"
                            class="w-full h-14 bg-white/50 border border-sage-100 rounded-2xl pl-12 pr-4 text-sage-600 placeholder:text-sage-300 focus:outline-none focus:ring-2 focus:ring-sage-500/10 focus:border-sage-500 transition-all font-medium">
                    </div>

                    <div id="errorMessage"
                        class="hidden p-4 bg-red-50 text-red-500 text-sm rounded-xl border border-red-100"></div>

                    <button type="submit" id="submitBtn"
                        class="w-full h-14 bg-sage-500 text-white rounded-2xl font-bold flex items-center justify-center gap-3 hover:bg-sage-600 hover:scale-[1.02] active:scale-[0.98] transition-all shadow-xl shadow-sage-500/20 disabled:opacity-50 disabled:scale-100">
                        <span id="btnText">Sign In</span>
                        <i data-lucide="arrow-right" class="w-5 h-5" id="btnIcon"></i>
                        <div id="btnLoader"
                            class="hidden animate-spin w-5 h-5 border-2 border-white border-t-transparent rounded-full">
                        </div>
                    </button>
                </form>

                <div class="mt-10 pt-10 border-t border-sage-50 text-center">
                    <p class="text-sage-400 text-sm">
                        Don't have an account? <a href="../index.html#products"
                            class="text-sage-500 font-bold hover:underline">View Plans</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        const loginForm = document.getElementById('loginForm');
        const errorMessage = document.getElementById('errorMessage');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const btnIcon = document.getElementById('btnIcon');
        const btnLoader = document.getElementById('btnLoader');

        // Check for Auto-Login Token
        window.addEventListener('load', async () => {
            const urlParams = new URLSearchParams(window.location.search);
            const token = urlParams.get('autologin');

            if (token) {
                console.log("Found auto-login token, verifying...");
                setIsLoading(true);
                btnText.textContent = 'Verifying link...';

                try {
                    const res = await fetch('/member/api/verify_token.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'include',
                        body: JSON.stringify({ token })
                    });
                    const data = await res.json();

                    if (data.success) {
                        window.location.href = 'index.php';
                    } else {
                        showError("Auto-login link expired or invalid.");
                        setIsLoading(false);
                        btnText.textContent = 'Sign In';
                    }
                } catch (e) {
                    showError("Connection error verifying link.");
                    setIsLoading(false);
                    btnText.textContent = 'Sign In';
                }
            }
        });

        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            // UI State: Loading
            setIsLoading(true);
            errorMessage.classList.add('hidden');

            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;

            try {
                const response = await fetch('/member/api/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ email, password })
                });

                const data = await response.json();

                if (data.success) {
                    window.location.href = 'index.php';
                } else {
                    showError(data.error || 'Invalid credentials');
                }
            } catch (err) {
                showError('Login failed. Please check your connection.');
            } finally {
                setIsLoading(false);
            }
        });

        function setIsLoading(loading) {
            submitBtn.disabled = loading;
            if (loading) {
                btnText.textContent = 'Signing in...';
                btnIcon.classList.add('hidden');
                btnLoader.classList.remove('hidden');
            } else {
                btnText.textContent = 'Sign In';
                btnIcon.classList.remove('hidden');
                btnLoader.classList.add('hidden');
            }
        }

        function showError(msg) {
            errorMessage.textContent = msg;
            errorMessage.classList.remove('hidden');
        }
    </script>
</body>

</html>
