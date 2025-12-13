<x-modern>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Categorías de Gastos</h1>
                <p class="mt-1 text-sm text-gray-600">Gestiona las categorías para organizar tus gastos</p>
            </div>
            <a href="{{ route('categories.create') }}" class="btn btn-primary inline-flex items-center gap-2 shadow-md hover:shadow-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Nueva Categoría
            </a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if(session('success'))
            <div class="mb-6">
                <x-alert type="success">
                    {{ session('success') }}
                </x-alert>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6">
                <x-alert type="danger">
                    {{ session('error') }}
                </x-alert>
            </div>
        @endif

        @if($categories->isEmpty())
            <!-- Empty state -->
            <div class="text-center py-20">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full gradient-primary mb-6">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No hay categorías aún</h3>
                <p class="text-gray-600 mb-8 max-w-md mx-auto">
                    Crea tu primera categoría para organizar mejor tus gastos
                </p>
                <a href="{{ route('categories.create') }}" class="btn btn-primary inline-flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Crear Primera Categoría
                </a>
            </div>
        @else
            <!-- Grid de categorías -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($categories as $category)
                    <article class="card group">
                        <!-- Header -->
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center transition-transform group-hover:scale-110"
                                     style="background-color: {{ $category->color }}20;">
                                    <x-category-icon :name="$category->icono" :color="$category->color" class="w-6 h-6" />
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">{{ $category->nombre }}</h3>
                                    <p class="text-sm text-gray-500">{{ $category->expenses->count() }} gastos</p>
                                </div>
                            </div>
                        </div>

                        <!-- Color badge -->
                        <div class="mb-4 flex items-center gap-2">
                            <div class="w-6 h-6 rounded-lg border-2 border-gray-200" style="background-color: {{ $category->color }}"></div>
                            <span class="text-xs font-mono text-gray-500">{{ $category->color }}</span>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2 pt-4 border-t border-gray-100">
                            <a href="{{ route('categories.edit', $category) }}"
                               class="flex-1 btn btn-secondary text-sm py-2">
                                <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Editar
                            </a>
                            <form method="POST" action="{{ route('categories.destroy', $category) }}" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        onclick="return confirm('¿Eliminar esta categoría?')"
                                        class="btn bg-red-500 hover:bg-red-600 text-white text-sm py-2 px-4">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-modern>
