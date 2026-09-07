# Umrah commercial pricing verification — 7 September 2026

## Result

Passed the scoped Umrah regression, pricing, Quick Booking, and browser checks described below. One material browser defect was found and fixed: opening an agent rate for editing selected the first agent instead of retaining the saved agent. This report is local verification, not a production deployment record.

Branch: `codex/umrah-commercial-pricing`.
Application: `http://127.0.0.1:9002`, seeded Bab-al-Salam Travel company.
Automated feature tests use the separate `haasib_test` database.

## Automated evidence

| Run | Result |
| --- | --- |
| Existing full `tests/Feature/Umrah` suite | 344 passed, 1,887 assertions |
| `tests/Unit/Umrah` plus expanded CommercialPricingTest and QuickBookingTest | 69 passed, 383 assertions |
| Additional cross-company and manager lifecycle tests | 2 passed, 18 assertions |
| Production build after editor fix | Passed |
| ESLint for changed pricing component | Passed |
| Pint for changed PHP test files | Passed |
| `git diff --check` | Passed |

The runs overlap: 396 distinct test cases passed across these runs, not 415 distinct cases. Eighteen automated cases were added during this verification. No backend production code was changed.

Coverage includes:

- Agent, category, dated default, and legacy fallback precedence without stacking.
- Exact zero price, fixed discount, discount floor at zero, zero/full percentage discount, fractional rounding, fixed/percentage markup, and maximum markup.
- Inclusive first/last dates, adjacent date periods, expired defaults, open-ended rates, overlapping active rates, and conflicting reactivation.
- Supplier cost stays independent of agent discounts; omitted default cost falls back to the supplier's legacy cost.
- Category creation, assignment, duplicate names, and assigned-category deactivation protection.
- Owner and manager pricing access; accountant, operations, and agent denial for pricing reads and all pricing mutations.
- Foreign-company vendor, agent, and category references are rejected.
- Quick Booking service choices, invalid setup, duplicate submission/idempotency, voucher continuation permissions, and supplier-cost payload privacy.
- Commercial visa and standard-bus prices saved through Quick Booking, including ignoring client-supplied price tampering.
- Commercial specialized transport prices multiplied by vehicle quantity.
- Hotel pricing across multiple nightly rate periods; per-bed, room, and night calculations.
- Historical group pricing snapshots remain unchanged when rates change.
- Existing Umrah operations, vouchers, payments, refunds, tickets, and related accounting regressions in the full feature suite.

## Browser evidence

| Workflow | Observed result |
| --- | --- |
| Owner opens Setup and Commercial Pricing | Pages render and existing rates/categories appear |
| Submit rate without amount | Inline error and toast; no rule created |
| Submit overlapping default rate | Inline overlap error; no rule created |
| Duplicate category with different letter case | Inline duplicate-name error |
| Deactivate category with an assigned active agent | Error toast; category remains active |
| Submit discount above 100% | Inline error; existing percentage unchanged |
| Submit Quick Booking without agent | Required-agent error; no booking saved |
| Select QA Agent 2026 | Visa quote becomes PKR 850 |
| Change expected travel date to 31 August | Quote returns to legacy PKR 52,000 |
| Change expected travel date to 25 September | Quote returns to PKR 850 |
| Save visa-only booking | PKR 850 receivable, PKR 700 cost, PKR 150 profit; zero transport/hotel charges |
| Open QA agent's rate for editing | Initially reproduced incorrect Al-Noor selection; correct QA agent after fix |
| Edit agent rate to PKR 825 | Saved against QA Agent 2026; existing booking stays at PKR 850 after reload |
| Deactivate agent rate | Fresh QA agent quote uses category price PKR 900 |
| Restore and reactivate agent rate | Original PKR 850 rate restored |
| Open percentage category rate for editing | Correct category and 10% value preserved |
| Operations user login | Lands on Operations |
| Operations user opens pricing URL directly | 403; no rates exposed |

The editor defect came from deferred field watchers overwriting the restored scope after the saved record was loaded. Dependent defaults now run synchronously, before the saved target/scope/value is restored. The browser regression covered opening, saving, deactivating, restoring, and reopening rate forms.

## Local test data and limitations

- Added demo booking `E2E-PRICING-0907`, passenger `E2E Pricing Traveller`, travel date 25 September 2026. This booking intentionally remains available for manual inspection.
- Booking URL: `http://127.0.0.1:9002/demo-babalsalam-travel/umrah/groups/01a07b23-fa73-70c2-a0c7-3c71240445fa`.
- Existing demo commercial rates were restored to their original active values: default adult visa PKR 1,000; E2E Preferred discount 10%; QA agent exact price PKR 850.
- The production build triggered development-server asset reload errors in already-open tabs. Reloading after generation completed restored the pages; this was separate from the reproduced rate-editor defect.
- Existing font-resolution warnings and Xdebug log-path warnings remain environment warnings; the build and test commands succeeded.
- Browser checks sample the actual UI workflows above. Hotel nightly boundaries, all permission mutations, cross-company attacks, and the wider regression cases were exercised by automated tests, not individually replayed through every screen.
- No production deployment or production data changes were performed.

## Re-run commands

From `D:\projects\Haasib\build`:

```powershell
php artisan test tests/Feature/Umrah tests/Unit/Umrah
npm run build
npx eslint modules/Umrah/Resources/js/components/CommercialPricingWorkspace.vue
vendor/bin/pint --test tests/Feature/Umrah/CommercialPricingTest.php tests/Feature/Umrah/QuickBookingTest.php
```
