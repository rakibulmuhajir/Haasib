# Voucher contacts, footer defaults and compact print

Released — user confirmed on 9 September 2026. Exact deployed commit/version and live smoke checks have not been independently verified. See [release tracker](umrah-release-tracker.md). The dated verification notes below retain their historical local-only status.

## User workflow

1. Open Umrah Setup → Voucher settings. Choose Company, an Agent, or a Visa/Transport provider.
2. Save representatives with responsibility, name, company/agent, city, phone and optional WhatsApp. Save optional footer/terms. Save is available above and below the form.
3. Agent and provider detail pages link directly to their voucher profile. Only users with `umrah.settings.update` manage defaults (owner/manager in the standard role matrix).
4. New vouchers prefill company and selected-agent contacts (up to 12). A nonempty agent footer replaces the company footer. Provider contacts are explicit directory choices, independently selectable for each responsibility; no assumption that visa and transport suppliers are the same.
5. Edit the selected contacts/footer for that voucher. Drafts remain editable under existing role/cutoff rules. Approved copies require an amendment. Defaults never refresh an existing voucher implicitly; intentional empty contacts/footer stay empty.

## Print

- Browser Print and Export PDF use the same server-rendered layout and access checks.
- A4 layout; four header positions for available company, agent, visa and transport logos. Providers outside the voucher's service bundle are not advertised.
- Passengers, accommodation, side-by-side flights, transport and named journey contacts.
- Accommodation columns: City, Hotel, Location QR, Rooms, Check-in, Checkout, Nights; aligned total nights below. Meals removed from the voucher form/print by user request. Clerk-entered stay notes are optional.
- Passenger print columns: number, name, passport and age; nationality/date of birth/visa status are not printed. Age uses travel date, with imported age as fallback.
- New voucher entry starts with three Makkah/Madinah/Makkah stays. Nights and checkout calculate each other; checkout carries into the next check-in while retaining subsequent night counts. Rows remain removable.
- A verified Google Maps URL is entered on the stay. QR generation is local; it does not guess a hotel entrance or send passengers to a third-party QR service.
- Local airport times remain unchanged. Empty footer/notes are hidden. Long groups flow to additional pages rather than being clipped.
- Existing records without a print snapshot remain without one. They are not retroactively assigned today's contacts.

## Verification

- Full Feature/Umrah suite: **382 passed, 2,062 assertions** (included the first 20 new profile tests).
- Expanded final profile suite: **26 passed, 105 assertions**, including creation defaults/explicit snapshots and owner/manager versus operations/accountant/agent setup permissions.
- Negative coverage: blank required contact fields, overlong footer, too many contacts, unexpected profile keys, missing contacts array, unsafe/non-map URLs, foreign-company targets, unauthorized setup, and attempts to mutate an approved voucher.
- Snapshot checks: agent footer precedence, independent supplier choices, edit/clear, approved reprint after source changes, amendment copying.
- Browser: required-field errors, saving with bottom button, reload persistence, new-voucher prefill, group selection, voucher-specific representative, print route and cleared defaults leaving the saved voucher intact.
- Synthetic PDF: eight passengers, three QR-bearing stays, flights and three city representatives rendered to **one A4 page**, inspected as PNG. Fixed duplicate screen padding in Dompdf by rendering with print media.
- Production build passed. Focused ESLint, PHP syntax, Pint and git whitespace checks passed. Existing font-resolution/Xdebug-log warnings remain unrelated.

## Acceptance rerun - 7 September 2026

- Reran profile, draft editing, approval/amendment and amendment accounting suites: **38 passed, 131 assertions**.
- Added explicit full-package and visa-plus-transport print/PDF cases: **2 passed, 36 assertions**. Both cover four logo slots, journey contacts, footer and unchanged local flight times; accommodation/total nights appear only for the hotel package.
- This acceptance run totals **40 passed, 167 assertions**; the earlier full 382-test run above was not repeated.
- Browser verified validation errors, defaults save/reload, representative picker closing after selection, and copying the chosen representative into voucher contact fields. The saved draft print retained its original contact/footer while company defaults were different.
- Temporary company test contacts/footer were cleared and verified after reload. The unsaved picker experiment did not change the existing QA voucher.
- Rechecked the existing eight-passenger PDF sample: one A4 page, with accommodation before flights and aligned total nights. Physical printing and phone-camera QR scanning remain manual checks.
- Focused Pint and git whitespace checks passed. No production deployment performed.

## Local rollout

### Print-style voucher view - 9 September 2026

- Voucher detail now opens on an A4-width, white-paper preview using the existing authorized print endpoint, rather than duplicating its layout in Vue.
- Actions stay above the paper. Existing details/history remain in a separate tab; the accounting action retains its permission check. Workflow dialogs remain mounted and usable from the preview.
- Preview waits for fonts/images, expands for longer content, supports horizontal scrolling on narrow screens, and includes timeout/error/retry states. It refreshes when voucher status/revision changes. This is a continuous HTML preview, not a guarantee of exact PDF page breaks.
- Verified the local 15-passenger VCH-0003 preview, internal tab and opening/dismissing its amendment dialog without modifying the voucher. Two full-package/visa-transport print tests passed (36 assertions); production build, focused ESLint and whitespace checks passed. Not deployed.

### Logo upload repair - 8 September 2026

- Reproduced the generic browser upload failure with `E:/logos/asanUmraLogo.png` (below the old size limit). Replaced the standalone fetch/static CSRF-token upload with an Inertia multipart form, redirect, and flashed uploaded URL.
- Raised party uploads from 300 KB to 2 MB; PNG/JPEG/WebP remain supported and converted to PNG. SVG is not accepted.
- Restored the missing local `public/storage` link using `php artisan storage:link`. Logo URLs now use the same-origin public path, independently of the default disk/APP_URL.
- Agent, hotel-provider, transport-provider and visa-provider forms now accept this company's uploaded PNG paths as well as legacy HTTP/HTTPS links. Foreign-company storage paths and traversal paths are rejected.
- Uploading no longer deletes the currently saved party logo before the parent form succeeds. Storage failures return a validation error rather than a bogus success URL.
- Nine focused service/size tests passed (18 assertions); two upload/save/path-validation tests passed (15 assertions); both four-logo voucher print/PDF cases passed (36 assertions). Production build, focused ESLint, Pint and whitespace checks passed.
- Browser verified the user's small PNG and 610 KB Aivolutive PNG display at a 600-pixel maximum edge. Saved the latter on the existing QA agent and verified it after reload, then restored that agent's original empty logo. Source images were not changed. No deployment performed.

Migration `2026_09_07_000002_add_voucher_print_profiles.php` adds nullable JSON columns on agents, visa vendors and vouchers. Existing tenant/RLS policies cover them. Company defaults use a documented settings key, preserving other company settings. Applied to the local development database only.

Apply that migration alongside the application build during a future release. Do not run migrate:fresh. No production deployment was performed.

## Deliberate limits

- Passenger bed allocation, visa/PNR fields and Ziyarat are not invented where the current voucher data model does not supply them.
- Logos use existing local uploaded raster assets; remote legacy logo URLs are not fetched by the PDF renderer.
- Single-page verification covers a typical eight-passenger sample, not an arbitrary group size or a long terms document. Physical QR scanning still deserves a check on the user's printer.
- Defaults are managed by company staff; external agent logins can choose/edit voucher contacts only through their existing voucher permissions, not edit the shared directory.

Demo QA draft `UVR-00002` was created on the existing hotel-only QA booking to verify contact snapshots. It is unapproved, has synthetic E2E contact details and no hotel charges. Temporary company footer/contact defaults were cleared afterward.
