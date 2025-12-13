<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExpenseCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = ExpenseCategory::withCount('expenses')->orderBy('nombre')->get();
        return Inertia::render('Categories/Index', [
            'categories' => $categories,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Categories/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255|unique:expense_categories,nombre',
            'icono' => 'required|string|max:50',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        ExpenseCategory::create($validated);

        return redirect()->route('categories.index')
            ->with('success', 'Categoría creada exitosamente');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ExpenseCategory $category)
    {
        return Inertia::render('Categories/Edit', [
            'category' => $category->load('expenses')->loadCount('expenses'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ExpenseCategory $category)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255|unique:expense_categories,nombre,' . $category->id,
            'icono' => 'required|string|max:50',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $category->update($validated);

        return redirect()->route('categories.index')
            ->with('success', 'Categoría actualizada exitosamente');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ExpenseCategory $category)
    {
        // Verificar si hay gastos usando esta categoría
        if ($category->expenses()->count() > 0) {
            return redirect()->route('categories.index')
                ->with('error', 'No se puede eliminar esta categoría porque tiene gastos asociados');
        }

        $category->delete();

        return redirect()->route('categories.index')
            ->with('success', 'Categoría eliminada exitosamente');
    }
}
