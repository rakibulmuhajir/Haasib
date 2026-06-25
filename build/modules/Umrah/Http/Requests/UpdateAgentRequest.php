<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\Agent;
use App\Services\CompanyContextService;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateAgentRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_AGENT_UPDATE;
    }

    public function rules(): array
    {
        return [
            'agent_number' => [
                'nullable',
                'string',
                'max:50',
                $this->uniqueForCompany(Agent::class, 'agent_number', 'This agent number is already used.', (string) $this->route('agent')),
            ],
            'user_id' => ['nullable', 'uuid', $this->validCompanyUser(), $this->uniqueLinkedUser()],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', Rule::in(array_keys(Agent::COUNTRIES))],
            'notes' => ['nullable', 'string'],
        ];
    }

    private function validCompanyUser(): Closure
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();

        return function (string $attribute, mixed $value, Closure $fail) use ($companyId): void {
            if ($value === null || $value === '') {
                return;
            }

            $exists = DB::table('auth.company_user')
                ->where('company_id', $companyId)
                ->where('user_id', $value)
                ->where('is_active', true)
                ->exists();

            if (! $exists) {
                $fail('Selected login user is not an active member of this company.');
            }
        };
    }

    private function uniqueLinkedUser(): Closure
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $agentId = (string) $this->route('agent');

        return function (string $attribute, mixed $value, Closure $fail) use ($companyId, $agentId): void {
            if ($value === null || $value === '') {
                return;
            }

            if (Agent::where('company_id', $companyId)->where('user_id', $value)->whereKeyNot($agentId)->whereNull('deleted_at')->exists()) {
                $fail('Selected login user is already linked to another agent.');
            }
        };
    }
}
