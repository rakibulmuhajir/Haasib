# Umrah browser acceptance — 14 September 2026

Tested in Codex's built-in browser against local port 9002. User reported import and partial name/passport search acceptance. No deployment performed.

## Passed in this browser run

- Owner Group search: lowercase reference with surrounding spaces; literal percent returns no matches; Clear restores the list.
- Voucher search: lowercase/spaced VCH-0002 finds its voucher; joined passenger QA-JOIN-0913-A1 finds receiving QA-JOIN-0913-B; literal underscore returns no matches.
- Seeded Al-Noor agent: VCH-0001 is visible; foreign VCH-0002 returns no matches.
- Saved view: save custom July–October arrivals, replace the same name without duplication, ignore an unapplied From-date edit, reopen and restore original dates and two matching movements.
- Saved view remains after logout/login and is not visible to the agent login.
- Removed only the temporary QA-0914 fixed arrivals shortcut; verified absence. Owner session restored. No booking/accounting data changed.
- Owner Operations and printable report agree on two airport arrivals (11 + 15 passengers), July 10 and July 28, both 6:20 AM local. Date-window summary is 26 moving in, 26 moving out, 26 Makkah-to-Madinah; six attention events across the window, distinct from the two filtered arrivals.
- Agent Operations and printable report show zero matching movements for that range, without the other agents' passengers or the owner's saved view.

## Not passed / remaining

- CSV download delivery and current file contents: clicked Export CSV on Operations and the report. No new file appeared in the usual Downloads folder; only September 13 exports were present. No visible application error. This is inconclusive, not proof of either successful delivery or an application bug. Need an observable completed download and file comparison before signing off CSV browser acceptance.
- Print-button popup delivery and PDF download were not fully verified. Navigating to the printable report itself works.
- Additional roles, cross-company browser checks, real next-day rollover, all filter combinations and large-list pagination were not exhaustively repeated in this run. Earlier automated coverage is separate evidence, not a claim of browser acceptance.
