<?php

namespace App\Http\Middleware;

use App\Facades\CompanyContext;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');
        $currentCompany = CompanyContext::getCompany();

        // Get user's companies
        $companies = $request->user()
            ? \DB::table('auth.company_user as cu')
                ->join('auth.companies as c', 'cu.company_id', '=', 'c.id')
                ->where('cu.user_id', $request->user()->id)
                ->where('cu.is_active', true)
                ->where('c.is_active', true)
                ->select('c.id', 'c.name', 'c.slug', 'c.base_currency')
                ->orderBy('c.name')
                ->get()
            : [];

        // If no current company (on global routes), use last accessed company for display
        if (! $currentCompany && $request->user() && session('last_company_slug')) {
            $currentCompany = \DB::table('auth.companies')
                ->where('slug', session('last_company_slug'))
                ->where('is_active', true)
                ->select('id', 'name', 'slug', 'base_currency')
                ->first();
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
                'currentCompany' => $currentCompany,
                'companies' => $companies,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
