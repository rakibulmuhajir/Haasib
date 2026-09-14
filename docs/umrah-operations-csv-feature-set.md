# Operations CSV export

Scope selected 13 September 2026. Implemented and locally tested; awaiting user acceptance, not deployed.

## One bounded release set

- Export Operations and its printable movement report to UTF-8 CSV for spreadsheet use.
- Reuse MovementReportService and its role-shaped OperationalEventTimelineService projection; no independent passenger queries, accounting writes or schema changes.
- Use applied screen filters. Preserve local dates/times without timezone conversion.
- Include the period-wide summary separately from the filtered event table, just like the screen/PDF. One row per event, with line-separated passenger details; totals are passenger movements, not unique travellers.
- Summary-only roles receive no manifest. Agents receive only permitted records, without driver-private information or supplier costs.
- Quote CSV correctly and neutralize formula-leading cells, including whitespace-prefixed formula payloads. Use Unicode BOM for Urdu names.
- Empty results remain a valid explanatory download. Unexpected export failures return to Operations with a Sonner flash error; no partial download.

Excluded: saved views, XLSX formatting, report redesign, new finance calculations, full packages and deployment. Downloads are handled by the browser's native download mechanism, not an Inertia JSON response. No synthetic success toast claims a file was saved.

## Acceptance

Verify date/event/readiness/agent filters, screen/report parity, local clocks, Unicode/commas/quotes/newlines, formula injection, empty exports, role/tenant restrictions, download headers, server-failure feedback, no source mutations, and button download in the browser.

## Verification — 13 September 2026

- `OperationsTest.php`: **45 passed / 533 assertions**. New cases compare CSV event rows, local date/time, period summaries and filtered counts to MovementReportService for all/arrivals/needs-attention/custom/empty results. Tests cover summary-role privacy, linked-agent driver privacy, foreign-company filtering, malformed filters, Unicode and CSV escaping, formula-prefix attacks, unchanged vouchers/journal counts, and export-failure flash feedback. CSV is included in the existing login/permission/module-access checks.
- Targeted ESLint and Pint passed; production build passed with existing font-resolution warnings. `git diff --check` passed.
- Browser: local demo Operations, 1 July–31 October 2026, airport arrivals needing attention. Screen showed **2 events / 26 passenger movements**; Export CSV downloaded `movement-report-2026-07-01-to-2026-10-31.csv` with the same filtered totals and 06:20 local times. Period summary values remain separately labelled (including 6 needs-attention events across the window).
- Changed From to 1 August without applying it, then exported again: the filename and content retained the displayed July–October dataset. Restored the input afterwards. No backend data changed during browser testing.
- The printable report toolbar's Export CSV link also produced a download with the retained report filters.
- Files are native browser downloads; the browser owns download progress/completion. CSV is not a styled XLSX workbook. Use Excel's text/CSV import when identifier columns need explicit text formatting (for example, leading-zero passports).

Release: no schema migration for this feature. Rebuild frontend assets on release. Prior uncommitted travelling-party work remains separate in scope and has not been declared deployed. No production actions performed.
