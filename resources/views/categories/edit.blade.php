<x-modern>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="{{ route('categories.index') }}" class="p-2 rounded-lg hover:bg-gray-100 transition">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Editar Categoría</h1>
                    <p class="mt-1 text-sm text-gray-600">{{ $category->nombre }}</p>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <form method="POST" action="{{ route('categories.update', $category) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="card">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-lg bg-primary-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Información de la Categoría</h2>
                        <p class="text-sm text-gray-600">Modifica el nombre, ícono y color</p>
                    </div>
                </div>

                <div class="space-y-5">
                    <!-- Nombre -->
                    <div>
                        <label for="nombre" class="block text-sm font-medium text-gray-700 mb-2">
                            Nombre <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nombre" id="nombre" required
                            value="{{ old('nombre', $category->nombre) }}"
                            class="w-full @error('nombre') border-red-500 @enderror"
                            placeholder="Ej: Transporte">
                        @error('nombre')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Ícono -->
                    <div>
                        <label for="icono" class="block text-sm font-medium text-gray-700 mb-2">
                            Ícono <span class="text-red-500">*</span>
                        </label>
                        <select name="icono" id="icono" required
                                class="w-full @error('icono') border-red-500 @enderror"
                                onchange="updatePreview()">
                            <option value="">Selecciona un ícono</option>
                            <option value="truck" {{ old('icono', $category->icono) === 'truck' ? 'selected' : '' }}>Camión (Transporte)</option>
                            <option value="home" {{ old('icono', $category->icono) === 'home' ? 'selected' : '' }}>Casa (Alojamiento)</option>
                            <option value="cake" {{ old('icono', $category->icono) === 'cake' ? 'selected' : '' }}>Torta (Alimentación)</option>
                            <option value="paper-airplane" {{ old('icono', $category->icono) === 'paper-airplane' ? 'selected' : '' }}>Avión (Pasajes)</option>
                            <option value="location-marker" {{ old('icono', $category->icono) === 'location-marker' ? 'selected' : '' }}>Ubicación (Taxi)</option>
                            <option value="cash" {{ old('icono', $category->icono) === 'cash' ? 'selected' : '' }}>Dinero (Peajes)</option>
                            <option value="lightning-bolt" {{ old('icono', $category->icono) === 'lightning-bolt' ? 'selected' : '' }}>Rayo (Combustible)</option>
                            <option value="dots-horizontal" {{ old('icono', $category->icono) === 'dots-horizontal' ? 'selected' : '' }}>Puntos (Otros)</option>
                        </select>
                        @error('icono')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Color -->
                    <div>
                        <label for="color" class="block text-sm font-medium text-gray-700 mb-2">
                            Color <span class="text-red-500">*</span>
                        </label>
                        <div class="flex gap-3">
                            <input type="color" name="color" id="color" required
                                value="{{ old('color', $category->color) }}"
                                class="h-12 w-20 rounded-lg border border-gray-300 cursor-pointer @error('color') border-red-500 @enderror"
                                onchange="updatePreview()">
                            <input type="text" id="color-text"
                                value="{{ old('color', $category->color) }}"
                                class="flex-1 font-mono"
                                placeholder="#000000"
                                pattern="^#[0-9A-Fa-f]{6}$"
                                maxlength="7"
                                oninput="updateColorPicker(this.value)">
                        </div>
                        @error('color')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Vista previa -->
                    <div class="pt-4 border-t border-gray-200">
                        <label class="block text-sm font-medium text-gray-700 mb-3">
                            Vista Previa
                        </label>
                        <div id="preview" class="inline-flex items-center gap-3 px-4 py-3 rounded-xl border-2 border-gray-200">
                            <div id="preview-bg" class="w-12 h-12 rounded-xl flex items-center justify-center" style="background-color: {{ $category->color }}20;">
                                <svg id="preview-icon" class="w-6 h-6" fill="none" stroke="{{ $category->color }}" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z" />
                                </svg>
                            </div>
                            <span id="preview-text" class="text-lg font-semibold text-gray-900">{{ $category->nombre }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botones de acción -->
            <div class="flex items-center justify-end gap-3 pt-4">
                <a href="{{ route('categories.index') }}" class="btn btn-secondary">
                    Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Guardar Cambios
                </button>
            </div>
        </form>
    </div>

    <script>
        const icons = {
            'truck': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h2a1 1 0 001-1m0 0h1a1 1 0 001-1v-3a1 1 0 00-.293-.707l-2-2A1 1 0 0017 8h-1a1 1 0 00-1 1v10" />',
            'home': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />',
            'cake': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.701 2.701 0 00-1.5-.454M9 6v2m3-2v2m3-2v2M9 3h.01M12 3h.01M15 3h.01M21 21v-7a2 2 0 00-2-2H5a2 2 0 00-2 2v7h18zm-3-9v-2a2 2 0 00-2-2H8a2 2 0 00-2 2v2h12z" />',
            'paper-airplane': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />',
            'location-marker': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />',
            'cash': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />',
            'lightning-bolt': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />',
            'dots-horizontal': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z" />',
        };

        function updatePreview() {
            const nombre = document.getElementById('nombre').value || 'Nombre de Categoría';
            const icono = document.getElementById('icono').value;
            const color = document.getElementById('color').value;

            document.getElementById('preview-text').textContent = nombre;
            document.getElementById('preview-icon').setAttribute('stroke', color);
            document.getElementById('preview-bg').style.backgroundColor = color + '20';

            if (icono && icons[icono]) {
                document.getElementById('preview-icon').innerHTML = icons[icono];
            }
        }

        function updateColorPicker(value) {
            if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
                document.getElementById('color').value = value;
                updatePreview();
            }
        }

        // Sincronizar input de color con texto
        document.getElementById('color').addEventListener('change', function() {
            document.getElementById('color-text').value = this.value;
            updatePreview();
        });

        // Actualizar preview cuando cambia el nombre
        document.getElementById('nombre').addEventListener('input', updatePreview);

        // Preview inicial
        updatePreview();
    </script>
</x-modern>
