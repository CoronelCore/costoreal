<section>
    <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
        </div>
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Cambiar Contraseña</h2>
            <p class="text-sm text-gray-600">Asegúrate de usar una contraseña segura y única</p>
        </div>
    </div>

    <form method="post" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        @method('put')

        <div>
            <label for="update_password_current_password" class="block text-sm font-medium text-gray-700 mb-2">
                Contraseña Actual
            </label>
            <input type="password" id="update_password_current_password" name="current_password"
                   autocomplete="current-password"
                   class="w-full @error('current_password', 'updatePassword') border-red-500 @enderror">
            @error('current_password', 'updatePassword')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="update_password_password" class="block text-sm font-medium text-gray-700 mb-2">
                Nueva Contraseña
            </label>
            <input type="password" id="update_password_password" name="password"
                   autocomplete="new-password"
                   class="w-full @error('password', 'updatePassword') border-red-500 @enderror">
            @error('password', 'updatePassword')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="update_password_password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">
                Confirmar Nueva Contraseña
            </label>
            <input type="password" id="update_password_password_confirmation" name="password_confirmation"
                   autocomplete="new-password"
                   class="w-full @error('password_confirmation', 'updatePassword') border-red-500 @enderror">
            @error('password_confirmation', 'updatePassword')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center gap-4 pt-4">
            <button type="submit" class="btn btn-primary">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Actualizar Contraseña
            </button>

            @if (session('status') === 'password-updated')
                <p x-data="{ show: true }"
                   x-show="show"
                   x-transition
                   x-init="setTimeout(() => show = false, 3000)"
                   class="text-sm font-medium text-green-600">
                    ✓ Contraseña actualizada correctamente
                </p>
            @endif
        </div>
    </form>
</section>
