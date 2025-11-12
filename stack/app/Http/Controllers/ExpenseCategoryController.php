<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Str;
use Inertia\Inertia;

class ExpenseCategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ExpenseCategory::where('company_id', Auth::user()->current_company_id)
            ->with(['parent', 'children'])
            ->withCount(['expenses', 'children']);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('code', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%");
            });
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        // Filter by active status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $categories = $query->ordered()->get();

        return Inertia::render('ExpenseCategories/Index', [
            'categories' => $categories,
            'filters' => $request->only(['search', 'type', 'is_active']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $categories = ExpenseCategory::where('company_id', Auth::user()->current_company_id)
            ->active()
            ->ordered()
            ->get(['id', 'name', 'full_path']);

        return Inertia::render('ExpenseCategories/Create', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type' => 'required|in:expense,reimbursement',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'parent_id' => 'nullable|exists:acct.expense_categories,id',
            'is_active' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            $category = ExpenseCategory::create([
                'id' => (string) Str::uuid(),
                'company_id' => Auth::user()->current_company_id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'type' => $validated['type'],
                'color' => $validated['color'] ?? null,
                'parent_id' => $validated['parent_id'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            DB::commit();

            return redirect()->route('expense-categories.show', $category)
                ->with('success', 'Expense category created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to create Expense Category: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(ExpenseCategory $expenseCategory)
    {
        // Ensure user can only view categories from their company
        if ($expenseCategory->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        $expenseCategory->load([
            'parent',
            'children' => function ($query) {
                $query->ordered();
            },
            'expenses' => function ($query) {
                $query->with(['employee', 'vendor'])
                    ->orderBy('expense_date', 'desc')
                    ->limit(10);
            },
        ]);

        return Inertia::render('ExpenseCategories/Show', [
            'category' => $expenseCategory,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ExpenseCategory $expenseCategory)
    {
        // Ensure user can only edit categories from their company
        if ($expenseCategory->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        $expenseCategory->load(['parent']);

        $categories = ExpenseCategory::where('company_id', Auth::user()->current_company_id)
            ->active()
            ->ordered()
            ->get(['id', 'name', 'full_path']);

        return Inertia::render('ExpenseCategories/Edit', [
            'category' => $expenseCategory,
            'categories' => $categories,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ExpenseCategory $expenseCategory)
    {
        // Ensure user can only update categories from their company
        if ($expenseCategory->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type' => 'required|in:expense,reimbursement',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'parent_id' => 'nullable|exists:acct.expense_categories,id',
            'is_active' => 'boolean',
        ]);

        // Prevent setting category as its own parent
        if ($validated['parent_id'] == $expenseCategory->id) {
            return back()->with('error', 'A category cannot be its own parent.')
                ->withInput();
        }

        try {
            DB::beginTransaction();

            $expenseCategory->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'type' => $validated['type'],
                'color' => $validated['color'] ?? null,
                'parent_id' => $validated['parent_id'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            DB::commit();

            return redirect()->route('expense-categories.show', $expenseCategory)
                ->with('success', 'Expense category updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to update Expense Category: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ExpenseCategory $expenseCategory)
    {
        // Ensure user can only delete categories from their company
        if ($expenseCategory->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        if (! $expenseCategory->canBeDeleted()) {
            return back()->with('error', 'This category cannot be deleted as it contains expenses or subcategories.');
        }

        try {
            DB::beginTransaction();

            $expenseCategory->delete();

            DB::commit();

            return redirect()->route('expense-categories.index')
                ->with('success', 'Expense category deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to delete Expense Category: '.$e->getMessage());
        }
    }
}
