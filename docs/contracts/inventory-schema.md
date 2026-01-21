# Schema Contract — Inventory & Products (inv)

Single source of truth for items, categories, warehouses, stock levels, movements, and costing. Read this before touching migrations, models, or services.

## Guardrails
- Schema: `inv` on `pgsql`.
- UUID primary keys with `public.gen_random_uuid()` default.
- Soft deletes via `deleted_at` on items, categories, warehouses.
- RLS required with company isolation + super-admin override.
- Models must set `$connection = 'pgsql'`, schema-qualified `$table`.
- Quantity precision: `numeric(18,3)` for fractional units.
- Cost precision: `numeric(15,6)` for unit cost; `numeric(15,2)` for totals.
- Stock movements are immutable after creation; adjustments create new movements.
- Costing methods: Weighted Average (WA) or FIFO per company.

## Tables

### inv.item_categories
- Purpose: hierarchical product categories.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `parent_id` uuid nullable FK → `inv.item_categories.id` (SET NULL/CASCADE).
  - `code` varchar(50) not null.
  - `name` varchar(255) not null.
  - `description` text nullable.
  - `is_active` boolean not null default true.
  - `sort_order` integer not null default 0.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at`, `deleted_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `code`) where deleted_at is null.
  - Index: `company_id`; `parent_id`; (`company_id`, `is_active`).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'inv.item_categories'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','parent_id','code','name','description','is_active','sort_order','created_by_user_id'];`
  - `$casts = ['company_id'=>'string','parent_id'=>'string','is_active'=>'boolean','sort_order'=>'integer','created_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Parent (self); hasMany Children (self); hasMany Item.
- Validation:
  - `code`: required|string|max:50; unique per company (soft-delete aware).
  - `name`: required|string|max:255.
  - `parent_id`: nullable|uuid|exists:inv.item_categories,id (same company).
- Business rules:
  - Max depth: 5 levels.
  - Cannot delete category with items; reassign or deactivate.
  - Parent must be same company.

### inv.items
- Purpose: products and services master.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `category_id` uuid nullable FK → `inv.item_categories.id` (SET NULL/CASCADE).
  - `sku` varchar(100) not null.
  - `name` varchar(255) not null.
  - `description` text nullable.
  - `item_type` varchar(30) not null default 'product'. Enum: product, service, non_inventory, bundle.
  - `unit_of_measure` varchar(50) not null default 'unit'.
  - `track_inventory` boolean not null default true.
  - `delivery_mode` varchar(30) not null default 'requires_receiving'. Enum: immediate, requires_receiving.
  - `is_purchasable` boolean not null default true.
  - `is_sellable` boolean not null default true.
  - `cost_price` numeric(15,6) not null default 0.00.
  - `selling_price` numeric(15,6) not null default 0.00.
  - `currency` char(3) not null FK → `public.currencies.code`.
  - `tax_rate_id` uuid nullable FK → `tax.tax_rates.id` (SET NULL/CASCADE).
  - `income_account_id` uuid nullable FK → `acct.accounts.id` (revenue account).
  - `expense_account_id` uuid nullable FK → `acct.accounts.id` (COGS account).
  - `asset_account_id` uuid nullable FK → `acct.accounts.id` (inventory asset).
  - `reorder_point` numeric(18,3) not null default 0.
  - `reorder_quantity` numeric(18,3) not null default 0.
  - `weight` numeric(10,3) nullable.
  - `weight_unit` varchar(10) nullable. Enum: kg, lb, g, oz.
  - `dimensions` jsonb nullable (keys: length, width, height, unit).
  - `barcode` varchar(100) nullable.
  - `manufacturer` varchar(255) nullable.
  - `brand` varchar(255) nullable.
  - `image_url` varchar(500) nullable.
  - `is_active` boolean not null default true.
  - `notes` text nullable.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at`, `deleted_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `sku`) where deleted_at is null.
  - Index: `company_id`; `category_id`; (`company_id`, `item_type`); (`company_id`, `is_active`); `barcode`.
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'inv.items'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','category_id','sku','name','description','item_type','unit_of_measure','track_inventory','delivery_mode','is_purchasable','is_sellable','cost_price','selling_price','currency','tax_rate_id','income_account_id','expense_account_id','asset_account_id','reorder_point','reorder_quantity','weight','weight_unit','dimensions','barcode','manufacturer','brand','image_url','is_active','notes','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','category_id'=>'string','track_inventory'=>'boolean','delivery_mode'=>'string','is_purchasable'=>'boolean','is_sellable'=>'boolean','cost_price'=>'decimal:6','selling_price'=>'decimal:6','tax_rate_id'=>'string','income_account_id'=>'string','expense_account_id'=>'string','asset_account_id'=>'string','reorder_point'=>'decimal:3','reorder_quantity'=>'decimal:3','weight'=>'decimal:3','dimensions'=>'array','is_active'=>'boolean','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Category; belongsTo TaxRate; belongsTo IncomeAccount; belongsTo ExpenseAccount; belongsTo AssetAccount; hasMany StockLevel; hasMany StockMovement.
- Validation:
  - `sku`: required|string|max:100; unique per company (soft-delete aware).
  - `name`: required|string|max:255.
  - `item_type`: required|in:product,service,non_inventory,bundle.
  - `unit_of_measure`: required|string|max:50.
  - `delivery_mode`: required|in:immediate,requires_receiving.
  - `currency`: required|string|size:3|uppercase.
  - `cost_price`: numeric|min:0.
  - `selling_price`: numeric|min:0.
  - `track_inventory`: boolean (must be true for product type).
  - `reorder_point`: numeric|min:0.
- Business rules:
  - Services and non_inventory types don't track_inventory.
  - delivery_mode governs when stock updates occur for purchasable items:
    - immediate: stock increases when the bill is received.
    - requires_receiving: stock increases only after goods receipt confirmation.
  - If track_inventory is false, delivery_mode must be immediate.
  - Cannot delete item with stock on hand; adjust to zero first.
  - Barcode must be unique if provided.
  - income_account_id for revenue; expense_account_id for COGS; asset_account_id for inventory.

### inv.warehouses
- Purpose: storage locations.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `code` varchar(50) not null.
  - `name` varchar(255) not null.
  - `address` text nullable.
  - `city` varchar(100) nullable.
  - `state` varchar(100) nullable.
  - `postal_code` varchar(20) nullable.
  - `country_code` char(2) nullable FK → `public.countries.code`.
  - `is_primary` boolean not null default false.
  - `is_active` boolean not null default true.
  - `notes` text nullable.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at`, `deleted_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `code`) where deleted_at is null.
  - Index: `company_id`; (`company_id`, `is_active`); (`company_id`, `is_primary`).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'inv.warehouses'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','code','name','address','city','state','postal_code','country_code','is_primary','is_active','notes','created_by_user_id'];`
  - `$casts = ['company_id'=>'string','is_primary'=>'boolean','is_active'=>'boolean','created_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships: belongsTo Company; hasMany StockLevel; hasMany StockMovement.
- Validation:
  - `code`: required|string|max:50; unique per company (soft-delete aware).
  - `name`: required|string|max:255.
  - `country_code`: nullable|string|size:2|exists:public.countries,code.
- Business rules:
  - Only one is_primary = true per company.
  - Cannot delete warehouse with stock; transfer first.

### inv.stock_levels
- Purpose: current quantity per item per warehouse.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `warehouse_id` uuid not null FK → `inv.warehouses.id` (CASCADE/CASCADE).
  - `item_id` uuid not null FK → `inv.items.id` (CASCADE/CASCADE).
  - `quantity` numeric(18,3) not null default 0.
  - `reserved_quantity` numeric(18,3) not null default 0 (committed to orders).
  - `available_quantity` numeric(18,3) generated always as (quantity - reserved_quantity) stored.
  - `reorder_point` numeric(18,3) nullable (override item default).
  - `max_stock` numeric(18,3) nullable.
  - `bin_location` varchar(50) nullable.
  - `last_count_date` date nullable.
  - `last_count_quantity` numeric(18,3) nullable.
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `warehouse_id`, `item_id`).
  - Index: `company_id`; `warehouse_id`; `item_id`; (`company_id`, `quantity`) where quantity < reorder_point.
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'inv.stock_levels'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','warehouse_id','item_id','quantity','reserved_quantity','reorder_point','max_stock','bin_location','last_count_date','last_count_quantity'];`
  - `$casts = ['company_id'=>'string','warehouse_id'=>'string','item_id'=>'string','quantity'=>'decimal:3','reserved_quantity'=>'decimal:3','available_quantity'=>'decimal:3','reorder_point'=>'decimal:3','max_stock'=>'decimal:3','last_count_date'=>'date','last_count_quantity'=>'decimal:3','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Warehouse; belongsTo Item.
- Business rules:
  - Updated by triggers on stock_movements.
  - quantity can go negative (backorder) if allowed by company settings.
  - reserved_quantity increased on sales order, decreased on fulfillment.
  - Reorder alert when available_quantity < reorder_point.

### inv.stock_movements
- Purpose: immutable log of all inventory changes.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `warehouse_id` uuid not null FK → `inv.warehouses.id` (RESTRICT/CASCADE).
  - `item_id` uuid not null FK → `inv.items.id` (RESTRICT/CASCADE).
  - `movement_date` date not null default current_date.
  - `movement_type` varchar(30) not null. Enum: purchase, sale, adjustment_in, adjustment_out, transfer_in, transfer_out, return_in, return_out, opening.
  - `quantity` numeric(18,3) not null. Positive for in, negative for out.
  - `unit_cost` numeric(15,6) nullable.
  - `total_cost` numeric(15,2) nullable.
  - `reference_type` varchar(100) nullable (e.g., 'acct.bills', 'acct.invoices').
  - `reference_id` uuid nullable.
  - `related_movement_id` uuid nullable FK → `inv.stock_movements.id` (for transfers).
  - `reason` varchar(255) nullable.
  - `notes` text nullable.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at` timestamp not null default now().
- Indexes/constraints:
  - PK `id`.
  - Index: `company_id`; `warehouse_id`; `item_id`; (`company_id`, `movement_date`); (`reference_type`, `reference_id`).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'inv.stock_movements'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','warehouse_id','item_id','movement_date','movement_type','quantity','unit_cost','total_cost','reference_type','reference_id','related_movement_id','reason','notes','created_by_user_id'];`
  - `$casts = ['company_id'=>'string','warehouse_id'=>'string','item_id'=>'string','movement_date'=>'date','quantity'=>'decimal:3','unit_cost'=>'decimal:6','total_cost'=>'decimal:2','reference_id'=>'string','related_movement_id'=>'string','created_by_user_id'=>'string','created_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Warehouse; belongsTo Item; belongsTo RelatedMovement (self).
- Validation:
  - `warehouse_id`: required|uuid|exists:inv.warehouses,id.
  - `item_id`: required|uuid|exists:inv.items,id.
  - `movement_date`: required|date.
  - `movement_type`: required|in:purchase,sale,adjustment_in,adjustment_out,transfer_in,transfer_out,return_in,return_out,opening.
  - `quantity`: required|numeric (non-zero).
- Business rules:
  - Movements are immutable; corrections create new adjustment movements.
  - Trigger updates stock_levels on insert.
  - Transfers create two movements: transfer_out and transfer_in.
  - unit_cost used for costing calculations.

### inv.stock_receipts
- Purpose: header for physical goods receipts (linked to bills). Supports partial receipts and variance tracking.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `bill_id` uuid nullable FK → `acct.bills.id` (SET NULL/CASCADE).
  - `receipt_date` date not null default current_date.
  - `notes` text nullable.
  - `variance_transaction_id` uuid nullable FK → `acct.transactions.id` (SET NULL/CASCADE).
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Index: `company_id`; `bill_id`; `receipt_date`.
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'inv.stock_receipts'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','bill_id','receipt_date','notes','variance_transaction_id','created_by_user_id'];`
  - `$casts = ['company_id'=>'string','bill_id'=>'string','receipt_date'=>'date','variance_transaction_id'=>'string','created_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Bill; belongsTo VarianceTransaction; hasMany StockReceiptLine; belongsTo User (created_by).
- Business rules:
  - One receipt can contain multiple lines; a bill line can appear in multiple receipts (partial deliveries).
  - Variance transaction is created only when variance exists on one or more lines.

### inv.stock_receipt_lines
- Purpose: per-line receipt details (expected vs actual), linked to bill lines and stock movements.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `stock_receipt_id` uuid not null FK → `inv.stock_receipts.id` (CASCADE/CASCADE).
  - `bill_line_item_id` uuid nullable FK → `acct.bill_line_items.id` (SET NULL/CASCADE).
  - `item_id` uuid not null FK → `inv.items.id` (RESTRICT/CASCADE).
  - `warehouse_id` uuid not null FK → `inv.warehouses.id` (RESTRICT/CASCADE).
  - `expected_quantity` numeric(18,3) not null.
  - `received_quantity` numeric(18,3) not null.
  - `variance_quantity` numeric(18,3) not null default 0.
  - `unit_cost` numeric(15,6) not null.
  - `total_cost` numeric(15,2) not null.
  - `variance_cost` numeric(15,2) not null default 0.
  - `variance_reason` varchar(50) nullable. Enum: transit_loss, spillage, temperature_adjustment, measurement_error, other.
  - `stock_movement_id` uuid nullable FK → `inv.stock_movements.id` (SET NULL/CASCADE).
  - `notes` text nullable.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Index: `company_id`; `stock_receipt_id`; `bill_line_item_id`; `item_id`; `warehouse_id`.
  - Check: `variance_reason` must be null or in enum list.
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'inv.stock_receipt_lines'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','stock_receipt_id','bill_line_item_id','item_id','warehouse_id','expected_quantity','received_quantity','variance_quantity','unit_cost','total_cost','variance_cost','variance_reason','stock_movement_id','notes','created_by_user_id'];`
  - `$casts = ['company_id'=>'string','stock_receipt_id'=>'string','bill_line_item_id'=>'string','item_id'=>'string','warehouse_id'=>'string','expected_quantity'=>'decimal:3','received_quantity'=>'decimal:3','variance_quantity'=>'decimal:3','unit_cost'=>'decimal:6','total_cost'=>'decimal:2','variance_cost'=>'decimal:2','variance_reason'=>'string','stock_movement_id'=>'string','created_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo StockReceipt; belongsTo BillLineItem; belongsTo Item; belongsTo Warehouse; belongsTo StockMovement; belongsTo User (created_by).
- Validation:
  - `expected_quantity`: required|numeric|min:0.01.
  - `received_quantity`: required|numeric|min:0.01.
  - `variance_reason`: required when variance_quantity != 0.
- Business rules:
  - `variance_quantity = received_quantity - expected_quantity`.
  - Stock movements are created for `received_quantity`.
  - If variance exists, post to GL using Transit Loss/Gain accounts.

### inv.cost_policies
- Purpose: costing method configuration per company.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null unique FK → `auth.companies.id` (CASCADE/CASCADE).
  - `method` varchar(10) not null default 'WA'. Enum: WA (Weighted Average), FIFO.
  - `effective_from` date not null default current_date.
  - `allow_negative_stock` boolean not null default false.
  - `notes` text nullable.
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique `company_id`.
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'inv.cost_policies'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','method','effective_from','allow_negative_stock','notes'];`
  - `$casts = ['company_id'=>'string','effective_from'=>'date','allow_negative_stock'=>'boolean','created_at'=>'datetime','updated_at'=>'datetime'];`
- Business rules:
  - Cannot change method after movements exist; would require recalculation.
  - WA simpler to implement; FIFO requires layer tracking.

### inv.item_costs
- Purpose: running cost summary per item per warehouse.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `item_id` uuid not null FK → `inv.items.id` (CASCADE/CASCADE).
  - `warehouse_id` uuid not null FK → `inv.warehouses.id` (CASCADE/CASCADE).
  - `avg_unit_cost` numeric(15,6) not null default 0.
  - `qty_on_hand` numeric(18,3) not null default 0.
  - `value_on_hand` numeric(18,2) not null default 0.
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `item_id`, `warehouse_id`).
  - Index: `company_id`; `item_id`.
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'inv.item_costs'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','item_id','warehouse_id','avg_unit_cost','qty_on_hand','value_on_hand'];`
  - `$casts = ['company_id'=>'string','item_id'=>'string','warehouse_id'=>'string','avg_unit_cost'=>'decimal:6','qty_on_hand'=>'decimal:3','value_on_hand'=>'decimal:2','created_at'=>'datetime','updated_at'=>'datetime'];`
- Business rules:
  - Updated by trigger when using WA method.
  - value_on_hand = qty_on_hand * avg_unit_cost.
  - Recalculated on inbound movements.

### inv.cost_layers (FIFO Only)
- Purpose: track cost layers for FIFO costing.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `item_id` uuid not null FK → `inv.items.id` (CASCADE/CASCADE).
  - `warehouse_id` uuid not null FK → `inv.warehouses.id` (CASCADE/CASCADE).
  - `source_type` varchar(30) not null. Enum: AP_BILL, ADJUSTMENT, TRANSFER_IN, OPENING.
  - `source_id` uuid nullable.
  - `layer_date` date not null.
  - `original_qty` numeric(18,3) not null.
  - `qty_remaining` numeric(18,3) not null; check >= 0.
  - `unit_cost` numeric(15,6) not null; check >= 0.
  - `total_cost` numeric(18,2) generated always as (qty_remaining * unit_cost) stored.
  - `created_at` timestamp not null default now().
- Indexes/constraints:
  - PK `id`.
  - Index: (`company_id`, `item_id`, `warehouse_id`, `layer_date`); `item_id`.
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'inv.cost_layers'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','item_id','warehouse_id','source_type','source_id','layer_date','original_qty','qty_remaining','unit_cost'];`
  - `$casts = ['company_id'=>'string','item_id'=>'string','warehouse_id'=>'string','source_id'=>'string','layer_date'=>'date','original_qty'=>'decimal:3','qty_remaining'=>'decimal:3','unit_cost'=>'decimal:6','total_cost'=>'decimal:2','created_at'=>'datetime'];`
- Business rules:
  - Created on inbound movements.
  - Consumed in FIFO order on outbound.
  - qty_remaining decremented as units sold.
  - Layers with qty_remaining = 0 are exhausted.

### inv.cogs_entries
- Purpose: record COGS at time of sale.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `movement_id` uuid not null unique FK → `inv.stock_movements.id` (CASCADE/CASCADE).
  - `item_id` uuid not null FK → `inv.items.id` (RESTRICT/CASCADE).
  - `warehouse_id` uuid not null FK → `inv.warehouses.id` (RESTRICT/CASCADE).
  - `qty_issued` numeric(18,3) not null.
  - `unit_cost` numeric(15,6) not null.
  - `cost_amount` numeric(18,2) not null.
  - `gl_transaction_id` uuid nullable FK → `acct.transactions.id` (SET NULL/CASCADE).
  - `created_at` timestamp not null default now().
- Indexes/constraints:
  - PK `id`.
  - Unique `movement_id`.
  - Index: `company_id`; `item_id`.
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'inv.cogs_entries'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','movement_id','item_id','warehouse_id','qty_issued','unit_cost','cost_amount','gl_transaction_id'];`
  - `$casts = ['company_id'=>'string','movement_id'=>'string','item_id'=>'string','warehouse_id'=>'string','qty_issued'=>'decimal:3','unit_cost'=>'decimal:6','cost_amount'=>'decimal:2','gl_transaction_id'=>'string','created_at'=>'datetime'];`
- Business rules:
  - Created on sale movements.
  - unit_cost from WA average or FIFO layer(s).
  - Links to GL transaction for COGS posting.

## Costing Formulas

### Weighted Average (WA)
```
On inbound:
  new_avg_cost = (existing_value + incoming_value) / (existing_qty + incoming_qty)

On outbound:
  cogs = issued_qty * current_avg_cost
```

### FIFO
```
On outbound:
  consume layers in order of layer_date (oldest first)
  for each layer:
    take min(needed_qty, layer.qty_remaining)
    cogs += taken_qty * layer.unit_cost
    layer.qty_remaining -= taken_qty
    needed_qty -= taken_qty
  until needed_qty = 0
```

## Enums Reference

### Item Type
| Type | Track Inventory | Description |
|------|-----------------|-------------|
| product | Yes | Physical goods |
| service | No | Services rendered |
| non_inventory | No | Supplies, consumables |
| bundle | Optional | Kit of multiple items |

### Movement Type
| Type | Quantity Sign | Description |
|------|---------------|-------------|
| purchase | Positive | Received from vendor |
| sale | Negative | Sold to customer |
| adjustment_in | Positive | Manual increase |
| adjustment_out | Negative | Manual decrease |
| transfer_in | Positive | From another warehouse |
| transfer_out | Negative | To another warehouse |
| return_in | Positive | Customer return |
| return_out | Negative | Return to vendor |
| opening | Either | Opening balance |

## Form Behaviors

### Item Form
- Fields: sku, name, category_id, item_type, unit_of_measure, cost_price, selling_price, currency, tax_rate_id, track_inventory, is_purchasable, is_sellable, reorder_point, income_account_id, expense_account_id, asset_account_id, barcode, image
- item_type controls visibility of inventory fields
- Account dropdowns filtered by appropriate type
- Barcode scanner integration option

### Stock Adjustment Form
- Select warehouse, item
- Enter adjustment quantity (positive or negative)
- Select reason from predefined list or enter custom
- Shows current qty and new qty preview
- Creates stock_movement with adjustment_in/out type

### Transfer Form
- Select source warehouse, destination warehouse, item
- Enter transfer quantity (cannot exceed available)
- Creates two movements: transfer_out and transfer_in
- Links movements via related_movement_id

### Stock Count Form
- Select warehouse
- List items with expected qty
- Enter counted qty
- System calculates variance
- Approve creates adjustment movements

## Out of Scope (v1)
- Serial number tracking.
- Lot/batch tracking.
- Expiry date management.
- Multiple units of measure per item.
- Bill of Materials (BOM) for manufacturing.
- Purchase orders / sales orders.
- Cycle counting schedules.

## Extending
- Add new movement_type values here first.
- Serial/lot tracking would add `inv.serial_numbers` and `inv.lots` tables.
- BOM would add `inv.bom_headers` and `inv.bom_lines` tables.
