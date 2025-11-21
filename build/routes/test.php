<?php

Route::get('/test-company-user', function () {
    try {
        $count = App\Models\CompanyUser::count();
        return "CompanyUser count: $count";
    } catch (\Exception $e) {
        return "Error: " . $e->getMessage();
    }
});

Route::get('/test-company-user-create', function () {
    try {
        $user = App\Models\User::first();
        $company = App\Models\Company::where('id', '!=', $user->companies()->first()->id ?? null)->first();
        
        if (!$user || !$company) {
            return "Need both user and company";
        }
        
        // Check if already exists
        $exists = App\Models\CompanyUser::where('user_id', $user->id)
            ->where('company_id', $company->id)
            ->first();
            
        if ($exists) {
            return "Already exists: " . $exists->id;
        }
        
        // Create new
        $companyUser = App\Models\CompanyUser::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'member',
            'is_active' => true,
            'joined_at' => now()
        ]);
        
        return "Created: " . $companyUser->id;
    } catch (\Exception $e) {
        return "Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
    }
});