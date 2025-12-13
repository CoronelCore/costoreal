<x-guest-layout>
    <div class="mb-8">
        <h2 class="text-3xl font-bold text-gray-900 mb-2">Bienvenido</h2>
        <p class="text-gray-600">Ingresa a tu cuenta para continuar</p>
    </div>

    <!-- Session Status -->
    @if (session('status'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl">
            <p class="text-sm text-green-600">{{ session('status') }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <!-- Email Address -->
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                Correo Electrónico
            </label>
            <input type="email" name="email" id="email" value="{{ old('email') }}"
                   required autofocus autocomplete="username"
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
                   required autocomplete="current-password"
                   placeholder="••••••••"
                   class="@error('password') border-red-500 @enderror">
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Remember Me & Forgot Password -->
        <div class="flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox"
                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 w-4 h-4"
                       name="remember">
                <span class="ml-2 text-sm text-gray-600">Recordarme</span>
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                    ¿Olvidaste tu contraseña?
                </a>
            @endif
        </div>

        <!-- Submit Button -->
        <button type="submit" class="btn-primary">
            Iniciar Sesión
        </button>

        <!-- Register Link -->
        <p class="text-center text-sm text-gray-600 pt-4">
            ¿No tienes una cuenta?
            <a href="{{ route('register') }}" class="font-medium text-indigo-600 hover:text-indigo-500">
                Regístrate aquí
            </a>
        </p>
    </form>
</x-guest-layout>
