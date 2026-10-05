<?php

namespace App\Modules\Accounting\Actions\CustomerCategory;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\CustomerCategory;
use Illuminate\Validation\ValidationException;

class UpdateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|uuid',
            'name' => 'required|string|min:1|max:100',
            'description' => 'nullable|string|max:500',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::CUSTOMER_UPDATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $category = CustomerCategory::where('company_id', $company->id)->findOrFail($params['id']);
        $name = trim($params['name']);

        $taken = CustomerCategory::where('company_id', $company->id)->where('id', '!=', $category->id)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists();
        if ($taken) {
            throw ValidationException::withMessages(['name' => 'That category already exists.']);
        }

        $category->update([
            'name' => $name,
            'description' => array_key_exists('description', $params) ? $params['description'] : $category->description,
        ]);

        return [
            'message' => "Category renamed: {$category->name}",
            'data' => ['id' => $category->id, 'name' => $category->name],
        ];
    }
}
