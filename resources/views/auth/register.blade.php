<x-guest-layout>
    <div class="mb-8">
        <h2 class="text-3xl font-bold text-gray-900 mb-2">Crear Cuenta</h2>
        <p class="text-gray-600">Regístrate para comenzar a gestionar tus viajes</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <!-- Name -->
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                Nombre Completo
            </label>
            <input type="text" name="name" id="name" value="{{ old('name') }}"
                   required autofocus autocomplete="name"
                   placeholder="Juan Pérez"
                   class="@error('name') border-red-500 @enderror">
            @error('name')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Email Address -->
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                Correo Electrónico
            </label>
            <input type="email" name="email" id="email" value="{{ old('email') }}"
                   required autocomplete="username"
                   placeholder="tu@email.com"
                   class="@error('email') border-red-500 @enderror">
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Password -->
        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                Contraseña
            </label>
            <input type="password" name="password" id="password"
                   required autocomplete="new-password"
                   placeholder="••••••••"
                   class="@error('password') border-red-500 @enderror">
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Confirm Password -->
        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">
                Confirmar Contraseña
            </label>
            <input type="password" name="password_confirmation" id="password_confirmation"
                   required autocomplete="new-password"
                   placeholder="••••••••">
            @error('password_confirmation')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Submit Button -->
        <button type="submit" class="btn-primary">
            Crear Cuenta
        </button>

        <!-- Login Link -->
        <p class="text-center text-sm text-gray-600 pt-4">
            ¿Ya tienes una cuenta?
            <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:text-indigo-500">
                Inicia sesión aquí
            </a>
        </p>
    </form>
</x-guest-layout>
