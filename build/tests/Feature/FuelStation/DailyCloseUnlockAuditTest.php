<?php

use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Models\DailyCloseUnlock;
use App\Modules\FuelStation\Services\DailyCloseService;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/CreditCloseFixtures.php';

/**
 * Locking a close is only worth anything if reopening it leaves a mark. Transaction::unlock()
 * used to null locked_at, locked_by_user_id and lock_reason, so an unlocked day became
 * indistinguishable from a day that was never locked -- and the post-close audit trigger
 * (fuel.capture_post_close_activity) deliberately skips fuel_daily_close rows, so nothing
 * caught it there either. These tests pin the unlock trail down.
 *
 * Relies on creditCloseFixture() from DailyCloseCreditSalesTest.php, which Pest loads when
 * the FuelStation directory is run together (module scope), per project convention.
 */
function unlockAuditFixture(): array
{
    $f = creditCloseFixture();
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($f['company']);
    DB::table('auth.company_user')->insert([
        'company_id' => $f['company']->id, 'user_id' => $f['user']->id, 'role' => 'owner',
        'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CompanyContextService::class)->assignRole($f['user'], 'owner'));
    $f['company']->enableModule('fuel_station');
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    test()->actingAs($f['user']);
    app(\App\Services\CurrentCompany::class)->set($f['company']);

    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $f['transaction'] = Transaction::findOrFail($posted['transaction_id']);

    test()->actingAs($f['user'])
        ->post("/{$f['company']->slug}/fuel/daily-close/{$f['transaction']->id}/lock")
        ->assertRedirect();

    $f['transaction']->refresh();

    return $f;
}

test('a locked close cannot be reopened without a stated reason', function () {
    $f = unlockAuditFixture();
    expect($f['transaction']->is_locked)->toBeTrue();

    test()->actingAs($f['user'])
        ->post("/{$f['company']->slug}/fuel/daily-close/{$f['transaction']->id}/unlock", [])
        ->assertSessionHasErrors('reason');

    expect($f['transaction']->fresh()->is_locked)->toBeTrue()
        ->and(DailyCloseUnlock::where('company_id', $f['company']->id)->count())->toBe(0);
});

test('reopening a locked close records who reopened it, when, why, and the lock it replaced', function () {
    $f = unlockAuditFixture();
    $lockedAt = $f['transaction']->locked_at;

    test()->actingAs($f['user'])
        ->post("/{$f['company']->slug}/fuel/daily-close/{$f['transaction']->id}/unlock", [
            'reason' => 'Attendant reported nozzle 1 closing reading was transposed.',
        ])
        ->assertRedirect();

    expect($f['transaction']->fresh()->is_locked)->toBeFalse();

    $unlock = DailyCloseUnlock::where('company_id', $f['company']->id)->sole();
    expect($unlock->close_transaction_id)->toBe($f['transaction']->id)
        ->and($unlock->unlocked_by_user_id)->toBe($f['user']->id)
        ->and($unlock->reason)->toBe('Attendant reported nozzle 1 closing reading was transposed.')
        ->and($unlock->unlocked_at)->not->toBeNull()
        ->and($unlock->previously_locked_by_user_id)->toBe($f['user']->id)
        ->and($unlock->previously_locked_at->toIso8601String())->toBe($lockedAt->toIso8601String());
});

test('every reopening is kept, so a day locked and reopened twice shows both', function () {
    $f = unlockAuditFixture();
    $url = "/{$f['company']->slug}/fuel/daily-close/{$f['transaction']->id}";

    test()->actingAs($f['user'])->post("{$url}/unlock", ['reason' => 'First correction to the dip reading.'])->assertRedirect();
    test()->actingAs($f['user'])->post("{$url}/lock")->assertRedirect();
    test()->actingAs($f['user'])->post("{$url}/unlock", ['reason' => 'Second correction after the tanker paperwork arrived.'])->assertRedirect();

    $reasons = DailyCloseUnlock::where('company_id', $f['company']->id)->orderBy('unlocked_at')->pluck('reason')->all();
    expect($reasons)->toBe([
        'First correction to the dip reading.',
        'Second correction after the tanker paperwork arrived.',
    ]);
});

test('the unlock trail cannot be edited or deleted once written', function () {
    $f = unlockAuditFixture();

    test()->actingAs($f['user'])
        ->post("/{$f['company']->slug}/fuel/daily-close/{$f['transaction']->id}/unlock", ['reason' => 'Correcting the cash count.'])
        ->assertRedirect();

    $unlock = DailyCloseUnlock::where('company_id', $f['company']->id)->sole();

    expect(fn () => DB::table('fuel.daily_close_unlocks')->where('id', $unlock->id)->update(['reason' => 'something else']))
        ->toThrow(\Illuminate\Database\QueryException::class);
    expect(fn () => DB::table('fuel.daily_close_unlocks')->where('id', $unlock->id)->delete())
        ->toThrow(\Illuminate\Database\QueryException::class);
});
