<?php

namespace App\Modules\Accounting\Actions\CustomerCategory;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\CustomerCategory;
use Illuminate\Validation\ValidationException;

class CreateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
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
        $name = trim($params['name']);

        $exists = CustomerCategory::where('company_id', $company->id)->whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists();
        if ($exists) {
            throw ValidationException::withMessages(['name' => 'That category already exists.']);
        }

        $category = CustomerCategory::create([
            'company_id' => $company->id,
            'name' => $name,
            'description' => $params['description'] ?? null,
        ]);

        return [
            'message' => "Category added: {$category->name}",
            'data' => ['id' => $category->id, 'name' => $category->name],
        ];
    }
}
