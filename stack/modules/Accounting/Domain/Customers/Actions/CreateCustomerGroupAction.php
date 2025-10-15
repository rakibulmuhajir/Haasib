<?php

namespace Modules\Accounting\Domain\Customers\Actions;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Domain\Customers\Models\CustomerGroup;

class CreateCustomerGroupAction
{
    /**
     * Create a new customer group.
     */
    public function execute(array $data, int $companyId): CustomerGroup
    {
        // Validate data
        $this->validate($data);

        return DB::transaction(function () use ($data, $companyId) {
            // Check for unique name within company
            $this->validateUniqueName($data['name'], $companyId);

            // Create the group
            $group = CustomerGroup::create([
                'company_id' => $companyId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_default' => (bool) ($data['is_default'] ?? false),
            ]);

            // Emit audit event
            $this->emitAuditEvent('customer_group_created', $group);

            return $group;
        });
    }

    /**
     * Validate the group data.
     */
    private function validate(array $data): void
    {
        $validator = Validator::make($data, [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'is_default' => 'boolean',
        ], [
            'name.required' => 'Group name is required.',
            'name.max' => 'Group name cannot exceed 100 characters.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }

    /**
     * Validate that group name is unique within the company.
     */
    private function validateUniqueName(string $name, int $companyId): void
    {
        if (CustomerGroup::where('company_id', $companyId)->where('name', $name)->exists()) {
            throw ValidationException::withMessages([
                'name' => 'A group with this name already exists.',
            ]);
        }
    }

    /**
     * Emit audit event for the action.
     */
    private function emitAuditEvent(string $event, CustomerGroup $group): void
    {
        if (function_exists('audit_log')) {
            audit_log($event, [
                'group_id' => $group->id,
                'company_id' => $group->company_id,
                'name' => $group->name,
                'is_default' => $group->is_default,
                'performed_by' => auth()->id(),
            ]);
        }
    }
}
