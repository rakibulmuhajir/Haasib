<?php

use App\Http\Middleware\EnsureIdempotency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('idempotency middleware stores payload hash instead of full payload', function () {
    $user = User::factory()->create(['current_company_id' => 'company-123']);
    $middleware = new EnsureIdempotency;

    $request = Request::create('/api/test', 'POST', [
        'sensitive_data' => 'secret',
        'public_data' => 'value',
    ]);
    $request->setUserResolver(fn () => $user);
    $request->headers->set('Idempotency-Key', 'test-key-123');

    $response = $middleware->handle($request, fn () => response()->json(['id' => 'inv-123']));

    $record = DB::table('idempotency_keys')->first();
    expect($record)->not->toBeNull();
    expect($record->payload_hash)->not->toBeNull();
    expect($record->payload_hash)->not->toContain('secret'); // Hash shouldn't contain plaintext
});

test('idempotency middleware rejects key reuse with different payload', function () {
    $user = User::factory()->create(['current_company_id' => 'company-123']);
    $middleware = new EnsureIdempotency;

    // First request
    $request1 = Request::create('/api/test', 'POST', ['amount' => 100]);
    $request1->setUserResolver(fn () => $user);
    $request1->headers->set('Idempotency-Key', 'test-key-123');

    $response1 = $middleware->handle($request1, fn () => response()->json(['id' => 'inv-123']));
    $response1->assertStatus(200);

    // Second request with same key but different payload
    $request2 = Request::create('/api/test', 'POST', ['amount' => 200]);
    $request2->setUserResolver(fn () => $user);
    $request2->headers->set('Idempotency-Key', 'test-key-123');

    $response2 = $middleware->handle($request2, fn () => response()->json(['id' => 'inv-456']));
    $response2->assertStatus(409)
        ->assertJson(['error' => 'Idempotency key already used with different payload']);
});

test('idempotency middleware stores minimal response data', function () {
    $user = User::factory()->create(['current_company_id' => 'company-123']);
    $middleware = new EnsureIdempotency;

    $request = Request::create('/api/invoices', 'POST', ['amount' => 100]);
    $request->setUserResolver(fn () => $user);
    $request->headers->set('Idempotency-Key', 'test-key-123');

    $fullResponse = response()->json([
        'id' => 'inv-123',
        'invoice_number' => 'INV-2024-001',
        'customer_id' => 'cust-456',
        'total_amount' => '100.00',
        'created_at' => now()->toISOString(),
        'updated_at' => now()->toISOString(),
    ], 201);

    $middleware->handle($request, fn () => $fullResponse);

    $record = DB::table('idempotency_keys')->first();
    $storedResponse = json_decode($record->response, true);

    expect($storedResponse)->toEqual([
        'status' => 201,
        'success' => true,
        'resource_id' => 'inv-123',
        'resource_type' => 'inv',
    ]);
});

test('idempotency middleware filters sensitive fields from hash', function () {
    $user = User::factory()->create(['current_company_id' => 'company-123']);
    $middleware = new EnsureIdempotency;

    // Request with sensitive fields
    $request1 = Request::create('/api/login', 'POST', [
        'email' => 'user@example.com',
        'password' => 'secret123',
        'current_password' => 'oldpass',
        'token' => 'csrf-token',
    ]);
    $request1->setUserResolver(fn () => $user);
    $request1->headers->set('Idempotency-Key', 'login-key');

    $middleware->handle($request1, fn () => response()->json(['success' => true]));

    $record1 = DB::table('idempotency_keys')->first();
    $hash1 = $record1->payload_hash;

    // Request without sensitive fields should have different hash
    $request2 = Request::create('/api/login', 'POST', [
        'email' => 'user@example.com',
    ]);
    $request2->setUserResolver(fn () => $user);
    $request2->headers->set('Idempotency-Key', 'login-key-2');

    $middleware->handle($request2, fn () => response()->json(['success' => true]));

    $record2 = DB::table('idempotency_keys')->where('key', 'login-key-2')->first();
    $hash2 = $record2->payload_hash;

    expect($hash1)->not->toBe($hash2);
});

test('idempotency middleware reconstructs minimal response from stored data', function () {
    $user = User::factory()->create(['current_company_id' => 'company-123']);
    $middleware = new EnsureIdempotency;

    // First request creates and stores response
    $request1 = Request::create('/api/invoices', 'POST', ['amount' => 100]);
    $request1->setUserResolver(fn () => $user);
    $request1->headers->set('Idempotency-Key', 'test-key-123');

    $response1 = $middleware->handle($request1, fn () => response()->json([
        'id' => 'inv-123',
        'invoice_number' => 'INV-2024-001',
        'total_amount' => '100.00',
    ], 201));

    // Second request with same key returns reconstructed response
    $request2 = Request::create('/api/invoices', 'POST', ['amount' => 100]);
    $request2->setUserResolver(fn () => $user);
    $request2->headers->set('Idempotency-Key', 'test-key-123');

    $response2 = $middleware->handle($request2, fn () => response()->json(['id' => 'inv-456']));

    $response2->assertStatus(201)
        ->assertJson([
            'id' => 'inv-123',
            'message' => 'Inv created successfully',
        ]);
});
