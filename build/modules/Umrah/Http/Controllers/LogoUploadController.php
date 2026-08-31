<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Umrah\Http\Requests\StoreLogoRequest;
use App\Services\CurrentCompany;
use App\Services\LogoUploadService;
use Illuminate\Http\JsonResponse;

/**
 * Uploads a party logo on its own, ahead of the form that will use it.
 *
 * The party forms save with PUT, and a PUT carrying a file arrives with no
 * file: PHP only populates uploads for POST. Rather than teach five forms
 * to spoof their method, the picker sends the image here as soon as it is
 * chosen and the form goes on submitting the URL it gets back, exactly as
 * it did when someone was pasting one in by hand.
 */
class LogoUploadController extends Controller
{
    public function __construct(private LogoUploadService $logos) {}

    public function store(StoreLogoRequest $request): JsonResponse
    {
        $company = app(CurrentCompany::class)->get();

        return response()->json([
            'url' => $this->logos->store(
                $request->file('logo'),
                'party-logos/'.$company->id,
                $request->input('replacing'),
            ),
        ]);
    }
}
