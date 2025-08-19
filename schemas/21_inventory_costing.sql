-- 21_inventory_costing.sql — Inventory Costing (PostgreSQL)
-- Depends on: core, inv(20), optional acct/acct_post for GL hooks
BEGIN;

SET search_path = inv, core, public;

-- ============================================================
-- Costing policy per company (Weighted Average or FIFO)
-- ============================================================
CREATE TABLE IF NOT EXISTS cost_policies (
  company_id   BIGINT PRIMARY KEY REFERENCES core.companies(company_id) ON DELETE CASCADE,
  method       VARCHAR(10) NOT NULL DEFAULT 'WA',  -- 'WA' weighted average, 'FIFO'
  effective_from DATE NOT NULL DEFAULT CURRENT_DATE,
  notes        TEXT,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- Per-item cost summary (one row per item+warehouse)
-- ============================================================
CREATE TABLE IF NOT EXISTS item_costs (
  item_cost_id  BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  item_id       BIGINT NOT NULL REFERENCES inv.items(item_id) ON DELETE CASCADE,
  warehouse_id  BIGINT NOT NULL REFERENCES inv.warehouses(warehouse_id) ON DELETE CASCADE,
  avg_unit_cost DECIMAL(15,6) NOT NULL DEFAULT 0,   -- WA moving average
  qty_on_hand   DECIMAL(18,3) NOT NULL DEFAULT 0,
  value_on_hand DECIMAL(18,2) NOT NULL DEFAULT 0,
  UNIQUE (company_id, item_id, warehouse_id)
);

-- ============================================================
-- FIFO layers (used when method='FIFO')
-- ============================================================
CREATE TABLE IF NOT EXISTS cost_layers (
  layer_id      BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  item_id       BIGINT NOT NULL REFERENCES inv.items(item_id) ON DELETE CASCADE,
  warehouse_id  BIGINT NOT NULL REFERENCES inv.warehouses(warehouse_id) ON DELETE CASCADE,
  source_type   VARCHAR(30) NOT NULL,  -- 'AP_BILL','ADJUSTMENT','TRANSFER_IN'
  source_id     BIGINT,
  layer_date    DATE NOT NULL,
  qty_remaining DECIMAL(18,3) NOT NULL CHECK (qty_remaining >= 0),
  unit_cost     DECIMAL(15,6) NOT NULL CHECK (unit_cost >= 0),
  total_cost    DECIMAL(18,2) GENERATED ALWAYS AS (qty_remaining * unit_cost) STORED
);
CREATE INDEX IF NOT EXISTS idx_cost_layers_item ON cost_layers(company_id, item_id, warehouse_id, layer_date);

-- Consumption mapping for FIFO issues
CREATE TABLE IF NOT EXISTS layer_consumptions (
  consumption_id BIGSERIAL PRIMARY KEY,
  layer_id       BIGINT NOT NULL REFERENCES inv.cost_layers(layer_id) ON DELETE CASCADE,
  movement_id    BIGINT NOT NULL REFERENCES inv.stock_movements(movement_id) ON DELETE CASCADE,
  qty_used       DECIMAL(18,3) NOT NULL CHECK (qty_used > 0),
  cost_amount    DECIMAL(18,2) NOT NULL CHECK (cost_amount >= 0)
);
CREATE INDEX IF NOT EXISTS idx_layer_consump_layer ON layer_consumptions(layer_id);

-- ============================================================
-- COGS entries (for WA or FIFO). Optionally posted to GL by app.
-- ============================================================
CREATE TABLE IF NOT EXISTS cogs_entries (
  cogs_id       BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  movement_id   BIGINT NOT NULL REFERENCES inv.stock_movements(movement_id) ON DELETE CASCADE,
  item_id       BIGINT NOT NULL REFERENCES inv.items(item_id),
  warehouse_id  BIGINT NOT NULL REFERENCES inv.warehouses(warehouse_id),
  qty_issued    DECIMAL(18,3) NOT NULL,
  unit_cost     DECIMAL(15,6) NOT NULL,
  cost_amount   DECIMAL(18,2) NOT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (movement_id)
);

-- ============================================================
-- Weighted Average implementation via trigger on stock_movements
-- Applies when cost_policies.method = 'WA'
-- Rules:
--  - purchase/transfer_in/adjustment positive qty increases stock and re-averages cost
--  - sale/transfer_out negative qty consumes at current avg cost and records COGS
-- ============================================================
CREATE OR REPLACE FUNCTION inv.trg_costing_wa() RETURNS TRIGGER AS $$
DECLARE
  v_policy TEXT; v_row item_costs%ROWTYPE; v_qty NUMERIC; v_cost NUMERIC; v_new_avg NUMERIC; v_wh BIGINT; v_it BIGINT; v_co BIGINT; v_issue_qty NUMERIC; v_cogs NUMERIC;
BEGIN
  v_co := NEW.company_id; v_wh := NEW.warehouse_id; v_it := NEW.item_id;
  SELECT method INTO v_policy FROM inv.cost_policies WHERE company_id=v_co;
  IF COALESCE(v_policy,'WA') <> 'WA' THEN RETURN NEW; END IF;

  v_qty := NEW.quantity;  -- can be + or -
  v_cost := COALESCE(NEW.unit_cost,0);

  -- ensure row in item_costs
  INSERT INTO inv.item_costs(company_id,item_id,warehouse_id)
  VALUES (v_co,v_it,v_wh)
  ON CONFLICT (company_id,item_id,warehouse_id) DO NOTHING;

  SELECT * INTO v_row FROM inv.item_costs
   WHERE company_id=v_co AND item_id=v_it AND warehouse_id=v_wh FOR UPDATE;

  IF v_qty > 0 THEN
    -- inbound: moving average
    v_new_avg := CASE WHEN (v_row.qty_on_hand + v_qty) = 0 THEN v_row.avg_unit_cost
                      ELSE (v_row.value_on_hand + (v_qty * v_cost)) / (v_row.qty_on_hand + v_qty) END;
    v_row.qty_on_hand := v_row.qty_on_hand + v_qty;
    v_row.avg_unit_cost := v_new_avg;
    v_row.value_on_hand := v_row.qty_on_hand * v_row.avg_unit_cost;
    UPDATE inv.item_costs SET qty_on_hand=v_row.qty_on_hand, avg_unit_cost=v_row.avg_unit_cost, value_on_hand=v_row.value_on_hand
    WHERE item_cost_id=v_row.item_cost_id;
  ELSIF v_qty < 0 THEN
    -- outbound: consume at avg cost
    v_issue_qty := -v_qty;
    v_cogs := round(v_issue_qty * v_row.avg_unit_cost, 2);
    v_row.qty_on_hand := v_row.qty_on_hand - v_issue_qty;
    v_row.value_on_hand := v_row.qty_on_hand * v_row.avg_unit_cost;
    UPDATE inv.item_costs SET qty_on_hand=v_row.qty_on_hand, value_on_hand=v_row.value_on_hand
    WHERE item_cost_id=v_row.item_cost_id;

    INSERT INTO inv.cogs_entries(company_id,movement_id,item_id,warehouse_id,qty_issued,unit_cost,cost_amount)
    VALUES (v_co, NEW.movement_id, v_it, v_wh, v_issue_qty, v_row.avg_unit_cost, v_cogs)
    ON CONFLICT (movement_id) DO NOTHING;
  END IF;
  RETURN NEW;
END; $$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS stock_movements_aiud_cost_wa ON inv.stock_movements;
CREATE TRIGGER stock_movements_aiud_cost_wa
AFTER INSERT OR UPDATE OF quantity, unit_cost ON inv.stock_movements
FOR EACH ROW EXECUTE FUNCTION inv.trg_costing_wa();

-- ============================================================
-- FIFO scaffolding (no trigger here to avoid heavy logic in DB)
-- App/job can populate cost_layers on inbound and layer_consumptions on outbound.
-- ============================================================

COMMIT;
