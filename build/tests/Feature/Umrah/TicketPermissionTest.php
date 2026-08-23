<?php

use App\Constants\Permissions;

/**
 * Reads the role straight out of config/role-permissions.php -- the same
 * matrix php artisan rbac:sync-role-permissions applies per company -- so
 * these tests exercise the source of truth without needing a company, a
 * user, or a database round trip.
 */
function ticketingRolePermissions(string $role): array
{
    return config("role-permissions.{$role}", []);
}

it('gives an owner every ticket permission', function () {
    expect(ticketingRolePermissions('owner'))->toContain(
        Permissions::UMRAH_TICKET_VIEW,
        Permissions::UMRAH_TICKET_CREATE,
        Permissions::UMRAH_TICKET_UPDATE,
        Permissions::UMRAH_TICKET_CANCEL,
    );
});

it('lets an agent see their own bookings and nobody else’s', function () {
    $agent = ticketingRolePermissions('agent');

    expect($agent)->toContain(Permissions::UMRAH_TICKET_OWN_VIEW)
        ->and($agent)->not->toContain(Permissions::UMRAH_TICKET_VIEW);
});

it('does not let an agent cancel a ticket', function () {
    // Cancelling moves money on both sides of the book.
    expect(ticketingRolePermissions('agent'))->not->toContain(Permissions::UMRAH_TICKET_CANCEL);
});

it('lets operations create a booking but not cancel one', function () {
    $ops = ticketingRolePermissions('operations');

    expect($ops)->toContain(Permissions::UMRAH_TICKET_CREATE)
        ->and($ops)->not->toContain(Permissions::UMRAH_TICKET_CANCEL);
});
