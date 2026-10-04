<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = ExpenseCategory::visibleTo($request->user()->id)
            ->withCount(['expenses' => fn ($q) => $q->whereHas('trip', fn ($t) => $t->where('user_id', $request->user()->id))])
            ->orderBy('nombre')->get()->map(fn ($c) => [...$c->toArray(), 'editable' => $c->user_id === $request->user()->id]);

        return Inertia::render('Categories/Index', ['categories' => $categories]);
    }

    public function create()
    {
        return Inertia::render('Categories/Create');
    }

    public function store(ExpenseCategoryRequest $request)
    {
        ExpenseCategory::create([...$request->validated(), 'user_id' => $request->user()->id]);

        return to_route('categories.index')->with('success', 'Categoría personal creada');
    }

    public function edit(Request $request, ExpenseCategory $category)
    {
        $this->authorize('update', $category);
        $category->loadCount(['expenses' => fn ($q) => $q->whereHas('trip', fn ($t) => $t->where('user_id', $request->user()->id))]);

        return Inertia::render('Categories/Edit', ['category' => $category]);
    }

    public function update(ExpenseCategoryRequest $request, ExpenseCategory $category)
    {
        $category->update($request->validated());

        return to_route('categories.index')->with('success', 'Categoría actualizada');
    }

    public function destroy(ExpenseCategory $category)
    {
        $this->authorize('delete', $category);
        if ($category->expenses()->exists()) {
            return to_route('categories.index')->with('error', 'Reasigna los gastos antes de eliminar su categoría.');
        }
        $category->delete();

        return to_route('categories.index')->with('success', 'Categoría eliminada');
    }
}
