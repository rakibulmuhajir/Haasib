<?php

// app/Http/Controllers/HomeController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController
{
    public function __invoke(Request $request)
    {
        // If Inertia is installed (as planned), render a page component:
        if (class_exists(\Inertia\Inertia::class)) {
            return \Inertia\Inertia::render('Home', [
                'user' => $request->user()
                    ? $request->user()->only(['id','name','email'])
                    : null,
            ]);
        }

        // Fallback if you haven’t wired Inertia yet.
        return view('welcome');
    }
}
