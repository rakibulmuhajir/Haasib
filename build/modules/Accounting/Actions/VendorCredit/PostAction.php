<?php

namespace App\Modules\Accounting\Actions\VendorCredit;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\VendorCredit;
use App\Modules\Accounting\Services\DocumentDateLock;
use App\Modules\Accounting\Services\GlPostingService;
use Illuminate\Support\Facades\Auth;

/** Draft -> received: puts the credit on the books (Dr AP, Cr each line's account). */
class PostAction implements PaletteAction
{
    public function rules(): array
    {
        return ['id' => 'required|string'];
    }

    public function permission(): ?string
    {
        return Permissions::VENDOR_CREDIT_CREATE;
    }

    public function handle(array $params): array
    {
        return \App\Services\AccountingWriteTransaction::run(function () use ($params) {
            $company = CompanyContext::requireCompany();
            $credit = VendorCredit::where('company_id', $company->id)->lockForUpdate()->findOrFail($params['id']);

            self::post($credit);

            return [
                'message' => "Vendor credit {$credit->credit_number} posted",
                'data' => ['id' => $credit->id],
            ];
        });
    }

    /** Also used by apply, so a draft is posted before it is spent. */
    public static function post(VendorCredit $credit): void
    {
        if ($credit->status !== 'draft') {
            throw new \InvalidArgumentException('Only a draft credit can be posted');
        }

        app(DocumentDateLock::class)->assertOpen($credit->company_id, $credit->credit_date->toDateString(), "Vendor credit {$credit->credit_number}");

        $transaction = app(GlPostingService::class)->postVendorCredit($credit->fresh());
        $credit->transaction_id = $transaction->id;
        $credit->status = 'received';
        $credit->received_at = now();
        $credit->updated_by_user_id = Auth::id();
        $credit->save();
    }
}
