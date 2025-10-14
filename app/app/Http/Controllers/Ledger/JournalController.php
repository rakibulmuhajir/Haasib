<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JournalController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Ledger/Journal/Index');
    }

    public function create(Request $request)
    {
        return Inertia::render('Ledger/Journal/Create');
    }
}
