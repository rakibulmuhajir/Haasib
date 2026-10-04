<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Http\Requests\DeleteCalculatorFormulaRequest;
use App\Modules\FuelStation\Http\Requests\EvaluateCalculatorRequest;
use App\Modules\FuelStation\Http\Requests\SaveCalculatorFormulaRequest;
use App\Modules\FuelStation\Services\Calculator\CalculatorService;
use App\Services\CommandBus;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Calculator: a formula over the books. Reading only -- nothing here writes to the ledger.
 * The page asks for a result with a POST that answers with the same page (an Inertia partial
 * reload of `result`), so no fetch or axios is involved.
 */
class CalculatorController extends Controller
{
    public function __construct(private readonly CalculatorService $calculator) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->isGodMode() || $user->hasCompanyPermission(Permissions::REPORT_VIEW), 403);

        return $this->page($request, null);
    }

    public function evaluate(EvaluateCalculatorRequest $request): Response
    {
        $company = app(CurrentCompany::class)->get();

        return $this->page($request, $this->calculator->run($company, $request->validated('formula')));
    }

    public function store(SaveCalculatorFormulaRequest $request): RedirectResponse
    {
        app(CommandBus::class)->dispatch('calculator.save', $request->validated() + ['user_id' => $request->user()->id], $request->user());

        return back()->with('success', 'Formula saved.');
    }

    public function update(SaveCalculatorFormulaRequest $request, string $company, string $formula): RedirectResponse
    {
        app(CommandBus::class)->dispatch('calculator.save', $request->validated() + ['id' => $formula, 'user_id' => $request->user()->id], $request->user());

        return back()->with('success', 'Formula saved.');
    }

    public function destroy(DeleteCalculatorFormulaRequest $request, string $company, string $formula): RedirectResponse
    {
        app(CommandBus::class)->dispatch('calculator.delete', ['id' => $formula, 'user_id' => $request->user()->id], $request->user());

        return back()->with('success', 'Formula deleted.');
    }

    private function page(Request $request, ?array $result): Response
    {
        $company = app(CurrentCompany::class)->get();
        $userId = $request->user()->id;

        return Inertia::render('FuelStation/Calculator/Index', [
            'company' => ['id' => $company->id, 'name' => $company->name, 'slug' => $company->slug, 'base_currency' => $company->base_currency ?? 'PKR'],
            'result' => $result,
            // Closures: a partial reload for the result skips them.
            'metrics' => fn () => $this->calculator->metrics(),
            'options' => fn () => $this->calculator->options($company->id),
            'saved' => fn () => $this->calculator->saved($company->id, $userId),
            'example' => fn () => $this->calculator->example($company->id),
        ]);
    }
}
