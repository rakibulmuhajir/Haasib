<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StorePostingTemplateRequest;
use App\Modules\Accounting\Http\Requests\UpdatePostingTemplateRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\PostingTemplate;
use App\Modules\Accounting\Models\PostingTemplateLine;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Accounting\Services\PostingTemplateValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PostingTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $company = CompanyContext::requireCompany();

        if (! $request->user()?->hasCompanyPermission(Permissions::POSTING_TEMPLATE_VIEW)) {
            abort(403);
        }

        $templates = PostingTemplate::where('company_id', $company->id)
            ->whereNull('deleted_at')
            ->with(['lines.account'])
            ->orderBy('doc_type')
            ->orderByDesc('is_default')
            ->orderByDesc('effective_from')
            ->get();

        return Inertia::render('accounting/posting-templates/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'templates' => $templates,
        ]);
    }

    public function create(Request $request): Response
    {
        $company = CompanyContext::requireCompany();

        if (! $request->user()?->hasCompanyPermission(Permissions::POSTING_TEMPLATE_CREATE)) {
            abort(403);
        }

        try {
            DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
            if (!empty($company->base_currency)) {
                DB::select("SELECT set_config('app.company_base_currency', ?, false)", [$company->base_currency]);
            }
        } catch (\Throwable $e) {
            // If session config fails, queries will still use explicit company filters.
        }

        $accounts = Account::where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'subtype']);
        $discountReceivedAccountId = Account::where('company_id', $company->id)
            ->whereIn('type', ['other_income', 'revenue'])
            ->where(function ($q) {
                $q->where('name', 'Discounts Received')
                    ->orWhere('name', 'Purchase Discounts')
                    ->orWhere('code', '4300');
            })
            ->value('id');

        return Inertia::render('accounting/posting-templates/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'accounts' => $accounts,
            'defaults' => [
                'ar_account_id' => $company->ar_account_id,
                'ap_account_id' => $company->ap_account_id,
                'income_account_id' => $company->income_account_id,
                'expense_account_id' => $company->expense_account_id,
                'bank_account_id' => $company->bank_account_id,
                'sales_tax_payable_account_id' => $company->sales_tax_payable_account_id,
                'purchase_tax_receivable_account_id' => $company->purchase_tax_receivable_account_id,
                'discount_received_account_id' => $discountReceivedAccountId,
            ],
        ]);
    }

    public function store(StorePostingTemplateRequest $request): RedirectResponse
    {
        $company = CompanyContext::requireCompany();
        $data = $request->validated();

        app(PostingTemplateValidator::class)->validateForSave($company->id, $data['doc_type'], $data['lines']);

        $template = PostingTemplate::create([
            'company_id' => $company->id,
            'doc_type' => $data['doc_type'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'is_default' => $data['is_default'] ?? false,
            'version' => 1,
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'created_by_user_id' => $request->user()?->id,
            'updated_by_user_id' => $request->user()?->id,
        ]);

        if ($template->is_default) {
            PostingTemplate::where('company_id', $company->id)
                ->where('doc_type', $template->doc_type)
                ->where('id', '!=', $template->id)
                ->update(['is_default' => false]);
        }

        foreach ($data['lines'] as $index => $line) {
            if (empty($line['account_id'])) {
                continue;
            }

            PostingTemplateLine::updateOrCreate(
                ['template_id' => $template->id, 'role' => $line['role']],
                [
                    'account_id' => $line['account_id'],
                    'description' => null,
                    'precedence' => $index + 1,
                    'is_required' => true,
                ]
            );
        }

        return redirect()
            ->route('posting-templates.edit', ['company' => $company->slug, 'posting_template' => $template->id])
            ->with('success', 'Posting template created.');
    }

    public function edit(Request $request): Response
    {
        $company = CompanyContext::requireCompany();

        if (! $request->user()?->hasCompanyPermission(Permissions::POSTING_TEMPLATE_VIEW)) {
            abort(403);
        }

        try {
            DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
            if (!empty($company->base_currency)) {
                DB::select("SELECT set_config('app.company_base_currency', ?, false)", [$company->base_currency]);
            }
        } catch (\Throwable $e) {
            // If session config fails, queries will still use explicit company filters.
        }

        $templateId = $request->route('posting_template');
        $template = PostingTemplate::where('company_id', $company->id)
            ->where('id', $templateId)
            ->with(['lines'])
            ->firstOrFail();

        $accounts = Account::where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'subtype']);
        $discountReceivedAccountId = Account::where('company_id', $company->id)
            ->whereIn('type', ['other_income', 'revenue'])
            ->where(function ($q) {
                $q->where('name', 'Discounts Received')
                    ->orWhere('name', 'Purchase Discounts')
                    ->orWhere('code', '4300');
            })
            ->value('id');

        $preview = null;
        $previewId = $request->query('preview_id');
        if ($previewId) {
            $posting = app(PostingService::class);
            $preview = match ($template->doc_type) {
                'AR_INVOICE' => $this->previewInvoice($company->id, $template, (string) $previewId, $posting),
                'AP_BILL' => $this->previewBill($company->id, $template, (string) $previewId, $posting),
                default => null,
            };
        }

        return Inertia::render('accounting/posting-templates/Edit', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'template' => $template,
            'accounts' => $accounts,
            'preview' => $preview,
            'defaults' => [
                'ar_account_id' => $company->ar_account_id,
                'ap_account_id' => $company->ap_account_id,
                'income_account_id' => $company->income_account_id,
                'expense_account_id' => $company->expense_account_id,
                'bank_account_id' => $company->bank_account_id,
                'sales_tax_payable_account_id' => $company->sales_tax_payable_account_id,
                'purchase_tax_receivable_account_id' => $company->purchase_tax_receivable_account_id,
                'discount_received_account_id' => $discountReceivedAccountId,
            ],
        ]);
    }

    public function update(UpdatePostingTemplateRequest $request): RedirectResponse
    {
        $company = CompanyContext::requireCompany();

        $templateId = $request->route('posting_template');
        $template = PostingTemplate::where('company_id', $company->id)
            ->where('id', $templateId)
            ->with(['lines'])
            ->firstOrFail();

        $data = $request->validated();

        app(PostingTemplateValidator::class)->validateForSave($company->id, $template->doc_type, $data['lines']);

        $template->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'is_default' => $data['is_default'] ?? false,
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'updated_by_user_id' => $request->user()?->id,
        ]);

        if ($template->is_default) {
            PostingTemplate::where('company_id', $company->id)
                ->where('doc_type', $template->doc_type)
                ->where('id', '!=', $template->id)
                ->update(['is_default' => false]);
        }

        $seenRoles = [];
        foreach ($data['lines'] as $index => $line) {
            $role = $line['role'];
            $seenRoles[] = $role;

            if (empty($line['account_id'])) {
                PostingTemplateLine::where('template_id', $template->id)->where('role', $role)->delete();
                continue;
            }

            PostingTemplateLine::updateOrCreate(
                ['template_id' => $template->id, 'role' => $role],
                [
                    'account_id' => $line['account_id'],
                    'description' => null,
                    'precedence' => $index + 1,
                    'is_required' => true,
                ]
            );
        }

        PostingTemplateLine::where('template_id', $template->id)
            ->whereNotIn('role', $seenRoles)
            ->delete();

        return back()->with('success', 'Posting template updated.');
    }

    private function previewInvoice(string $companyId, PostingTemplate $template, string $invoiceId, PostingService $posting): ?array
    {
        $invoice = Invoice::where('company_id', $companyId)->with(['customer', 'lineItems', 'company'])->find($invoiceId);
        if (! $invoice) return null;

        $entries = $posting->previewInvoice($template, $invoice);
        $accountsById = Account::where('company_id', $companyId)
            ->whereIn('id', array_values(array_unique(array_column($entries, 'account_id'))))
            ->get(['id', 'code', 'name', 'type', 'subtype'])
            ->keyBy('id');

        return [
            'transaction' => [
                'type' => 'AR_INVOICE',
                'number' => $invoice->invoice_number,
                'date' => $invoice->invoice_date,
                'currency' => $invoice->currency,
                'total' => $invoice->total_amount,
                'tax' => $invoice->tax_amount,
                'discount' => $invoice->discount_amount,
            ],
            'entries' => array_map(function ($entry) use ($accountsById) {
                $account = $accountsById[$entry['account_id']] ?? null;
                return [
                    ...$entry,
                    'account' => $account ? [
                        'id' => $account->id,
                        'code' => $account->code,
                        'name' => $account->name,
                        'type' => $account->type,
                        'subtype' => $account->subtype,
                    ] : null,
                ];
            }, $entries),
        ];
    }

    private function previewBill(string $companyId, PostingTemplate $template, string $billId, PostingService $posting): ?array
    {
        $bill = Bill::where('company_id', $companyId)->with(['vendor', 'lineItems', 'company'])->find($billId);
        if (! $bill) return null;

        $entries = $posting->previewBill($template, $bill);
        $accountsById = Account::where('company_id', $companyId)
            ->whereIn('id', array_values(array_unique(array_column($entries, 'account_id'))))
            ->get(['id', 'code', 'name', 'type', 'subtype'])
            ->keyBy('id');

        return [
            'transaction' => [
                'type' => 'AP_BILL',
                'number' => $bill->bill_number,
                'date' => $bill->bill_date,
                'currency' => $bill->currency,
                'total' => $bill->total_amount,
                'tax' => $bill->tax_amount,
                'discount' => $bill->discount_amount,
            ],
            'entries' => array_map(function ($entry) use ($accountsById) {
                $account = $accountsById[$entry['account_id']] ?? null;
                return [
                    ...$entry,
                    'account' => $account ? [
                        'id' => $account->id,
                        'code' => $account->code,
                        'name' => $account->name,
                        'type' => $account->type,
                        'subtype' => $account->subtype,
                    ] : null,
                ];
            }, $entries),
        ];
    }
}
