<x-modern>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('companies.index') }}" class="p-2 rounded-lg hover:bg-gray-100 transition">
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Nueva Empresa</h1>
                <p class="mt-1 text-sm text-gray-600">Crea una nueva empresa para organizar tus importaciones</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <form method="POST" action="{{ route('companies.store') }}" class="space-y-6">
            @csrf

            <!-- Información General -->
            <div class="card">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-lg bg-primary-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Información de la Empresa</h2>
                        <p class="text-sm text-gray-600">Datos básicos de identificación</p>
                    </div>
                </div>

                <div class="space-y-5">
                    <!-- Nombre -->
                    <div>
                        <label for="nombre" class="block text-sm font-medium text-gray-700 mb-2">
                            Nombre de la Empresa <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nombre" id="nombre" required
                            value="{{ old('nombre') }}"
                            class="w-full"
                            placeholder="Ej: Importadora Chile SpA">
                        @error('nombre')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- RUT -->
                    <div>
                        <label for="rut" class="block text-sm font-medium text-gray-700 mb-2">
                            RUT <span class="text-gray-400">(Opcional)</span>
                        </label>
                        <input type="text" name="rut" id="rut"
                            value="{{ old('rut') }}"
                            class="w-full"
                            placeholder="Ej: 76.123.456-7">
                        @error('rut')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Descripción -->
                    <div>
                        <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-2">
                            Descripción <span class="text-gray-400">(Opcional)</span>
                        </label>
                        <textarea name="descripcion" id="descripcion" rows="3"
                            class="w-full resize-none"
                            placeholder="Describe la actividad o propósito de esta empresa...">{{ old('descripcion') }}</textarea>
                        @error('descripcion')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Personalización Visual -->
            <div class="card">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Personalización Visual</h2>
                        <p class="text-sm text-gray-600">Define cómo se verá tu empresa en la interfaz</p>
                    </div>
                </div>

                <div class="space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Color -->
                        <div>
                            <label for="color" class="block text-sm font-medium text-gray-700 mb-2">
                                Color Identificador <span class="text-red-500">*</span>
                            </label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="color" id="color" required
                                    value="{{ old('color', '#3B82F6') }}"
                                    class="h-12 w-20 rounded-lg border border-gray-300 cursor-pointer">
                                <div class="flex-1">
                                    <input type="text" id="color-hex"
                                        value="{{ old('color', '#3B82F6') }}"
                                        class="w-full font-mono text-sm"
                                        placeholder="#3B82F6"
                                        readonly>
                                </div>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Este color se usará para identificar visualmente la empresa</p>
                            @error('color')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Ícono SVG -->
                        <div>
                            <label for="icono" class="block text-sm font-medium text-gray-700 mb-2">
                                Ícono <span class="text-gray-400">(Opcional)</span>
                            </label>
                            <select name="icono" id="icono" class="w-full">
                                <option value="building" {{ old('icono') === 'building' ? 'selected' : '' }}>Edificio</option>
                                <option value="office" {{ old('icono') === 'office' ? 'selected' : '' }}>Oficina</option>
                                <option value="store" {{ old('icono') === 'store' ? 'selected' : '' }}>Tienda</option>
                                <option value="factory" {{ old('icono') === 'factory' ? 'selected' : '' }}>Fábrica</option>
                                <option value="warehouse" {{ old('icono') === 'warehouse' ? 'selected' : '' }}>Bodega</option>
                                <option value="truck" {{ old('icono') === 'truck' ? 'selected' : '' }}>Transporte</option>
                                <option value="globe" {{ old('icono') === 'globe' ? 'selected' : '' }}>Internacional</option>
                                <option value="chart" {{ old('icono') === 'chart' ? 'selected' : '' }}>Analítica</option>
                                <option value="briefcase" {{ old('icono') === 'briefcase' ? 'selected' : '' }}>Negocios</option>
                                <option value="home" {{ old('icono') === 'home' ? 'selected' : '' }}>Casa</option>
                                <option value="tag" {{ old('icono') === 'tag' ? 'selected' : '' }}>Etiqueta</option>
                                <option value="shopping-bag" {{ old('icono') === 'shopping-bag' ? 'selected' : '' }}>Compras</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Selecciona un ícono para identificar la empresa</p>
                            @error('icono')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Preview -->
                    <div class="p-4 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300">
                        <p class="text-xs text-gray-500 mb-2">Vista previa:</p>
                        <div class="inline-flex items-center gap-3 px-4 py-2 bg-white rounded-lg border-l-4" id="preview-card" style="border-left-color: {{ old('color', '#3B82F6') }}">
                            <div id="preview-icon" style="color: {{ old('color', '#3B82F6') }}">
                                <x-company-icon :icon="old('icono', 'building')" class="w-6 h-6" />
                            </div>
                            <span class="font-semibold text-gray-900" id="preview-name">{{ old('nombre', 'Nombre de la Empresa') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Colores sugeridos -->
            <div class="card">
                <h3 class="text-sm font-medium text-gray-700 mb-3">Colores sugeridos:</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach(['#3B82F6' => 'Azul', '#10B981' => 'Verde', '#F59E0B' => 'Naranja', '#EF4444' => 'Rojo', '#8B5CF6' => 'Morado', '#EC4899' => 'Rosa', '#06B6D4' => 'Cyan', '#84CC16' => 'Lima'] as $colorCode => $colorName)
                        <button type="button"
                                onclick="document.getElementById('color').value='{{ $colorCode }}'; document.getElementById('color-hex').value='{{ $colorCode }}'; updatePreview();"
                                class="w-10 h-10 rounded-lg border-2 border-gray-300 hover:border-gray-400 transition"
                                style="background-color: {{ $colorCode }}"
                                title="{{ $colorName }}">
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Botones de acción -->
            <div class="flex items-center justify-end gap-3 pt-4">
                <a href="{{ route('companies.index') }}" class="btn btn-secondary">
                    Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Crear Empresa
                </button>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        const iconPaths = {
            'building': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />',
            'office': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />',
            'store': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />',
            'factory': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />',
            'warehouse': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z" />',
            'truck': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />',
            'globe': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
            'chart': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />',
            'briefcase': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />',
            'home': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />',
            'tag': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />',
            'shopping-bag': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />',
        };

        // Sincronizar color picker con input de texto
        document.getElementById('color').addEventListener('input', function() {
            document.getElementById('color-hex').value = this.value;
            updatePreview();
        });

        // Actualizar preview en tiempo real
        document.getElementById('nombre').addEventListener('input', updatePreview);
        document.getElementById('icono').addEventListener('change', updatePreview);

        function updatePreview() {
            const nombre = document.getElementById('nombre').value || 'Nombre de la Empresa';
            const iconName = document.getElementById('icono').value || 'building';
            const color = document.getElementById('color').value || '#3B82F6';

            document.getElementById('preview-name').textContent = nombre;
            document.getElementById('preview-card').style.borderLeftColor = color;

            // Actualizar ícono SVG
            const iconContainer = document.getElementById('preview-icon');
            iconContainer.style.color = color;
            iconContainer.innerHTML = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">' + iconPaths[iconName] + '</svg>';
        }
    </script>
    @endpush
</x-modern>
