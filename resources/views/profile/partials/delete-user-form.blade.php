<section x-data="{ showModal: {{ $errors->userDeletion->isNotEmpty() ? 'true' : 'false' }} }">
    <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center">
            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
        </div>
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Eliminar Cuenta</h2>
            <p class="text-sm text-gray-600">Elimina permanentemente tu cuenta y todos tus datos</p>
        </div>
    </div>

    <div class="space-y-4">
        <p class="text-sm text-gray-600">
            Una vez que tu cuenta sea eliminada, todos sus recursos y datos serán eliminados permanentemente. Antes de eliminar tu cuenta, descarga cualquier información que desees conservar.
        </p>

        <button type="button" @click="showModal = true"
                class="btn bg-red-500 hover:bg-red-600 text-white">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
            Eliminar Cuenta
        </button>
    </div>

    <!-- Modal -->
    <div x-show="showModal" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         @keydown.escape.window="showModal = false">
        <!-- Overlay -->
        <div class="fixed inset-0 bg-black/50 transition-opacity"
             @click="showModal = false"
             x-show="showModal"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"></div>

        <!-- Modal Content -->
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6 animate-scaleIn"
                 x-show="showModal"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 transform scale-95"
                 x-transition:enter-end="opacity-100 transform scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 transform scale-100"
                 x-transition:leave-end="opacity-0 transform scale-95"
                 @click.stop>

                <form method="post" action="{{ route('profile.destroy') }}">
                    @csrf
                    @method('delete')

                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">¿Eliminar tu cuenta?</h3>
                            <p class="text-sm text-gray-600">Esta acción no se puede deshacer</p>
                        </div>
                    </div>

                    <p class="text-sm text-gray-600 mb-6">
                        Una vez que tu cuenta sea eliminada, todos sus recursos y datos serán eliminados permanentemente. Por favor, ingresa tu contraseña para confirmar que deseas eliminar tu cuenta de forma permanente.
                    </p>

                    <div class="mb-6">
                        <label for="delete_password" class="block text-sm font-medium text-gray-700 mb-2">
                            Contraseña
                        </label>
                        <input type="password" id="delete_password" name="password"
                               placeholder="Ingresa tu contraseña"
                               class="w-full @error('password', 'userDeletion') border-red-500 @enderror">
                        @error('password', 'userDeletion')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center gap-3 justify-end">
                        <button type="button" @click="showModal = false"
                                class="btn btn-secondary">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="btn bg-red-500 hover:bg-red-600 text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Eliminar Cuenta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
