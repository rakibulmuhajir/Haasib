# Bank ledger and Amanat retest

The browser retest of `9e4df4a5` exposed two separate problems:

- Main Bank pointed to the Cash on Hand ledger. Accepting that ledger as a bank could debit and credit the same account during a withdrawal.
- Bank transaction request validation used `acct.accounts` as a validation table string. Laravel interprets the prefix as a connection name; that connection does not share the active `pgsql` session's RLS context. Even a valid bank ledger was rejected under enforcement. Validation now uses the Account model's connection and remains company scoped.

## Changes

- Bank transaction choices and posting validation require bank-subtype ledgers. Removed the linked-account subtype bypass.
- Create and edit bank forms offer **Create automatically**. On edit, explicitly selecting it creates and links a separate ledger atomically, provided the old link has no activity.
- Updates reject cash/bank subtype mismatches and ledgers belonging to another company or another bank record.
- Bank-feed transactions and direct ledger entries both lock ledger linkage, account type, currency and opening balance. A locked legacy record can still be deactivated without changing its old link.
- Amanat uses the selected active cash/bank ledger directly. Removed the fallback OR query that could ignore the selected account ID.
- Creating an Amanat liability in a fresh company now supplies its required credit normal balance and the standard other-current-liability subtype.

## Browser retest after deployment

1. Open Banking → Accounts → Main Bank → Edit.
2. If its ledger is editable, select **Create automatically**, keep Checking and PKR, and save.
3. If the ledger is locked because Cash on Hand already has history, create a new bank record (for example **Main Bank — corrected**) with a distinct account number and **Create automatically**. Deactivate the legacy bank record if it is no longer needed. Do not delete or reclassify Cash on Hand.
4. Verify the new ledger is separate from 1050 Cash on Hand and appears in Bank Transactions and Amanat.
5. Post the 25,000 PKR withdrawal. Verify debit Cash on Hand / credit the new bank ledger.
6. Post the 60,000 PKR Amanat deposit into the new bank ledger. Verify debit bank / credit Amanat liability, with no increase in drawer cash.

## Verification

Executed with `DB_USERNAME=haasib_app`, `DB_MIGRATOR_USERNAME=postgres`, and `RLS_ENFORCEMENT=on` against `haasib_test`:

- Banking mapping, bank movement, and Amanat regressions: 22 tests, 109 assertions passed before the final legacy-deactivation regression was added.
- Final bank mapping suite, including legacy deactivation: 9 tests, 50 assertions passed.
- The HTTP regression repairs a cash-linked bank, posts the 25,000 PKR withdrawal, checks both ledger sides, deposits 60,000 PKR Amanat into the selected bank, and rejects relinking after activity.
- `npm run build`: passed. Existing font asset resolution warnings remain.
- `git diff --check`: passed.
- Repository validation guidance references `composer quality-check` and `php artisan layout:validate`; this checkout has no quality-check composer script or layout artisan namespace. No migration was added.

These are local automated results. Production data has not been repaired or browser-retested by this change.
