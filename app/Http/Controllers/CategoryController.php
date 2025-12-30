<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreCategoryRequest;
use App\Http\Requests\Product\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:manage-categories');
    }

    public function index(Request $request)
    {
        $query = Category::query();

        // Search
        if ($request->has('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        // Filter by parent
        if ($request->has('parent_id')) {
            if ($request->parent_id === 'null') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $request->parent_id);
            }
        }

        $categories = $query->with('parent')
            ->orderBy('parent_id')
            ->orderBy('position')
            ->paginate(20);

        $parentCategories = Category::whereNull('parent_id')->get();

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
            'parent_categories' => $parentCategories,
            'filters' => $request->only(['search', 'parent_id']),
        ]);
    }

    public function create()
    {
        $parentCategories = Category::whereNull('parent_id')->get();

        return Inertia::render('Categories/Create', [
            'parent_categories' => $parentCategories,
        ]);
    }

    public function store(StoreCategoryRequest $request)
    {
        try {
            Category::create($request->validated());

            return redirect()->route('categories.index')
                ->with('success', 'Kategori berhasil ditambahkan');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal menambahkan kategori: ' . $e->getMessage());
        }
    }

    public function edit(Category $category)
    {
        $parentCategories = Category::whereNull('parent_id')
            ->where('id', '!=', $category->id)
            ->get();

        return Inertia::render('Categories/Edit', [
            'category' => $category,
            'parent_categories' => $parentCategories,
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        try {
            // Prevent circular reference
            if ($request->parent_id == $category->id) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Kategori tidak dapat menjadi parent dari dirinya sendiri');
            }

            $category->update($request->validated());

            return redirect()->route('categories.index')
                ->with('success', 'Kategori berhasil diperbarui');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal memperbarui kategori: ' . $e->getMessage());
        }
    }

    public function destroy(Category $category)
    {
        try {
            // Check if category has products
            if ($category->products()->exists()) {
                return redirect()->back()
                    ->with('error', 'Kategori tidak dapat dihapus karena memiliki produk terkait');
            }

            // Check if category has subcategories
            if ($category->children()->exists()) {
                return redirect()->back()
                    ->with('error', 'Kategori tidak dapat dihapus karena memiliki subkategori');
            }

            $category->delete();

            return redirect()->route('categories.index')
                ->with('success', 'Kategori berhasil dihapus');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Gagal menghapus kategori: ' . $e->getMessage());
        }
    }
}
