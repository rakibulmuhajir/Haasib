<?php

namespace App\Modules\Accounting\Actions\Vendor;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Bill;
use Illuminate\Support\Facades\DB;

class DeleteAction implements PaletteAction
{
    use ResolvesVendors;

    public function rules(): array
    {
        return [
            'id' => 'required|string|max:255',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::VENDOR_DELETE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $vendor = $this->resolveVendor($params['id'], $company->id);

        // Same open-bill definition the vendor page uses for its outstanding
        // figure, so a vendor the screen shows money owed to cannot be removed
        // from underneath it.
        $unpaidCount = Bill::where('company_id', $company->id)
            ->where('vendor_id', $vendor->id)
            ->whereNotIn('status', ['paid', 'void', 'cancelled'])
            ->where('balance', '>', 0)
            ->count();

        if ($unpaidCount > 0) {
            throw new \Exception(
                "Cannot delete vendor with {$unpaidCount} unpaid bill(s). ".
                'Void or pay them first.'
            );
        }

        $creditCount = DB::table('acct.vendor_credits')
            ->where('vendor_id', $vendor->id)
            ->where('status', '!=', 'void')
            ->count();

        if ($creditCount > 0) {
            throw new \Exception(
                "Cannot delete vendor with {$creditCount} open vendor credit(s). ".
                'Void them first.'
            );
        }

        // Deactivation, not deletion -- matching the customer action. A vendor
        // is referenced by every bill ever raised against it, so the record has
        // to stay readable for those to make sense.
        $vendor->update(['is_active' => false]);

        return [
            'message' => "Vendor deleted: {$vendor->name}",
            'data' => [
                'id' => $vendor->id,
                'name' => $vendor->name,
            ],
        ];
    }
}
