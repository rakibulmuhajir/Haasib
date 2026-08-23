<?php

use Illuminate\Support\Facades\Route;

/*
 * Every other Umrah route that takes a model id constrains it to a UUID. The
 * ticket routes did not, so any URL segment that was not a UUID reached the
 * model binding and Postgres answered with
 *
 *   invalid input syntax for type uuid: "new"
 *
 * -- a 500 for what is only ever a mistyped address. The constraint is what
 * turns that back into a 404.
 *
 * These assert the registered constraint rather than making a request: the
 * auth and identify.company middleware run before model binding, so a request
 * without a session is redirected long before it could demonstrate anything
 * about the binding.
 */

/** The uuid requirement Laravel's whereUuid() installs, whatever its wording. */
function ticketRouteRequirement(string $name, string $parameter): ?string
{
    $route = collect(Route::getRoutes()->getRoutes())
        ->first(fn ($candidate) => $candidate->getName() === $name);

    expect($route)->not->toBeNull("Route {$name} is not registered");

    return $route->wheres[$parameter] ?? null;
}

test('the ticket detail route only accepts a uuid', function () {
    $requirement = ticketRouteRequirement('umrah.tickets.show', 'booking');

    expect($requirement)->not->toBeNull()
        ->and(preg_match('#^'.$requirement.'$#i', 'new'))->toBe(0)
        ->and(preg_match('#^'.$requirement.'$#i', '0196b1c4-8f3a-7c2d-9e51-3a7b4c8d9e10'))->toBe(1);
});

test('the ticket cancel route only accepts a uuid', function () {
    $requirement = ticketRouteRequirement('umrah.tickets.cancel', 'ticket');

    expect($requirement)->not->toBeNull()
        ->and(preg_match('#^'.$requirement.'$#i', 'create'))->toBe(0);
});

test('the create route is still reachable alongside the constrained detail route', function () {
    // The create form lives at tickets/create, not tickets/new. It is declared
    // before tickets/{booking} and, now that the detail route rejects
    // non-uuids, could not be shadowed by it even if it were not.
    $names = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->getName())
        ->filter()
        ->values();

    expect($names)->toContain('umrah.tickets.create');
});
