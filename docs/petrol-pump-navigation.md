# Petrol pump navigation

Implemented 2026-09-14. Fuel stations replace the general module navigation with seven flat groups:

- Daily Close: direct workspace entry, with Current Close and History links on close pages. View-only users enter through History.
- Stock: overview, deliveries, movements, fuel prices, and Products You Sell (the existing company-home product workspace).
- Purchases: bills, bill payments, vendors, and vendor card settlement.
- Customers: all customers, credit customers, and Amanat depositors.
- Team & Partners: employees, payroll, salary advances, partners, and enabled investors.
- Reports: performance, profitability, expenses, profit summary, stock variance, and salary report.
- Settings: station configuration, equipment, bank accounts, setup, help, and permitted advanced accounting links.

The horizontal menu switches to a navigation drawer below 1280px to avoid crowding. Fuel stations also have Daily Close, Stock, and More shortcuts at those widths. The drawer closes after navigation. Detail pages and query-string filters preserve the active area; the longest matching destination wins.

FuelNavigationAccess supplies navigation visibility from existing company permissions and the station's has_investors setting. Payroll and inventory links also respect enabled modules. Navigation visibility complements existing backend authorization; it does not replace route/request checks. The product currently uses the single plain-language lexicon, not an accountant-mode toggle.

Validation: targeted ESLint, Vue SFC compilation, PHP syntax/style checks, permission constant checks, and navigation smoke checks passed. Full type checking reports existing errors outside the changed navigation files. The documented layout:validate command and composer quality-check script do not exist in this checkout. The local preview loads, but the documented admin test credentials were rejected, so authenticated visual verification remains pending.
