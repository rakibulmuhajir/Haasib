-- 20_inventory.sql — Inventory module (PostgreSQL)
-- Depends on: core. Optionally links to acct, acct_ar, acct_ap.
BEGIN;

CREATE SCHEMA IF NOT EXISTS inv;
SET search_path = inv, core, public;

-- =========================
-- Item categories
-- =========================
CREATE TABLE IF NOT EXISTS item_categories (
  category_id   BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  name          VARCHAR(255) NOT NULL,
  description   TEXT,
  parent_id     BIGINT REFERENCES inv.item_categories(category_id),
  is_active     BOOLEAN NOT NULL DEFAULT TRUE,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (company_id, name)
);

-- =========================
-- Items / products
-- =========================
CREATE TABLE IF NOT EXISTS items (
  item_id       BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  category_id   BIGINT REFERENCES inv.item_categories(category_id),
  sku           VARCHAR(100) NOT NULL,
  name          VARCHAR(255) NOT NULL,
  description   TEXT,
  unit_of_measure VARCHAR(50) NOT NULL DEFAULT 'unit',
  cost_price    DECIMAL(15,2) NOT NULL DEFAULT 0,
  selling_price DECIMAL(15,2) NOT NULL DEFAULT 0,
  currency_id   BIGINT NOT NULL REFERENCES core.currencies(currency_id),
  is_active     BOOLEAN NOT NULL DEFAULT TRUE,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (company_id, sku)
);

-- =========================
-- Warehouses / locations
-- =========================
CREATE TABLE IF NOT EXISTS warehouses (
  warehouse_id  BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  code          VARCHAR(50) NOT NULL,
  name          VARCHAR(255) NOT NULL,
  address       TEXT,
  city          VARCHAR(100),
  state         VARCHAR(100),
  postal_code   VARCHAR(20),
  country_id    BIGINT REFERENCES core.countries(country_id),
  is_active     BOOLEAN NOT NULL DEFAULT TRUE,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (company_id, code)
);

-- =========================
-- Stock levels per item per warehouse
-- =========================
CREATE TABLE IF NOT EXISTS stock_levels (
  stock_id      BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  warehouse_id  BIGINT NOT NULL REFERENCES inv.warehouses(warehouse_id) ON DELETE CASCADE,
  item_id       BIGINT NOT NULL REFERENCES inv.items(item_id) ON DELETE CASCADE,
  quantity      DECIMAL(15,3) NOT NULL DEFAULT 0,
  reorder_point DECIMAL(15,3) NOT NULL DEFAULT 0,
  max_stock     DECIMAL(15,3),
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (company_id, warehouse_id, item_id)
);

-- =========================
-- Stock movements (in, out, transfer, adjustment)
-- =========================
CREATE TABLE IF NOT EXISTS stock_movements (
  movement_id   BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  warehouse_id  BIGINT NOT NULL REFERENCES inv.warehouses(warehouse_id),
  item_id       BIGINT NOT NULL REFERENCES inv.items(item_id),
  movement_type VARCHAR(50) NOT NULL,  -- purchase, sale, adjustment, transfer_in, transfer_out
  quantity      DECIMAL(15,3) NOT NULL,
  unit_cost     DECIMAL(15,2),
  total_cost    DECIMAL(15,2),
  reference_id  BIGINT, -- e.g. bill_id, invoice_id
  reference_type VARCHAR(50),
  notes         TEXT,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_stock_movements_item ON stock_movements(item_id);
CREATE INDEX IF NOT EXISTS idx_stock_movements_wh ON stock_movements(warehouse_id);

-- =========================
-- Conditional cross-module FKs
-- =========================
DO $$
BEGIN
  -- Link stock movement to AR invoices
  IF EXISTS (
    SELECT 1 FROM information_schema.tables WHERE table_schema='acct_ar' AND table_name='invoices'
  ) THEN
    IF NOT EXISTS (
      SELECT 1 FROM pg_constraint WHERE conname='fk_stock_movement_invoice'
    ) THEN
      ALTER TABLE inv.stock_movements
        ADD CONSTRAINT fk_stock_movement_invoice FOREIGN KEY (reference_id)
        REFERENCES acct_ar.invoices(invoice_id) DEFERRABLE INITIALLY DEFERRED;
    END IF;
  END IF;

  -- Link stock movement to AP bills
  IF EXISTS (
    SELECT 1 FROM information_schema.tables WHERE table_schema='acct_ap' AND table_name='bills'
  ) THEN
    IF NOT EXISTS (
      SELECT 1 FROM pg_constraint WHERE conname='fk_stock_movement_bill'
    ) THEN
      ALTER TABLE inv.stock_movements
        ADD CONSTRAINT fk_stock_movement_bill FOREIGN KEY (reference_id)
        REFERENCES acct_ap.bills(bill_id) DEFERRABLE INITIALLY DEFERRED;
    END IF;
  END IF;
END$$;

COMMIT;
