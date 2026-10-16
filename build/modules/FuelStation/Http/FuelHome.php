<?php

namespace App\Modules\FuelStation\Http;

use App\Support\CompanyClock;
use App\Constants\Permissions;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/** The fuel station's company home (moved verbatim from CompanyController::show). */
class FuelHome
{
    public static function render(Request $request, Company $company): Response
    {
        $isGodMode = str_starts_with(Auth::id() ?? '', '00000000-0000-0000-0000-');
        $isMember = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', Auth::id())
            ->where('is_active', true)
            ->exists();
        if (! $isGodMode && ! $isMember) {
            abort(403, 'You are not a member of this company.');
        }

        $tab = $request->query('tab') === 'history' ? 'history' : 'today';
        $service = app(\App\Modules\FuelStation\Services\FuelHomeService::class);
        $available = $request->user()->showsValueTrails()
            && ($isGodMode || $request->user()->hasCompanyPermission(Permissions::REPORT_VIEW));
        $includeTrail = $available
            && $request->header('X-Inertia-Partial-Component') === 'FuelStation/Home/Index'
            && in_array('valueTrail', explode(',', (string) $request->header('X-Inertia-Partial-Data')), true);
        try {
            $props = [
                'company' => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'slug' => $company->slug,
                    'base_currency' => $company->base_currency ?? 'PKR',
                ],
                'tab' => $tab,
                'valueTrailsAvailable' => $available,
                'today' => $service->today($company, $includeTrail && $tab === 'today'),
            ];
            $trail = $props['today']['month']['statement']['valueTrail'] ?? null;
            unset($props['today']['month']['statement']['valueTrail']);
            if ($tab === 'history') {
                [$from, $to] = self::historyRange($request->query('from'), $request->query('to'));
                $props['history'] = $service->period($company, $from, $to, $includeTrail);
                $props['range'] = ['from' => $from, 'to' => $to];
                $trail = $props['history']['statement']['valueTrail'] ?? null;
                unset($props['history']['statement']['valueTrail']);
            }
            if ($trail) {
                $trail = app(\App\Modules\FuelStation\Services\ProfitValueTrailPresenter::class)->present($trail, $company, $request->user());
            }
            $props['valueTrail'] = Inertia::optional(fn () => $trail);
        } catch (\Throwable $exception) {
            if (! $includeTrail) {
                throw $exception;
            }
            Log::error('Could not load the home profit statement trail.', ['company_id' => $company->id, 'exception' => $exception]);

            return Inertia::render('FuelStation/Home/Index', [
                'valueTrail' => Inertia::optional(fn () => ['error' => 'Could not load this calculation. Please try again.']),
            ]);
        }

        return Inertia::render('FuelStation/Home/Index', $props);
    }

    /** The History tab's range: ?from & ?to as dates, last month when absent or unreadable. */
    private static function historyRange(mixed $from, mixed $to): array
    {
        $parse = function (mixed $v): ?Carbon {
            try {
                return $v ? Carbon::parse((string) $v)->startOfDay() : null;
            } catch (\Throwable) {
                return null;
            }
        };
        $start = $parse($from);
        $end = $parse($to);
        if (! $start || ! $end) {
            $start = Carbon::parse(app(CompanyClock::class)->today())->subMonthNoOverflow()->startOfMonth();
            $end = $start->copy()->endOfMonth();
        }
        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        return [$start->toDateString(), $end->toDateString()];
    }
}
