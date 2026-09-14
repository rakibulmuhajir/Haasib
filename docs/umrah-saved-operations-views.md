# Personal Operations saved views

13 September 2026. Implemented locally; not deployed.

## Use

Apply Operations filters, enter a view name and choose **Save applied filters**. Click its shortcut to reopen. Saving the same name replaces its filters. Remove with its × button; this deletes only the shortcut, which can be recreated.

Views are private to the current user and company. They store period, movement type, readiness and authorized agent filter. Relative periods do not store an anchor date and continue using the existing event-local clock behavior. Custom start/end dates remain fixed. No new timezone controls, accounting changes or universal search. Specific city-direction filtering is not introduced: current City transfers filter includes both directions.

Print/PDF/CSV retain the existing applied-filter pipeline after reopening a view. Saved filters are validated again when opened; deleted agent references can therefore require removing/recreating an old view rather than silently broadening it.

## Verification and release

Operations regression: **54 tests / 638 assertions passed**, including nine saved-view cases. Coverage includes save/list/update/delete, cross-user/company privacy, permission denial, invalid names/filters, rolling periods and fixed custom ranges. Production build, targeted ESLint and PHP formatting passed. Browser saved a synthetic Tomorrow/Airport arrivals view, switched to Today, reopened it and confirmed Tomorrow was restored, then removed that QA shortcut. No booking or accounting data was changed.

New migration: `2026_09_13_000001_create_operation_views.php`, applied locally only. Creates a UUID, company-RLS-protected table with user-scoped access. Deployment must include this migration before serving the updated Operations controller. User acceptance and release authorization remain required.
