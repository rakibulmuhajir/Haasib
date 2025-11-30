<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\DB;

class CurrentCompany
{
    private ?Company $company = null;

    public function setBySlug(string $slug): void
    {
        $this->company = Company::where('slug', $slug)->firstOrFail();
        
        DB::select("SELECT set_config('app.current_company_id', ?, true)", [$this->company->id]);
        setPermissionsTeamId($this->company->id);
    }

    public function setById(string $id): void
    {
        $this->company = Company::findOrFail($id);
        
        DB::select("SELECT set_config('app.current_company_id', ?, true)", [$this->company->id]);
        setPermissionsTeamId($this->company->id);
    }

    public function set(Company $company): void
    {
        $this->company = $company;
        
        DB::select("SELECT set_config('app.current_company_id', ?, true)", [$company->id]);
        setPermissionsTeamId($company->id);
    }

    public function get(): ?Company
    {
        return $this->company;
    }

    public function getId(): ?string
    {
        return $this->company?->id;
    }

    public function clear(): void
    {
        $this->company = null;
        
        DB::select("SELECT set_config('app.current_company_id', NULL, true)");
        setPermissionsTeamId(null);
    }
}
