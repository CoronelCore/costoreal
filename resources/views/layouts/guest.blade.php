<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'CostoReal') }}</title>

        <!-- Favicon -->
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.ts'])

        <style>
            * {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            }

            :root {
                --color-primary: #6366f1;
                --color-primary-dark: #4f46e5;
                --color-primary-light: #818cf8;
                --radius-md: 12px;
                --radius-lg: 16px;
                --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
                --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
                --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .gradient-primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }

            .btn-primary {
                background: var(--color-primary);
                color: white;
                padding: 0.625rem 1.25rem;
                font-size: 0.875rem;
                font-weight: 500;
                border-radius: var(--radius-md);
                transition: var(--transition);
                border: none;
                cursor: pointer;
                width: 100%;
            }

            .btn-primary:hover {
                background: var(--color-primary-dark);
                transform: translateY(-1px);
                box-shadow: var(--shadow-md);
            }

            input, select, textarea {
                border: 1px solid #e5e7eb;
                border-radius: var(--radius-md);
                padding: 0.625rem 1rem;
                font-size: 0.875rem;
                transition: var(--transition);
                background: white;
                width: 100%;
            }

            input:focus, select:focus, textarea:focus {
                border-color: var(--color-primary);
                outline: none;
                box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            }

            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }

            .animate-fadeIn {
                animation: fadeIn 0.5s ease-out;
            }
        </style>
    </head>
    <body class="h-full antialiased">
        <div class="min-h-screen flex">
            <!-- Left side - Form -->
            <div class="flex-1 flex items-center justify-center px-4 sm:px-6 lg:px-20 bg-gray-50">
                <div class="w-full max-w-md animate-fadeIn">
                    <!-- Logo -->
                    <div class="flex items-center gap-3 mb-8">
                        <div class="w-12 h-12 rounded-xl gradient-primary flex items-center justify-center">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <span class="text-2xl font-bold text-gray-900">CostoReal</span>
                    </div>

                    <!-- Form Content -->
                    {{ $slot }}
                </div>
            </div>

            <!-- Right side - Branding (hidden on mobile) -->
            <div class="hidden lg:flex lg:flex-1 gradient-primary items-center justify-center p-12">
                <div class="text-center text-white">
                    <div class="w-24 h-24 mx-auto mb-6 bg-white/20 rounded-2xl backdrop-blur flex items-center justify-center">
                        <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h2 class="text-3xl font-bold mb-4">Gestiona tus compras</h2>
                    <p class="text-lg text-white/90 max-w-md mx-auto">
                        Calcula el prorrateo de gastos de forma automática y mantén un control preciso de tus viajes de compras.
                    </p>
                </div>
            </div>
        </div>
    </body>
</html>
