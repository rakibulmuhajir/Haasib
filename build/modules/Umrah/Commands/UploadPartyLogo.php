<?php

namespace App\Modules\Umrah\Commands;

use App\Services\LogoUploadService;
use Illuminate\Http\UploadedFile;

final class UploadPartyLogo
{
    public function __construct(public readonly string $companyId, public readonly UploadedFile $file) {}

    public function handle(LogoUploadService $logos): string
    {
        return $logos->store($this->file, 'party-logos/'.$this->companyId);
    }
}
