<?php

use Illuminate\Support\Facades\Route;

it('exposes expected invoicing routes', function () {
    // Named routes should be registered
    expect(Route::has('invoices.index'))->toBeTrue();
    expect(Route::has('invoices.store'))->toBeTrue();
    expect(Route::has('invoices.post'))->toBeTrue();
    expect(Route::has('invoices.cancel'))->toBeTrue();
    expect(Route::has('invoices.generate-pdf'))->toBeTrue();
    expect(Route::has('invoices.send-email'))->toBeTrue();
});

it('guards invoicing index with auth', function () {
    $response = $this->get(route('invoices.index'));
    // Unauthenticated users should be redirected to login
    $response->assertStatus(302);
});

