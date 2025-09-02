<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyStoreRequest;
use App\Http\Requests\CompanyInviteRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompanyController extends Controller
{
    public function store(CompanyStoreRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();

        $company = null;
        DB::transaction(function () use ($data, $user, &$company) {
            $company = Company::create([
                'name' => $data['name'],
                'base_currency' => $data['base_currency'] ?? 'AED',
                'language' => $data['language'] ?? 'en',
                'locale' => $data['locale'] ?? 'en-AE',
                'settings' => $data['settings'] ?? [],
            ]);

            // Attach creator as owner
            $user->companies()->attach($company->id, [
                'role' => 'owner',
                'invited_by_user_id' => $user->id,
            ]);
        });

        return response()->json([
            'data' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
                'language' => $company->language,
                'locale' => $company->locale,
            ]
        ], 201);
    }

    public function invite(CompanyInviteRequest $request, string $company)
    {
        $auth = $request->user();

        // Resolve company by id or slug or name
        $q = Company::query();
        if (Str::isUuid($company)) {
            $q->where('id', $company);
        } else {
            $q->where(function ($w) use ($company) { $w->where('slug', $company)->orWhere('name', $company); });
        }
        $co = $q->firstOrFail(['id','name']);

        // Permission: owner/admin or superadmin
        $allowed = $auth->isSuperAdmin() || DB::table('auth.company_user')
            ->where('company_id', $co->id)
            ->where('user_id', $auth->id)
            ->whereIn('role', ['owner','admin'])
            ->exists();
        abort_unless($allowed, 403);

        $data = $request->validated();
        $email = Str::lower($data['email']);
        $role = $data['role'];
        $expiresAt = now()->addDays($data['expires_in_days'] ?? 14);

        $token = Str::lower(Str::random(48));

        // Upsert or create invitation; rely on partial unique index to prevent duplicate pending
        $inv = null;
        DB::transaction(function () use ($co, $email, $role, $auth, $token, $expiresAt, &$inv) {
            // If a pending invite exists, refresh token and expiry
            $existing = DB::table('auth.company_invitations')
                ->where('company_id', $co->id)
                ->where('invited_email', $email)
                ->where('status', 'pending')
                ->first();

            if ($existing) {
                DB::table('auth.company_invitations')->where('id', $existing->id)->update([
                    'role' => $role,
                    'invited_by_user_id' => $auth->id,
                    'token' => $token,
                    'expires_at' => $expiresAt,
                    'updated_at' => now(),
                ]);
                $inv = (object) array_merge((array) $existing, [
                    'role' => $role,
                    'invited_by_user_id' => $auth->id,
                    'token' => $token,
                    'expires_at' => $expiresAt,
                ]);
            } else {
                $id = (string) Str::uuid();
                DB::table('auth.company_invitations')->insert([
                    'id' => $id,
                    'company_id' => $co->id,
                    'invited_email' => $email,
                    'role' => $role,
                    'invited_by_user_id' => $auth->id,
                    'token' => $token,
                    'status' => 'pending',
                    'expires_at' => $expiresAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $inv = DB::table('auth.company_invitations')->where('id', $id)->first();
            }
        });

        // TODO: dispatch email job with deep link containing $token

        return response()->json([
            'data' => [
                'id' => $inv->id,
                'company_id' => $inv->company_id,
                'email' => $inv->invited_email,
                'role' => $inv->role,
                'status' => $inv->status,
                'expires_at' => $inv->expires_at,
                'token' => $token, // for now return it; in prod you’d email only
            ]
        ], 201);
    }
}

