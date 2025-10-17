<?php

use App\Models\Command;
use App\Models\User;

afterEach(function () {
    \Mockery::close();
});

it('fails closed when no permissions are defined', function () {
    $command = Command::make(['required_permissions' => []]);

    $user = \Mockery::mock(User::class);
    $user->shouldNotReceive('hasAllPermissions');

    expect($command->userHasPermission($user))->toBeFalse();
    expect($command->userHasPermission(null))->toBeFalse();
});

it('requires all listed permissions', function () {
    $command = Command::make(['required_permissions' => ['commands.execute']]);

    $user = \Mockery::mock(User::class);
    $user->shouldReceive('hasAllPermissions')
        ->once()
        ->with(['commands.execute'])
        ->andReturnTrue();

    expect($command->userHasPermission($user))->toBeTrue();
});
