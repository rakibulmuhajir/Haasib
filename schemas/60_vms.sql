-- 60_vms.sql — Visitor Management System for Travel Agencies (PostgreSQL)
-- Depends on: core. Optionally links to crm (customers), acct_ar (invoices, payments), inv (items).
BEGIN;

CREATE SCHEMA IF NOT EXISTS vms;
SET search_path = vms, core, public;

-- =========================
-- Master: travel groups
-- =========================
CREATE TABLE IF NOT EXISTS groups (
  group_id      BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  group_number  VARCHAR(100) NOT NULL,
  name          VARCHAR(255) NOT NULL,
  description   TEXT,
  customer_id   BIGINT,                 -- crm.customers when present
  vendor_id     BIGINT,                 -- crm.vendors when present (operator)
  group_type    VARCHAR(50) NOT NULL DEFAULT 'tour', -- tour, business, family, individual
  departure_date DATE,
  return_date    DATE,
  destination_country_id BIGINT REFERENCES core.countries(country_id),
  status        VARCHAR(50) NOT NULL DEFAULT 'draft', -- draft, confirmed, in_progress, completed, cancelled
  total_members INTEGER NOT NULL DEFAULT 0 CHECK (total_members >= 0),
  total_cost    DECIMAL(15,2) NOT NULL DEFAULT 0,
  paid_amount   DECIMAL(15,2) NOT NULL DEFAULT 0,
  notes         TEXT,
  custom_fields JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by    BIGINT REFERENCES core.user_accounts(user_id),
  updated_by    BIGINT REFERENCES core.user_accounts(user_id),
  UNIQUE (company_id, group_number),
  CHECK (return_date IS NULL OR return_date > departure_date)
);

-- =========================
-- Visitors (travellers)
-- =========================
CREATE TABLE IF NOT EXISTS visitors (
  visitor_id    BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  group_id      BIGINT REFERENCES vms.groups(group_id) ON DELETE SET NULL,
  customer_id   BIGINT,  -- crm.customers when present
  visitor_number VARCHAR(100) NOT NULL,
  first_name    VARCHAR(255) NOT NULL,
  last_name     VARCHAR(255) NOT NULL,
  date_of_birth DATE,
  gender        VARCHAR(20),
  nationality_id BIGINT REFERENCES core.countries(country_id),
  passport_number VARCHAR(100),
  passport_issue_date DATE,
  passport_expiry_date DATE,
  passport_issue_country_id BIGINT REFERENCES core.countries(country_id),
  phone         VARCHAR(50),
  email         VARCHAR(255),
  emergency_contact_name VARCHAR(255),
  emergency_contact_phone VARCHAR(50),
  notes         TEXT,
  documents     JSONB NOT NULL DEFAULT '[]'::jsonb,
  custom_fields JSONB NOT NULL DEFAULT '{}'::jsonb,
  is_active     BOOLEAN NOT NULL DEFAULT TRUE,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (company_id, visitor_number),
  CHECK (passport_expiry_date IS NULL OR passport_issue_date IS NULL OR passport_expiry_date > passport_issue_date)
);

-- =========================
-- Services provided to visitors (visa, hotel, flight, transport, tour)
-- =========================
CREATE TABLE IF NOT EXISTS services (
  service_id    BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  visitor_id    BIGINT NOT NULL REFERENCES vms.visitors(visitor_id) ON DELETE CASCADE,
  customer_id   BIGINT,                 -- crm.customers when present
  vendor_id     BIGINT,                 -- crm.vendors when present
  service_type  VARCHAR(50) NOT NULL,   -- visa, hotel, flight, transport, tour, insurance
  service_name  VARCHAR(255) NOT NULL,
  description   TEXT,
  start_date    DATE,
  end_date      DATE,
  quantity      INTEGER NOT NULL DEFAULT 1 CHECK (quantity > 0),
  unit_price    DECIMAL(15,2) DEFAULT 0 CHECK (unit_price >= 0),
  total_price   DECIMAL(15,2) DEFAULT 0 CHECK (total_price >= 0),
  currency_id   BIGINT REFERENCES core.currencies(currency_id),
  status        VARCHAR(50) NOT NULL DEFAULT 'pending', -- pending, confirmed, completed, cancelled
  reference_number VARCHAR(100),
  confirmation_number VARCHAR(100),
  details       JSONB NOT NULL DEFAULT '{}'::jsonb,
  attachments   JSONB NOT NULL DEFAULT '[]'::jsonb,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- =========================
-- Bookings (order header) → can invoice via acct_ar
-- =========================
CREATE TABLE IF NOT EXISTS bookings (
  booking_id    BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  booking_number VARCHAR(100) NOT NULL,
  customer_id   BIGINT,                 -- crm.customers when present
  group_id      BIGINT REFERENCES vms.groups(group_id),
  booking_date  DATE NOT NULL DEFAULT CURRENT_DATE,
  status        VARCHAR(50) NOT NULL DEFAULT 'draft', -- draft, confirmed, invoiced, cancelled
  subtotal      DECIMAL(15,2) NOT NULL DEFAULT 0,
  tax_amount    DECIMAL(15,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  total_amount  DECIMAL(15,2) NOT NULL DEFAULT 0,
  currency_id   BIGINT REFERENCES core.currencies(currency_id),
  notes         TEXT,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by    BIGINT REFERENCES core.user_accounts(user_id),
  updated_by    BIGINT REFERENCES core.user_accounts(user_id),
  UNIQUE (company_id, booking_number)
);

CREATE TABLE IF NOT EXISTS booking_items (
  booking_item_id BIGSERIAL PRIMARY KEY,
  booking_id    BIGINT NOT NULL REFERENCES vms.bookings(booking_id) ON DELETE CASCADE,
  service_id    BIGINT REFERENCES vms.services(service_id) ON DELETE SET NULL,
  item_id       BIGINT,                -- inv.items when present (packages)
  description   VARCHAR(255) NOT NULL,
  quantity      INTEGER NOT NULL DEFAULT 1 CHECK (quantity > 0),
  unit_price    DECIMAL(15,2) NOT NULL DEFAULT 0 CHECK (unit_price >= 0),
  tax_amount    DECIMAL(15,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  line_total    DECIMAL(15,2) NOT NULL DEFAULT 0,
  sort_order    INTEGER NOT NULL DEFAULT 0
);

-- =========================
-- Vouchers (for hotels, flights, tours)
-- =========================
CREATE TABLE IF NOT EXISTS vouchers (
  voucher_id    BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  booking_id    BIGINT REFERENCES vms.bookings(booking_id) ON DELETE SET NULL,
  visitor_id    BIGINT REFERENCES vms.visitors(visitor_id) ON DELETE SET NULL,
  voucher_number VARCHAR(100) NOT NULL,
  voucher_type  VARCHAR(50) NOT NULL DEFAULT 'travel', -- travel, hotel, flight
  title         VARCHAR(255) NOT NULL,
  issue_date    DATE NOT NULL,
  valid_from    DATE,
  valid_until   DATE,
  details       JSONB NOT NULL DEFAULT '{}'::jsonb,
  terms_conditions TEXT,
  status        VARCHAR(50) NOT NULL DEFAULT 'active', -- active, used, expired, cancelled
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (company_id, voucher_number),
  CHECK (valid_until IS NULL OR valid_from IS NULL OR valid_until >= valid_from)
);

-- =========================
-- Itineraries
-- =========================
CREATE TABLE IF NOT EXISTS itineraries (
  itinerary_id  BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  booking_id    BIGINT NOT NULL REFERENCES vms.bookings(booking_id) ON DELETE CASCADE,
  title         VARCHAR(255) NOT NULL,
  start_date    DATE,
  end_date      DATE,
  notes         TEXT,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CHECK (end_date IS NULL OR start_date IS NULL OR end_date >= start_date)
);

CREATE TABLE IF NOT EXISTS itinerary_items (
  itinerary_item_id BIGSERIAL PRIMARY KEY,
  itinerary_id  BIGINT NOT NULL REFERENCES vms.itineraries(itinerary_id) ON DELETE CASCADE,
  day_index     INTEGER NOT NULL DEFAULT 1 CHECK (day_index > 0),
  start_time    TIME,
  end_time      TIME,
  activity_type VARCHAR(50) NOT NULL,   -- flight, hotel, tour, transport, meal, other
  title         VARCHAR(255) NOT NULL,
  location      VARCHAR(255),
  details       JSONB NOT NULL DEFAULT '{}'::jsonb
);

-- =========================
-- Conditional cross‑module links
-- =========================
DO $$
BEGIN
  -- Link to CRM customers/vendors if present
  IF EXISTS (SELECT 1 FROM information_schema.schemata WHERE schema_name='crm') THEN
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema='crm' AND table_name='customers') THEN
      IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='fk_vms_groups_customer') THEN
        ALTER TABLE vms.groups ADD CONSTRAINT fk_vms_groups_customer FOREIGN KEY (customer_id) REFERENCES crm.customers(customer_id);
      END IF;
      IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='fk_vms_visitors_customer') THEN
        ALTER TABLE vms.visitors ADD CONSTRAINT fk_vms_visitors_customer FOREIGN KEY (customer_id) REFERENCES crm.customers(customer_id);
      END IF;
      IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='fk_vms_bookings_customer') THEN
        ALTER TABLE vms.bookings ADD CONSTRAINT fk_vms_bookings_customer FOREIGN KEY (customer_id) REFERENCES crm.customers(customer_id);
      END IF;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema='crm' AND table_name='vendors') THEN
      IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='fk_vms_groups_vendor') THEN
        ALTER TABLE vms.groups ADD CONSTRAINT fk_vms_groups_vendor FOREIGN KEY (vendor_id) REFERENCES crm.vendors(vendor_id);
      END IF;
      IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='fk_vms_services_vendor') THEN
        ALTER TABLE vms.services ADD CONSTRAINT fk_vms_services_vendor FOREIGN KEY (vendor_id) REFERENCES crm.vendors(vendor_id);
      END IF;
    END IF;
  END IF;

  -- Link booking_items to inv.items when inventory module exists
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema='inv' AND table_name='items') THEN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='fk_vms_booking_items_item') THEN
      ALTER TABLE vms.booking_items ADD CONSTRAINT fk_vms_booking_items_item FOREIGN KEY (item_id) REFERENCES inv.items(item_id);
    END IF;
  END IF;

  -- Optional AR integration: create invoice from booking
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema='acct_ar' AND table_name='invoices') THEN
    IF NOT EXISTS (
      SELECT 1 FROM information_schema.columns WHERE table_schema='vms' AND table_name='bookings' AND column_name='invoice_id'
    ) THEN
      ALTER TABLE vms.bookings ADD COLUMN invoice_id BIGINT;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='fk_vms_bookings_invoice') THEN
      ALTER TABLE vms.bookings ADD CONSTRAINT fk_vms_bookings_invoice FOREIGN KEY (invoice_id) REFERENCES acct_ar.invoices(invoice_id);
    END IF;
  END IF;
END$$;

-- =========================
-- Suggested indexes
-- =========================
CREATE INDEX IF NOT EXISTS idx_vms_groups_company ON vms.groups(company_id);
CREATE INDEX IF NOT EXISTS idx_vms_visitors_company ON vms.visitors(company_id);
CREATE INDEX IF NOT EXISTS idx_vms_bookings_company ON vms.bookings(company_id);
CREATE INDEX IF NOT EXISTS idx_vms_services_visitor ON vms.services(visitor_id);

COMMIT;
