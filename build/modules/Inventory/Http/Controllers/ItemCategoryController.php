<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Modules\Inventory\Http\Requests\StoreItemCategoryRequest;
use App\Modules\Inventory\Http\Requests\UpdateItemCategoryRequest;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ItemCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $query = ItemCategory::where('company_id', $company->id)
            ->withCount('items')
            ->with('parent:id,name,code');

        if ($request->has('search') && $request->search) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', "%{$term}%")
                    ->orWhere('code', 'ilike', "%{$term}%");
            });
        }

        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        $query->orderBy('sort_order')->orderBy('name');

        $categories = $query->paginate(50)->withQueryString();

        return Inertia::render('inventory/categories/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'categories' => $categories,
            'filters' => [
                'search' => $request->search ?? '',
                'include_inactive' => $request->boolean('include_inactive'),
            ],
        ]);
    }

    public function create(): Response
    {
        $company = CompanyContext::getCompany();

        $parentCategories = ItemCategory::where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNull('parent_id') // Only allow 1 level deep for simplicity
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return Inertia::render('inventory/categories/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'parentCategories' => $parentCategories,
        ]);
    }

    public function store(StoreItemCategoryRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $category = ItemCategory::create(array_merge($request->validated(), [
            'company_id' => $company->id,
            'created_by_user_id' => $request->user()->id,
        ]));

        return redirect()
            ->route('item-categories.index', ['company' => $company->slug])
            ->with('success', 'Category created successfully.');
    }

    public function show(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $categoryId = $request->route('item_category');
        $category = ItemCategory::where('company_id', $company->id)
            ->with('parent:id,name,code')
            ->withCount('items')
            ->findOrFail($categoryId);

        $items = Item::where('category_id', $category->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'sku', 'name', 'item_type', 'selling_price', 'currency']);

        $children = ItemCategory::where('parent_id', $category->id)
            ->withCount('items')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('inventory/categories/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'category' => $category,
            'items' => $items,
            'children' => $children,
        ]);
    }

    public function edit(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $categoryId = $request->route('item_category');
        $category = ItemCategory::where('company_id', $company->id)->findOrFail($categoryId);

        $parentCategories = ItemCategory::where('company_id', $company->id)
            ->where('is_active', true)
            ->where('id', '!=', $category->id)
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return Inertia::render('inventory/categories/Edit', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'category' => $category,
            'parentCategories' => $parentCategories,
        ]);
    }

    public function update(UpdateItemCategoryRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $categoryId = $request->route('item_category');
        $category = ItemCategory::where('company_id', $company->id)->findOrFail($categoryId);

        $category->update($request->validated());

        return redirect()
            ->route('item-categories.index', ['company' => $company->slug])
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $categoryId = $request->route('item_category');
        $category = ItemCategory::where('company_id', $company->id)->findOrFail($categoryId);

        // Check if category has items
        $hasItems = Item::where('category_id', $category->id)->exists();

        if ($hasItems) {
            return back()->with('error', 'Cannot delete category with items. Reassign items first.');
        }

        // Check if category has children
        $hasChildren = ItemCategory::where('parent_id', $category->id)->exists();

        if ($hasChildren) {
            return back()->with('error', 'Cannot delete category with subcategories.');
        }

        $category->delete();

        return redirect()
            ->route('item-categories.index', ['company' => $company->slug])
            ->with('success', 'Category deleted successfully.');
    }

    public function search(Request $request): JsonResponse
    {
        $company = CompanyContext::getCompany();
        $query = $request->get('q', '');
        $limit = min((int) $request->get('limit', 10), 50);

        $categories = ItemCategory::where('company_id', $company->id)
            ->where('is_active', true)
            ->when(strlen($query) >= 2, function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->where('name', 'ilike', "%{$query}%")
                        ->orWhere('code', 'ilike', "%{$query}%");
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'code', 'name']);

        return response()->json(['results' => $categories]);
    }
}
