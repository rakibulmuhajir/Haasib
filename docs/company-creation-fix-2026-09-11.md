# Company creation: optional currency and visible errors

Status: fixed and verified locally; not deployed.

## Cause

The form sends empty secondary-currency/rate fields when None is selected. Laravel converts empty strings to null. The rate's numeric/positive/decimal rules lacked `nullable`, so a single-currency company failed validation. The corresponding error was rendered only inside the hidden secondary-currency section, and this standalone page had neither an error callback nor a toast host.

## Changes

- Allow a null rate when the secondary currency is absent. Keep required, positive, numeric, precision and prohibited-without-currency checks.
- Use `User::class` for owner existence validation so it uses the same schema-qualified table and connection as the user model. The former string selected the separate `auth` connection, which also made newly inserted test users invisible inside the test transaction.
- Add an always-visible validation summary, Sonner error feedback and a toast host to the standalone creation page. Preserve submitted fields and the existing loading state.

## Verification

- 17 tests, 93 assertions passed: CompanyCreationCurrencyTest, CompanyIndustryOptionsTest, CompanyStoreRequestTest.
- Successful HTTP creation by a new regular user: empty strings, nulls, omitted optional inputs, and SAR with a positive rate. Verified owner membership, no bootstrap error, secondary rate persistence when selected, and successful access to the resulting Travel dashboard.
- Rejected HTTP cases save no company: missing/null/zero/negative/non-numeric/over-precision rate, rate without currency, secondary currency matching the base.
- Browser check on local server: Travel, Afghanistan/AFN, secondary None, blank name. Only the expected name error appears, both in the visible summary and beside the field; selected values remain. No secondary-rate error and no company created. Production build completion required a development-page version refresh before the settled browser check.
- Targeted ESLint and Pint passed. Production build passed with existing font asset warnings.

No production writes or deployment. The separately observed production queue-worker Redis extension issue is not changed by this fix.
