<x-modern>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-lg bg-primary-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Mi Perfil</h1>
                <p class="mt-1 text-sm text-gray-600">Gestiona tu información personal y configuración</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="space-y-6">
            <!-- Información del Perfil -->
            <div class="card">
                @include('profile.partials.update-profile-information-form')
            </div>

            <!-- Cambiar Contraseña -->
            <div class="card">
                @include('profile.partials.update-password-form')
            </div>
        </div>
    </div>
</x-modern>
