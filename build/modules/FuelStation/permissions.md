# FuelStation Permissions

This module uses company-scoped permissions defined in `build/app/Constants/Permissions.php`.
When adding new features, add permissions there and update `build/config/role-permissions.php`.

## Permission catalog
| Area | Permission | Purpose |
| --- | --- | --- |
| Pumps | `pump.create` | Create pumps/nozzles |
| Pumps | `pump.view` | View pumps/nozzles |
| Pumps | `pump.update` | Update pumps/nozzles |
| Pumps | `pump.delete` | Delete pumps/nozzles |
| Tank Readings | `tank_reading.create` | Record tank readings |
| Tank Readings | `tank_reading.view` | View tank readings |
| Tank Readings | `tank_reading.update` | Update tank readings |
| Pump Readings | `pump_reading.create` | Record pump readings |
| Pump Readings | `pump_reading.view` | View pump readings |
| Rates | `fuel_rate.update` | Update fuel rates |
| Investors | `investor.create` | Create investors |
| Investors | `investor.view` | View investors |
| Investors | `investor.update` | Update investors |
| Handovers | `handover.create` | Record shift handovers |
| Handovers | `handover.view` | View handovers |
| Amanat | `amanat.deposit` | Record amanat deposits |
| Amanat | `amanat.withdraw` | Record amanat withdrawals |
| Sales | `fuel_sale.create` | Record fuel sales (daily close sales) |
| Daily Close | `daily_close.create` | Create daily close |
| Daily Close | `daily_close.view` | View daily close |
| Daily Close | `daily_close.amend` | Amend daily close |
| Daily Close | `daily_close.lock` | Lock daily close |
| Daily Close | `daily_close.unlock` | Unlock daily close |

## Default role mapping (FuelStation-specific)
No module-specific roles are required. This module uses the core roles from
`build/config/role-permissions.php`.

- Owner: all permissions (via `Permissions::all()`).
- Admin: full FuelStation access including amend/lock (no unlock).
- Accountant: operational access (create/view daily close, no amend/lock/unlock).
- Member: read-only access to operational views (daily close view only).

If you add a new permission, update the role matrix so onboarding and RBAC
sync remains consistent.
