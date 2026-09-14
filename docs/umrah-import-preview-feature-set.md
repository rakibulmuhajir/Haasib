# Mutamer import preview and reliability

13 September 2026. Implemented locally; not deployed. Search remains on Groups/Vouchers, not a universal passenger-search feature.

## Workflow

Create Group → choose XLSX → Preview import → review physical spreadsheet row numbers and errors → explicitly exclude unwanted rows or fix the workbook and upload again → add selected valid passengers to the form → save the group.

- Preview never creates passengers, groups or accounting entries. Cancel leaves the form untouched.
- Nonempty rows with missing names are retained and flagged, not skipped. Validate name/passport lengths, whole-number age (0–130), recognized nationality, and duplicate passports within the file/current form.
- Unknown nationality is no longer silently replaced with Pakistan. Same-group duplicate validation also runs on the server at Create Group submission, ignoring passport case/spacing. The same passport on another journey is allowed.
- Re-uploading the same workbook cannot append passengers already in the form. An empty placeholder may be replaced, but partially entered identity data is preserved. Saving a partially filled unnamed passenger is blocked in the form rather than silently dropping it.
- Preview renders 50 rows per page; the import and save enforce a 500-passenger maximum. Header can occur after title rows, within the first 20 nonempty rows. Preserve text passports, including leading zeros, and Unicode names.
- Uploaded file limit remains 5 MB; reject workbooks exceeding 20 MB unpacked or 2,000 archive entries. Reject damaged XML, DTD/entity declarations and formula cells. Sparse cells no longer expand arbitrarily large arrays.
- Create Group sends a per-form UUID using the existing company-scoped idempotency column. Its command takes a transaction lock before replay lookup/create. Retrying returns the original same-agent group without duplicate passengers or charges. Reuse for another agent or cancelled/deleted group is rejected. A new form creates a new booking; this is not a global file/passport duplicate ban.
- FormRequest permissions, company context and linked-agent ownership remain in effect. Unexpected import/save errors use flash toasts; validation errors remain inline.

## Boundaries

Supported imported fields are still name, passport, age and nationality. Issued visa/MOFA numbers and dates have no corresponding passenger columns in the current schema and are **not claimed implemented** here. Confirm their mapping from a representative real Nusuk workbook before adding structured fields. No visa-status workflow or universal search was added. No migration is required for this slice.

## Acceptance evidence

Browser used a synthetic workbook only: physical rows 5, 8 and 9, containing a Unicode name and leading-zero passport, an unnamed/invalid-age/unknown-nationality row, and a duplicate passport. All rows were visible; Add was disabled until the invalid rows were explicitly unchecked. Cancel added nothing. The valid passenger appeared in the unsaved form with `001234` intact. Re-upload flagged the already-present passport. No group was saved and no charges were posted in the local demo during this browser check.

Automated cases cover row diagnostics, 500/501 boundary, unpacked-size limit, damaged XML/entities/formulas/missing headers/empty lists, no-write preview, authorization, same-group duplicates, HTTP save replay, command replay and separate repeat journeys. A posted visa purchase retry preserves transaction IDs and balances. Regression also includes QuickBookingTest, GroupServiceChoiceTest and DefaultGroupNameTest: **35 tests passed, 186 assertions**. Production build, targeted ESLint and PHP formatting passed. Results are also recorded in the release tracker.

Not deployed. Existing travelling-party and CSV work remains preserved; each feature still needs user acceptance and explicit release authorization.
