<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class GuideController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('FuelStation/Guide/Index');
    }
}
