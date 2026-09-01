<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Umrah\Models\VisaGroup;
use App\Services\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every change made to a group's figures after it was created.
 *
 * An adjustment is always about one trip, so making one starts from that
 * trip's own page. What had nowhere to live was the other direction: at
 * month end somebody wants every correction and renegotiation in one
 * place, and until now that could only be assembled by opening groups one
 * at a time and reading their ledgers.
 *
 * The amount comes off the adjustment's own journal entry rather than
 * being recomputed -- the receivable line for a change to what the agent
 * is charged, the payable line for a change to what the suppliers charge
 * us -- so this shows exactly what the ledger moved by.
 */
class AdjustmentController extends Controller
{
    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless((bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_GROUP_ACCOUNTING_VIEW), 403);

        $accountFor = fn (string $subtype) => Account::where('company_id', $company->id)
            ->where('subtype', $subtype)
            ->orderBy('code')
            ->value('id');

        $receivable = $accountFor('accounts_receivable') ?: $company->ar_account_id;
        $payable = $accountFor('accounts_payable') ?: $company->ap_account_id;

        $rows = DB::table('acct.transactions as t')
            ->join('umrah.visa_groups as g', 'g.id', '=', 't.reference_id')
            ->leftJoin('acct.journal_entries as j', function ($join) use ($receivable, $payable) {
                $join->on('j.transaction_id', '=', 't.id')
                    ->whereIn('j.account_id', array_filter([$receivable, $payable]));
            })
            ->where('t.company_id', $company->id)
            ->where('t.reference_type', 'umrah.visa_groups')
            ->whereIn('t.transaction_type', ['umrah_group_sale_adjustment', 'umrah_group_cost_adjustment'])
            ->groupBy('t.id', 't.transaction_type', 't.transaction_date', 't.posting_date', 't.description', 't.metadata', 'g.id', 'g.group_number', 'g.name')
            ->selectRaw('
                t.id,
                t.transaction_type,
                COALESCE(t.posting_date, t.transaction_date) as moved_on,
                t.metadata,
                g.id as group_id,
                g.group_number,
                g.name as group_name,
                SUM(j.debit_amount) - SUM(j.credit_amount) as delta
            ')
            ->orderByDesc('moved_on')
            ->orderByDesc('t.id')
            ->limit(200)
            ->get()
            ->map(function ($row) {
                $metadata = json_decode((string) $row->metadata, true) ?: [];
                $isSale = $row->transaction_type === 'umrah_group_sale_adjustment';

                return [
                    'id' => $row->id,
                    'date' => $row->moved_on,
                    'group_id' => $row->group_id,
                    'group' => $row->group_number,
                    'group_name' => $row->group_name,
                    // A sale adjustment moves the receivable; a cost one
                    // moves the payable, and a payable rising is a credit,
                    // so its sign has to be turned to read as a change.
                    'side' => $isSale ? 'charge' : 'cost',
                    'amount' => round((float) $row->delta * ($isSale ? 1 : -1), 2),
                    'reason' => $metadata['reason'] ?? null,
                    'reason_category' => $metadata['reason_category'] ?? null,
                ];
            })
            ->values();

        return Inertia::render('Umrah/Adjustments/Index', [
            'company' => ['name' => $company->name, 'slug' => $company->slug, 'base_currency' => $company->base_currency],
            'adjustments' => $rows,
            'reasonLabels' => VisaGroup::ADJUSTMENT_REASONS,
            // For starting one: an adjustment belongs to a trip, so the
            // only way in is to say which.
            'groups' => VisaGroup::where('company_id', $company->id)
                ->where('status', '!=', VisaGroup::STATUS_CANCELLED)
                ->orderByDesc('created_at')
                ->limit(500)
                ->get(['id', 'group_number', 'name'])
                ->map(fn (VisaGroup $group) => [
                    'id' => $group->id,
                    'label' => $group->group_number.' · '.$group->name,
                ]),
            'canAdjust' => (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_GROUP_ACCOUNTING_UPDATE),
        ]);
    }
}
