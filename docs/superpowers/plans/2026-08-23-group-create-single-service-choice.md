# Group Create — One Service Choice Implementation Plan

> **For agentic workers:** Steps use checkbox (`- [ ]`) syntax for tracking. Each task lands complete and committed on its own.

**Goal:** Replace the per-passenger service decision on group create with a single group-level choice of four options, and stop a specialized group from booking fewer vehicle seats than it has passengers.

**Architecture:** The group's own record becomes the only place that says what is being sold. `umrah.visa_groups` gains one boolean, `includes_visa`, which is the second axis alongside the existing `transport_mode`; together they express all four options. Passenger `service_type` and `transport_charge_amount` stop being collected from the operator and are derived server-side from the group, so the 20-odd downstream readers of those columns keep working unchanged.

**Tech Stack:** Laravel 12, Inertia, Vue 3 `<script setup lang="ts">`, PostgreSQL, Pest.

## Global Constraints

- Application root is `build/`. Umrah migrations live in `modules/Umrah/Database/Migrations/`; Umrah tests live in `build/tests/Feature/Umrah/` and `build/tests/Unit/Umrah/`, **never** inside the module.
- PHP namespace is `App\Modules\Umrah\...` though the path is `modules/Umrah/...`.
- UUID keys: `protected $keyType = 'string'; public $incrementing = false;`
- Schema-prefixed table names (`umrah.visa_groups`), RLS by `company_id`.
- Vue: shadcn components only, no raw `<input>`/`<button>`; Inertia forms, never `fetch`/`axios`.
- Every `<form>` tag carries `novalidate`. The invariant is currently **106 form tags : 106 attributes** — this plan adds no forms, so it must still read 106:106.
- **Do not run the full test suite.** Run only your own task's test file with `--filter`. The orchestrator runs the full suite.
- Pest/artisan output carries ANSI escapes — pipe through `sed 's/\x1b\[[0-9;]*m//g'`.
- `sed -i` silently no-ops on CRLF files here. Use the Edit tool.
- `rm -f build/bash.exe.stackdump` before any `git add`.

---

## Decisions taken

**Why a boolean and not a fourth `transport_mode` value.** "Transport only" is not a kind of transport; it is the absence of a visa. Folding it into `transport_mode` would make `transport_only` mutually exclusive with `standard_bus` and `specialized`, which is wrong — a group of people who already hold visas can travel by either. Two independent axes, one boolean and one enum, express all four options and stay honest if a fifth appears.

**Transport-only groups get a shorter lifecycle.** `passports_received`, `submitted` and `visa_approved` describe a visa application and mean nothing when there is no visa. A group with `includes_visa = false` moves `draft → delivered → closed`, with `cancelled` reachable throughout. The three visa statuses are rejected for such a group rather than merely hidden, so no code path can park one in a state nobody can act on.

**`service_type` stays in the database, derived rather than collected.** Twenty-three files read `Passenger::SERVICE_*`; `Voucher` has its own constants that merely spell the same strings. Ripping the column out is a separate, larger change with its own risk. This plan removes the *decision* — the operator never sees it — while the column keeps a value that is now always consistent with its group, because the server writes it from `includes_visa`. That delivers the outcome asked for without a 23-file migration.

**Seat shortfall corrects the vehicle count, it does not block.** Per the request: when a specialized item's passengers exceed `quantity × pax_capacity`, raise `quantity` to `ceil(passengers / capacity)`. The form does it live and says it did; the server re-checks so a hand-built payload cannot slip under. Where `pax_capacity` is null the vehicle's capacity is unknown and no check is possible — skip silently rather than guess.

---

## The four options as the operator sees them

Radio group, "What this group includes":

| Label | Sub-label | `includes_visa` | `transport_mode` |
|---|---|---|---|
| Visa only (self transport) | Passengers arrange their own transport. No vehicle, driver or fare is recorded. | true | `none` |
| Visa and standard bus | One per-head bus rate from the transport provider, alongside the visa. | true | `standard_bus` |
| Visa and specialized transport | Chartered vehicles priced per vehicle, alongside the visa. | true | `specialized` |
| Transport only | Everyone already holds a visa. No visa vendor, no visa charge. | false | `specialized` |

---

## File structure

| File | Responsibility |
|---|---|
| `modules/Umrah/Database/Migrations/2026_08_23_000001_add_includes_visa_to_visa_groups.php` | Adds the column and the two check constraints |
| `modules/Umrah/Models/VisaGroup.php` | `includes_visa` in `$fillable`/`$casts`; `statusesAvailable()` |
| `modules/Umrah/Http/Requests/StoreVisaGroupRequest.php` | Validates the pair; derives `service_type`; seat check |
| `modules/Umrah/Http/Requests/UpdateVisaGroupRequest.php` | Same rules on edit |
| `modules/Umrah/Http/Controllers/VisaGroupController.php` | Passes `includes_visa` to the page; writes derived passenger fields |
| `modules/Umrah/Services/GroupAccountingService.php` | Suppresses the visa service line when `includes_visa` is false |
| `modules/Umrah/Resources/js/pages/Umrah/Groups/Create.vue` | The single radio group; seat auto-correction; passenger rows lose two fields |
| `modules/Umrah/Resources/js/pages/Umrah/Groups/Show.vue` | Reads the group's choice instead of per-passenger types |
| `tests/Feature/Umrah/GroupServiceChoiceTest.php` | New — the four options end to end |
| `tests/Unit/Umrah/StoreVisaGroupRequestTest.php` | Extended — validation of the pair and the seat rule |

---

## Task 1: The column and the model

**Files:**
- Create: `modules/Umrah/Database/Migrations/2026_08_23_000001_add_includes_visa_to_visa_groups.php`
- Modify: `modules/Umrah/Models/VisaGroup.php`
- Test: `tests/Feature/Umrah/GroupServiceChoiceTest.php`

**Interfaces:**
- Produces: `VisaGroup::$includes_visa` (bool, default true); `VisaGroup::statusesAvailable(bool $includesVisa): array` returning the label map valid for that kind of group.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Modules\Umrah\Models\VisaGroup;

it('defaults an existing group to including a visa', function () {
    $group = VisaGroup::factory()->create();

    expect($group->fresh()->includes_visa)->toBeTrue();
});

it('offers no visa statuses to a transport-only group', function () {
    expect(array_keys(VisaGroup::statusesAvailable(false)))
        ->toBe(['draft', 'delivered', 'closed', 'cancelled']);

    expect(array_keys(VisaGroup::statusesAvailable(true)))
        ->toBe(array_keys(VisaGroup::STATUSES));
});

it('refuses a group that is neither visa nor transport', function () {
    expect(fn () => VisaGroup::factory()->create([
        'includes_visa' => false,
        'transport_mode' => VisaGroup::TRANSPORT_NONE,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 2: Run it and confirm it fails**

Run: `php artisan test tests/Feature/Umrah/GroupServiceChoiceTest.php 2>&1 | sed 's/\x1b\[[0-9;]*m//g'`
Expected: FAIL — unknown column `includes_visa`.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umrah.visa_groups', function (Blueprint $table) {
            $table->boolean('includes_visa')->default(true);
        });

        // A group that sells neither a visa nor transport is not a group.
        DB::statement("ALTER TABLE umrah.visa_groups ADD CONSTRAINT visa_groups_sells_something_check
            CHECK (includes_visa OR transport_mode <> 'none')");

        // passports_received, submitted and visa_approved describe a visa
        // application. A transport-only group has none, so those states are
        // rejected rather than merely hidden -- otherwise a group can be
        // parked where no one can act on it.
        DB::statement("ALTER TABLE umrah.visa_groups ADD CONSTRAINT visa_groups_status_matches_kind_check
            CHECK (includes_visa OR status IN ('draft', 'delivered', 'closed', 'cancelled'))");
    }

    public function down(): void
    {
        $stranded = DB::table('umrah.visa_groups')->where('includes_visa', false)->count();

        if ($stranded > 0) {
            throw new \RuntimeException(
                "Cannot roll back: {$stranded} transport-only group(s) exist. "
                .'Each would silently become a visa group with no visa vendor. Reassign them deliberately first.'
            );
        }

        DB::statement('ALTER TABLE umrah.visa_groups DROP CONSTRAINT IF EXISTS visa_groups_status_matches_kind_check');
        DB::statement('ALTER TABLE umrah.visa_groups DROP CONSTRAINT IF EXISTS visa_groups_sells_something_check');

        Schema::table('umrah.visa_groups', function (Blueprint $table) {
            $table->dropColumn('includes_visa');
        });
    }
};
```

- [ ] **Step 4: Update the model**

Add `'includes_visa'` to `$fillable`, `'includes_visa' => 'boolean'` to `$casts`, and:

```php
/**
 * The three middle statuses track a visa application. A transport-only
 * group never has one, so it is offered a shorter lifecycle -- draft,
 * delivered, closed -- with cancelled reachable throughout. The database
 * enforces the same rule, so this method decides what the UI offers, not
 * what the record may hold.
 */
public static function statusesAvailable(bool $includesVisa): array
{
    if ($includesVisa) {
        return self::STATUSES;
    }

    return array_intersect_key(self::STATUSES, array_flip([
        self::STATUS_DRAFT,
        self::STATUS_DELIVERED,
        self::STATUS_CLOSED,
        self::STATUS_CANCELLED,
    ]));
}
```

- [ ] **Step 5: Run the test, confirm it passes**

Run: `php artisan test tests/Feature/Umrah/GroupServiceChoiceTest.php 2>&1 | sed 's/\x1b\[[0-9;]*m//g'`
Expected: PASS, 3 tests.

- [ ] **Step 6: Commit**

```bash
rm -f bash.exe.stackdump
git add modules/Umrah/Database/Migrations/2026_08_23_000001_add_includes_visa_to_visa_groups.php modules/Umrah/Models/VisaGroup.php tests/Feature/Umrah/GroupServiceChoiceTest.php
git commit -m "Let a group say whether it sells a visa at all"
```

---

## Task 2: Validation, derivation and the seat rule

**Files:**
- Modify: `modules/Umrah/Http/Requests/StoreVisaGroupRequest.php`
- Modify: `modules/Umrah/Http/Requests/UpdateVisaGroupRequest.php`
- Modify: `modules/Umrah/Http/Controllers/VisaGroupController.php`
- Test: `tests/Unit/Umrah/StoreVisaGroupRequestTest.php`

**Interfaces:**
- Consumes: `VisaGroup::$includes_visa` from Task 1.
- Produces: the request now accepts `includes_visa` (boolean, required) and **ignores** any `passengers.*.service_type` / `passengers.*.transport_charge_amount` in the payload, replacing them with derived values.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Unit/Umrah/StoreVisaGroupRequestTest.php`:

```php
it('derives passenger service type from the group rather than the payload', function () {
    $request = makeStoreRequest([
        'includes_visa' => false,
        'transport_mode' => 'specialized',
        'passengers' => [
            ['full_name' => 'Ayesha Khan', 'service_type' => 'visa_transport', 'transport_charge_amount' => 900],
        ],
    ]);

    $passengers = $request->validated()['passengers'];

    expect($passengers[0]['service_type'])->toBe('transport_only')
        ->and($passengers[0]['transport_charge_amount'])->toBe(0.0);
});

it('rejects a group that sells neither visa nor transport', function () {
    expect(validationErrorsFor([
        'includes_visa' => false,
        'transport_mode' => 'none',
    ]))->toHaveKey('transport_mode');
});

it('raises the vehicle count when passengers exceed the seats booked', function () {
    // Fare's service seats 20; 45 passengers in 1 vehicle is 3 vehicles' worth.
    $request = makeStoreRequest([
        'transport_mode' => 'specialized',
        'transport_items' => [
            ['transport_fare_id' => $fareSeating20->id, 'quantity' => 1, 'passenger_count' => 45],
        ],
    ]);

    expect($request->validated()['transport_items'][0]['quantity'])->toBe(3);
});

it('leaves the vehicle count alone when capacity is unknown', function () {
    $request = makeStoreRequest([
        'transport_mode' => 'specialized',
        'transport_items' => [
            ['transport_fare_id' => $fareWithNullCapacity->id, 'quantity' => 1, 'passenger_count' => 45],
        ],
    ]);

    expect($request->validated()['transport_items'][0]['quantity'])->toBe(1);
});
```

Follow the file's existing helper conventions for building the request — read the top of the file first and match them rather than inventing `makeStoreRequest`/`validationErrorsFor` if equivalents already exist.

- [ ] **Step 2: Run and confirm failure**

Run: `php artisan test tests/Unit/Umrah/StoreVisaGroupRequestTest.php 2>&1 | sed 's/\x1b\[[0-9;]*m//g'`

- [ ] **Step 3: Add the rules**

In `rules()`:

```php
'includes_visa' => ['required', 'boolean'],
'transport_mode' => [
    'required',
    Rule::in(array_keys(VisaGroup::TRANSPORT_MODES)),
    function ($attribute, $value, $fail) {
        if (! $this->boolean('includes_visa') && $value === VisaGroup::TRANSPORT_NONE) {
            $fail('A group must sell a visa, transport, or both.');
        }
    },
],
```

Delete the `passengers.*.service_type` and `passengers.*.transport_charge_amount` rules entirely — the operator no longer supplies them.

- [ ] **Step 4: Derive and correct in `passedValidation()`**

```php
/**
 * The operator chooses once, for the group. Both passenger columns are
 * written from that choice rather than read from the payload, so a
 * hand-built request cannot put a passenger at odds with the group it
 * belongs to -- the mismatch that once billed self-arranged groups for a
 * bus they never bought.
 */
protected function passedValidation(): void
{
    $serviceType = $this->boolean('includes_visa')
        ? Passenger::SERVICE_VISA_TRANSPORT
        : Passenger::SERVICE_TRANSPORT_ONLY;

    $passengers = collect($this->input('passengers', []))
        ->map(fn (array $passenger) => [
            ...$passenger,
            'service_type' => $serviceType,
            'transport_charge_amount' => 0.0,
        ])
        ->all();

    $this->merge([
        'passengers' => $passengers,
        'transport_items' => $this->withSeatedVehicleCounts($this->input('transport_items', [])),
    ]);
}

/**
 * Seats booked must cover the passengers riding. A shortfall raises the
 * vehicle count rather than refusing the group -- the operator's intent is
 * plain, and the arithmetic is not theirs to redo. A fare whose service
 * has no pax_capacity states no capacity at all, so nothing is checked:
 * inventing one would price vehicles against a guess.
 */
private function withSeatedVehicleCounts(array $items): array
{
    return collect($items)->map(function (array $item) {
        $capacity = TransportFare::with('service:id,pax_capacity')
            ->find($item['transport_fare_id'] ?? null)?->service?->pax_capacity;

        if (! $capacity) {
            return $item;
        }

        $passengers = max((int) ($item['passenger_count'] ?? 0), 0);
        $needed = (int) ceil($passengers / $capacity);

        $item['quantity'] = max((int) ($item['quantity'] ?? 1), $needed, 1);

        return $item;
    })->all();
}
```

Add `use App\Modules\Umrah\Models\Passenger;` and `use App\Modules\Umrah\Models\TransportFare;` if absent.

- [ ] **Step 5: Mirror both changes into `UpdateVisaGroupRequest`**

Read it first. If it already extends `StoreVisaGroupRequest` or shares a trait, put the shared code there rather than duplicating it.

- [ ] **Step 6: Controller writes the column**

In `VisaGroupController::store()` and `update()`, include `'includes_visa' => $data['includes_visa']` where the group is written, and pass `includesVisa` plus `statuses` from `VisaGroup::statusesAvailable()` to the pages that render a status picker.

- [ ] **Step 7: Run the tests, confirm they pass**

Run: `php artisan test tests/Unit/Umrah/StoreVisaGroupRequestTest.php 2>&1 | sed 's/\x1b\[[0-9;]*m//g'`

- [ ] **Step 8: Commit**

```bash
rm -f bash.exe.stackdump
git add modules/Umrah/Http tests/Unit/Umrah/StoreVisaGroupRequestTest.php
git commit -m "Derive each passenger's service from the group that carries it"
```

---

## Task 3: The accounting line

**Files:**
- Modify: `modules/Umrah/Services/GroupAccountingService.php`
- Test: `tests/Feature/Umrah/GroupAccountingServiceTest.php`

**Interfaces:**
- Consumes: `VisaGroup::$includes_visa`.

Read the existing docblock at lines 45-80 before changing anything. It records a real bug — transport labels keyed off passenger `service_type` billed self-arranged groups for a bus they never bought — and the fix must not reintroduce that coupling.

- [ ] **Step 1: Write the failing test**

```php
it('raises no visa line for a transport-only group', function () {
    $group = VisaGroup::factory()->create([
        'includes_visa' => false,
        'transport_mode' => 'specialized',
        'visa_sale_amount' => 0,
    ]);

    $services = app(GroupAccountingService::class)->servicesFor($group);

    expect($services->pluck('service'))->not->toContain('Visa')
        ->and($services->pluck('service'))->not->toContain('Visa with mandatory transport');
});
```

Match the real method name on `GroupAccountingService` — read the class rather than trusting `servicesFor`.

- [ ] **Step 2: Run and confirm failure**

- [ ] **Step 3: Guard the visa line**

Wrap the existing `$services->push([... 'service' => $group->transport_mode === 'standard_bus' ? ... ])` block in `if ($group->includes_visa) { ... }`, and extend the docblock above it with one sentence saying a transport-only group raises no visa line at all.

- [ ] **Step 4: Run the test, confirm it passes**

Run: `php artisan test tests/Feature/Umrah/GroupAccountingServiceTest.php 2>&1 | sed 's/\x1b\[[0-9;]*m//g'`

- [ ] **Step 5: Commit**

```bash
rm -f bash.exe.stackdump
git add modules/Umrah/Services/GroupAccountingService.php tests/Feature/Umrah/GroupAccountingServiceTest.php
git commit -m "Bill no visa to a group that never bought one"
```

---

## Task 4: The form

**Files:**
- Modify: `modules/Umrah/Resources/js/pages/Umrah/Groups/Create.vue`
- Modify: `modules/Umrah/Resources/js/pages/Umrah/Groups/Show.vue`

No test file — this task is verified by the quad plus a read of the diff.

- [ ] **Step 1: Collapse the two questions into one radio group**

Replace the three-option "Transport" `RadioGroup` at roughly lines 1085-1135 with a four-option group titled **"What this group includes"**, bound to a new `form.service_choice` holding one of `visa_only`, `visa_standard_bus`, `visa_specialized`, `transport_only`. Labels and sub-labels exactly as the table in this plan. Keep the shadcn `RadioGroup`/`RadioGroupItem`/`Label` structure and the existing card; a four-item grid needs `md:grid-cols-2`, not `md:grid-cols-3`.

Derive the two stored fields from it:

```ts
const includesVisa = computed(() => form.service_choice !== 'transport_only');
const transportMode = computed(() =>
    form.service_choice === 'visa_only'
        ? 'none'
        : form.service_choice === 'visa_standard_bus'
          ? 'standard_bus'
          : 'specialized',
);
```

Then replace every `form.transport_mode` read in the file with `transportMode.value` (in script) or `transportMode` (in template), and delete `transport_mode` from the `useForm` object. The `watch(() => form.transport_mode, ...)` at line 423 becomes a watch on `form.service_choice` and no longer needs the two passenger-field resets.

- [ ] **Step 2: Remove the per-passenger fields**

- Delete `service_type` and `transport_charge_amount` from the `PassengerFormRow` type (lines 32-33) and from `emptyPassenger()` (lines 57-58).
- Delete both from `appendImportedMutamers()` (lines 472-473).
- Delete the `availablePassengerServiceTypes` computed (226-230) and the `transportOnlyCharges` computed (270-278).
- `visaPassengers` (216-220) becomes: every named passenger when `includesVisa`, none otherwise.
- In `updateVisaPricing()`, drop the `transportOnlyCharges` term from `form.transport_amount`, and return zero visa sale/cost when `!includesVisa`.
- Delete the two passenger-row form controls at roughly lines 1596-1660 (the `service_type` `Select` and the `transport_charge_amount` field), and drop the now-unused `passengerServiceTypes` prop from `defineProps` **and** from whatever the controller passes it.
- In `submit()`, drop the `service_type` and `transport_charge_amount` lines from the passenger map (615-616) — the server derives them — and add `includes_visa: includesVisa.value`, `transport_mode: transportMode.value`, `transport_required: transportMode.value !== 'none'`.

- [ ] **Step 3: Correct the vehicle count live**

Add beside the specialized items:

```ts
const capacityFor = (fareId: string) =>
    Number(fareFor(fareId)?.service?.pax_capacity || 0) || null;

/**
 * Mirrors the server rule so the operator sees the corrected count before
 * they submit rather than after. A fare whose service states no capacity
 * is left alone -- there is nothing to check against.
 */
const seatVehiclesFor = (item: TransportItemFormRow) => {
    const capacity = capacityFor(item.transport_fare_id);
    if (!capacity) return;

    const needed = Math.ceil(Math.max(Number(item.passenger_count || 0), 0) / capacity);
    if (needed > Number(item.quantity || 1)) {
        item.quantity = String(needed);
    }
};

watch(
    () => form.transport_items.map((item) => [item.transport_fare_id, item.passenger_count].join(':')),
    () => form.transport_items.forEach(seatVehiclesFor),
);
```

Beneath each item's quantity field show, when a capacity is known:

```
{{ capacityFor(item.transport_fare_id) }} seats per vehicle · {{ Number(item.quantity || 1) * (capacityFor(item.transport_fare_id) || 0) }} seats for {{ item.passenger_count || 0 }} passengers
```

as muted `text-xs`, so the raised count explains itself rather than appearing to change on its own.

- [ ] **Step 4: Show.vue**

Replace anything reading per-passenger `service_type` with the group's own choice, rendered as one line. Do not leave a hole in the layout where the removed passenger column was — close the grid up.

- [ ] **Step 5: Verify**

From `build/`, all four must hold:

```bash
npx vue-tsc --noEmit 2>&1 | grep -v "^resources/js/actions/" | grep -cE "error TS"   # 10 — the baseline, unchanged
node scripts/lint-palette.mjs                                                        # 0
node scripts/lint-nav.mjs                                                            # 7 definitions
npx vite build && git checkout -- public/build/manifest.json                          # exit 0
grep -rc "<form" modules resources --include=*.vue | awk -F: '{s+=$2} END {print s}'  # still 106
```

- [ ] **Step 6: Commit**

```bash
rm -f bash.exe.stackdump
git add modules/Umrah/Resources/js/pages/Umrah/Groups/
git commit -m "Ask once what the group includes"
```

---

## Verification

1. Full suite from `build/`: `php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | tail -20`. Baseline before this plan is **264 passed / 1196 assertions**; it must not fall, and Tasks 1-3 add 8 tests.
2. `php artisan migrate` then `php artisan migrate:rollback --step=1` — the rollback must refuse only if transport-only groups exist, and succeed cleanly otherwise.
3. Create one group of each of the four kinds by hand and confirm: the visa vendor picker is absent on transport-only; a specialized item with 45 passengers against a 20-seat vehicle shows 3 vehicles; the status picker on a transport-only group offers four states, not seven.

## Out of scope

- Dropping `passengers.service_type` and `passengers.transport_charge_amount` from the schema. They are now always derived and consistent; removing the columns is a separate change across 23 files.
- Backfilling existing mixed groups. Every current group has `includes_visa = true` by default, which is correct for all of them — no group could previously have been sold without a visa.
- The transport-only group's voucher template, which still says "Visa" in places.
