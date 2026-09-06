<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Services\OperationalEventTimelineService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class OperationsIndexRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_OPERATIONS_VIEW;
    }

    protected function prepareForValidation(): void
    {
        $today = Carbon::now(config('umrah.operations.operational_timezone', 'Asia/Riyadh'))->toDateString();

        $this->merge([
            'period' => $this->input('period', 'today'),
            'date' => $this->input('date', $today),
            'event_type' => $this->input('event_type', 'all'),
            'readiness' => $this->input('readiness', 'all'),
        ]);
    }

    public function rules(): array
    {
        return [
            'period' => ['required', Rule::in(array_keys(OperationalEventTimelineService::PERIODS))],
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
            'end' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:start'],
            'event_type' => ['required', Rule::in(['all', ...array_keys(OperationalEventTimelineService::EVENT_TYPES)])],
            'readiness' => ['required', Rule::in(['all', 'ready', 'needs_attention', 'self_arranged'])],
            'agent_id' => ['bail', 'nullable', 'uuid', $this->existsForCompany(Agent::class, 'The selected agent is not available for this company.')],
        ];
    }
}
