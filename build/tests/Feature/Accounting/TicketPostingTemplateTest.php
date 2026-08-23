<?php

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\PostingTemplate;
use App\Modules\Accounting\Models\PostingTemplateLine;
use Illuminate\Support\Facades\DB;

/**
 * acct.posting_template_lines has no company_id column of its own -- its RLS
 * policy joins to acct.posting_templates for that -- and its foreign key to
 * the template is `template_id`, not `posting_template_id`. Both corrections
 * to the plan's assumed schema.
 */
function ticketPostingTemplateCompany(): Company
{
    $company = Company::create([
        'name' => 'Ticket Posting Template Co '.str()->random(8),
        'slug' => 'ticket-posting-template-'.str()->lower(str()->random(10)),
        'base_currency' => 'USD',
    ]);

    if (! DB::table('public.currencies')->where('code', 'USD')->exists()) {
        DB::table('public.currencies')->insert(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);
    }

    return $company;
}

function ticketTemplateAccount(Company $company, string $code): Account
{
    return Account::where('company_id', $company->id)->where('code', $code)->first()
        ?? match ($code) {
            '1100' => Account::create([
                'company_id' => $company->id,
                'code' => '1100',
                'name' => 'Accounts Receivable',
                'type' => 'asset',
                'subtype' => 'accounts_receivable',
                'normal_balance' => 'debit',
            ]),
            '2350' => Account::create([
                'company_id' => $company->id,
                'code' => '2350',
                'name' => 'Ticket Supplier Clearing',
                'type' => 'liability',
                'subtype' => 'other_current_liability',
                'normal_balance' => 'credit',
                'currency' => 'USD',
            ]),
            '4130' => Account::create([
                'company_id' => $company->id,
                'code' => '4130',
                'name' => 'Ticket Commission Revenue',
                'type' => 'revenue',
                'subtype' => 'revenue',
                'normal_balance' => 'credit',
            ]),
            '4140' => Account::create([
                'company_id' => $company->id,
                'code' => '4140',
                'name' => 'Ticket Service Fee Revenue',
                'type' => 'revenue',
                'subtype' => 'revenue',
                'normal_balance' => 'credit',
            ]),
            '4150' => Account::create([
                'company_id' => $company->id,
                'code' => '4150',
                'name' => 'Ticket Discount',
                'type' => 'revenue',
                'subtype' => 'revenue',
                'normal_balance' => 'debit',
                'is_contra' => true,
            ]),
            '9900' => Account::create([
                'company_id' => $company->id,
                'code' => '9900',
                'name' => 'Rounding Differences',
                'type' => 'expense',
                'subtype' => 'expense',
                'normal_balance' => 'debit',
            ]),
            default => throw new \RuntimeException("no fixture for account {$code}"),
        };
}

it('accepts a TICKET_INVOICE template with all six roles', function () {
    $company = ticketPostingTemplateCompany();

    $template = PostingTemplate::create([
        'company_id' => $company->id,
        'doc_type' => 'TICKET_INVOICE',
        'name' => 'Ticket sale',
        'is_active' => true,
        'is_default' => true,
        'effective_from' => '2026-01-01',
        'version' => 1,
    ]);

    $roles = [
        'AR' => '1100',
        'CLEARING' => '2350',
        'REVENUE' => '4130',
        'SERVICE_FEE' => '4140',
        'DISCOUNT_GIVEN' => '4150',
        'ROUNDING' => '9900',
    ];

    foreach ($roles as $role => $code) {
        PostingTemplateLine::create([
            'template_id' => $template->id,
            'role' => $role,
            'account_id' => ticketTemplateAccount($company, $code)->id,
        ]);
    }

    expect($template->fresh()->lines)->toHaveCount(6);
});

it('rejects a doc type that is not on the list', function () {
    $company = ticketPostingTemplateCompany();

    expect(fn () => PostingTemplate::create([
        'company_id' => $company->id,
        'doc_type' => 'TICKET_NONSENSE',
        'name' => 'Bad',
        'is_active' => true,
        'is_default' => true,
        'effective_from' => '2026-01-01',
        'version' => 1,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
