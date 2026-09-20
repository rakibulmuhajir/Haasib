<?php

use Illuminate\Support\Facades\Route;

/**
 * useInlineEdit() saves a single field with PATCH. Any page that wires its fields through
 * that composable therefore needs its update route to accept PATCH, not PUT alone.
 *
 * It was PUT alone on customers and vendors, so all 19 inline fields across those two Show
 * pages - plus both address saves - returned 405 and rendered a MethodNotAllowedHttpException
 * instead of saving. Nothing caught it because the composable's third consumer, company
 * settings, points at a route that was already PATCH, so the one screen anybody exercised
 * happened to be the one whose verb matched.
 *
 * This pins the verb for every endpoint the composable posts to.
 */
test('every endpoint useInlineEdit patches accepts PATCH', function (string $name) {
    $route = Route::getRoutes()->getByName($name);

    expect($route)->not->toBeNull("route [{$name}] is missing");
    expect($route->methods())->toContain('PATCH');
})->with([
    'customers.update',
    'vendors.update',
    'company.settings.update',
]);

/**
 * The full-record Edit forms still send PUT, so widening the verb must not have narrowed it.
 */
test('the record update routes still accept PUT for the full Edit form', function (string $name) {
    expect(Route::getRoutes()->getByName($name)->methods())->toContain('PUT');
})->with([
    'customers.update',
    'vendors.update',
]);
