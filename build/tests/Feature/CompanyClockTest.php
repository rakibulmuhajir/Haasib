<?php

use App\Models\Company;
use App\Services\CurrentCompany;
use App\Support\CompanyClock;
use Illuminate\Support\Carbon;

// 21:00 UTC on the 15th is 02:00 on the 16th in Karachi (UTC+5).
beforeEach(fn () => Carbon::setTestNow('2026-10-15 21:00:00 UTC'));
afterEach(fn () => Carbon::setTestNow());

function clockCompany(?string $tz): Company
{
    return new Company(['timezone' => $tz]);
}

it('gives the company date for a Karachi company', function () {
    $clock = app(CompanyClock::class);

    expect($clock->today(clockCompany('Asia/Karachi')))->toBe('2026-10-16');
    expect($clock->timezone(clockCompany('Asia/Karachi')))->toBe('Asia/Karachi');
    expect($clock->now(clockCompany('Asia/Karachi'))->format('Y-m-d H:i'))->toBe('2026-10-16 02:00');
});

it('gives the UTC date for a UTC company', function () {
    expect(app(CompanyClock::class)->today(clockCompany('UTC')))->toBe('2026-10-15');
});

it('falls back to the app timezone for a null or invalid timezone', function () {
    $clock = app(CompanyClock::class);

    expect($clock->today(clockCompany(null)))->toBe('2026-10-15');
    expect($clock->today(clockCompany('Not/AZone')))->toBe('2026-10-15');
    expect($clock->today(clockCompany('')))->toBe('2026-10-15');
    expect($clock->timezone(clockCompany('Not/AZone')))->toBe(config('app.timezone'));
});

it('falls back to the app timezone with no company context', function () {
    expect(app(CompanyClock::class)->today())->toBe('2026-10-15');
});

it('uses the current company when none is passed', function () {
    app(CurrentCompany::class)->set(clockCompany('Asia/Karachi'));

    expect(app(CompanyClock::class)->today())->toBe('2026-10-16');
});
