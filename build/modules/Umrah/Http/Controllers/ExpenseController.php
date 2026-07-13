<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Bill;
use App\Services\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        $isMember = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $request->user()?->id)
            ->where('is_active', true)
            ->value('role') === 'member';
        abort_if($isMember, 403, 'Agent logins cannot view company expenses.');

        $query = Bill::where('company_id', $company->id)
            ->with('vendor:id,name')
            ->when($request->filled('status'), fn ($billQuery) => $billQuery->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($billQuery) use ($request) {
                $term = $request->string('search')->toString();
                $billQuery->where(function ($searchQuery) use ($term) {
                    $searchQuery->where('bill_number', 'ilike', "%{$term}%")
                        ->orWhere('vendor_invoice_number', 'ilike', "%{$term}%")
                        ->orWhereHas('vendor', fn ($vendorQuery) => $vendorQuery->where('name', 'ilike', "%{$term}%"));
                });
            });

        $summaryQuery = clone $query;

        return Inertia::render('Umrah/Expenses/Index', [
            'company' => ['name' => $company->name, 'slug' => $company->slug, 'base_currency' => $company->base_currency],
            'expenses' => $query->orderByDesc('bill_date')->orderByDesc('created_at')->paginate(25)->withQueryString(),
            'summary' => [
                'total' => (float) (clone $summaryQuery)->whereNotIn('status', ['void', 'cancelled'])->sum('base_amount'),
                'outstanding' => (float) (clone $summaryQuery)->whereNotIn('status', ['paid', 'void', 'cancelled'])->selectRaw('COALESCE(SUM(balance * COALESCE(exchange_rate, 1)), 0) AS aggregate')->value('aggregate'),
            ],
            'filters' => $request->only(['search', 'status']),
        ]);
    }
}
