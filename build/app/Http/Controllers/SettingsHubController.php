<?php

namespace App\Http\Controllers;

use App\Facades\CompanyContext;
use Illuminate\Http\RedirectResponse;

/**
 * The old Settings home. Settings now open from the Settings menu (App\Services\SettingsMenu);
 * this address only forwards to the company settings, so old links and bookmarks still land.
 */
class SettingsHubController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect('/'.CompanyContext::getCompany()->slug.'/settings');
    }
}
