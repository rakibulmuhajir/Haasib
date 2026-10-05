<?php

namespace App\Modules\Accounting\Actions\CustomerCategory;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\CustomerCategory;
use Illuminate\Validation\ValidationException;

class DeleteAction implements PaletteAction
{
    public function rules(): array
    {
        return ['id' => 'required|uuid'];
    }

    public function permission(): ?string
    {
        return Permissions::CUSTOMER_UPDATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $category = CustomerCategory::where('company_id', $company->id)->findOrFail($params['id']);

        // Inactive and soft-deleted customers still point at it, so count every one.
        $used = Customer::withTrashed()->where('company_id', $company->id)->where('category_id', $category->id)->count();
        if ($used > 0) {
            throw ValidationException::withMessages([
                'category' => "In use by {$used} customer".($used === 1 ? '' : 's').'.',
            ]);
        }

        $category->delete();

        return [
            'message' => "Category deleted: {$category->name}",
            'data' => ['id' => $category->id],
        ];
    }
}
