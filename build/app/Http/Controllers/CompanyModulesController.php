<?php

namespace App\Http\Controllers;

use App\Http\Requests\Company\UpdateCompanyModulesRequest;
use App\Services\CommandBus;
use Illuminate\Http\RedirectResponse;

class CompanyModulesController extends Controller
{
    public function update(UpdateCompanyModulesRequest $request): RedirectResponse
    {
        $commandBus = app(CommandBus::class);
        $result = $commandBus->dispatch('company.modules.update', $request->validated(), $request->user());

        return back()->with('success', $result['message'] ?? 'Modules updated successfully.');
    }
}

