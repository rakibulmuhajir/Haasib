<?php

use App\Http\Requests\CompanyStoreRequest;
use App\Models\User;

function companyStoreRequestFor(User $user, string $ownerUserId): CompanyStoreRequest
{
    $request = new class extends CompanyStoreRequest
    {
        public function prepareInput(): void
        {
            $this->prepareForValidation();
        }
    };

    $request->merge(['owner_user_id' => $ownerUserId]);
    $request->setUserResolver(fn () => $user);
    $request->prepareInput();

    return $request;
}

test('company creation is authorized for an authenticated user', function () {
    $user = new User;
    $user->id = '11111111-1111-1111-1111-111111111111';
    $request = companyStoreRequestFor($user, $user->id);

    expect($request->authorize())->toBeTrue();
});

test('a regular user is always made owner of their new company', function () {
    $user = new User;
    $user->id = '11111111-1111-1111-1111-111111111111';
    $request = companyStoreRequestFor(
        $user,
        '22222222-2222-2222-2222-222222222222',
    );

    expect($request->input('owner_user_id'))->toBe($user->id);
});

test('a super admin can assign a different company owner', function () {
    $superAdmin = new User;
    $superAdmin->id = '00000000-0000-0000-0000-000000000099';
    $ownerUserId = '22222222-2222-2222-2222-222222222222';
    $request = companyStoreRequestFor($superAdmin, $ownerUserId);

    expect($request->input('owner_user_id'))->toBe($ownerUserId);
});
