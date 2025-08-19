-- 51_payroll_extended.sql — Payroll Extended (PostgreSQL)
-- Depends: 50_payroll.sql, 00_core.sql
BEGIN;

CREATE SCHEMA IF NOT EXISTS pay;
SET search_path = pay, core, public;

CREATE TABLE IF NOT EXISTS earning_types (
  earning_type_id BIGSERIAL PRIMARY KEY,
  company_id BIGINT NOT NULL REFERENCES core.companies(company_id),
  code TEXT NOT NULL,
  name TEXT NOT NULL,
  taxable BOOLEAN NOT NULL DEFAULT TRUE,
  affects_overtime BOOLEAN NOT NULL DEFAULT TRUE,
  UNIQUE (company_id, code)
);

CREATE TABLE IF NOT EXISTS deduction_types (
  deduction_type_id BIGSERIAL PRIMARY KEY,
  company_id BIGINT NOT NULL REFERENCES core.companies(company_id),
  code TEXT NOT NULL,
  name TEXT NOT NULL,
  pre_tax BOOLEAN NOT NULL DEFAULT TRUE,
  UNIQUE (company_id, code)
);

CREATE TABLE IF NOT EXISTS benefit_plans (
  benefit_plan_id BIGSERIAL PRIMARY KEY,
  company_id BIGINT NOT NULL REFERENCES core.companies(company_id),
  code TEXT NOT NULL,
  name TEXT NOT NULL,
  provider TEXT,
  employee_contrib_rate NUMERIC(7,4) DEFAULT 0,
  employer_contrib_rate NUMERIC(7,4) DEFAULT 0,
  currency_id BIGINT REFERENCES core.currencies(currency_id),
  UNIQUE (company_id, code)
);

CREATE TABLE IF NOT EXISTS employee_benefits (
  employee_benefit_id BIGSERIAL PRIMARY KEY,
  company_id BIGINT NOT NULL REFERENCES core.companies(company_id),
  employee_id BIGINT NOT NULL REFERENCES pay.employees(employee_id),
  benefit_plan_id BIGINT NOT NULL REFERENCES pay.benefit_plans(benefit_plan_id),
  start_date DATE NOT NULL,
  end_date DATE,
  employee_fixed NUMERIC(15,2),
  employer_fixed NUMERIC(15,2),
  UNIQUE (employee_id, benefit_plan_id)
);

CREATE TABLE IF NOT EXISTS leave_types (
  leave_type_id BIGSERIAL PRIMARY KEY,
  company_id BIGINT NOT NULL REFERENCES core.companies(company_id),
  code TEXT NOT NULL,
  name TEXT NOT NULL,
  paid BOOLEAN NOT NULL DEFAULT TRUE,
  accrual_rate_hours NUMERIC(7,3) DEFAULT 0,
  UNIQUE (company_id, code)
);

CREATE TABLE IF NOT EXISTS leave_requests (
  leave_request_id BIGSERIAL PRIMARY KEY,
  company_id BIGINT NOT NULL REFERENCES core.companies(company_id),
  employee_id BIGINT NOT NULL REFERENCES pay.employees(employee_id),
  leave_type_id BIGINT NOT NULL REFERENCES pay.leave_types(leave_type_id),
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  hours NUMERIC(7,2) NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending', -- pending, approved, rejected, taken
  approved_by BIGINT REFERENCES core.user_accounts(user_id),
  approved_at TIMESTAMP,
  notes TEXT,
  CHECK (end_date >= start_date)
);

CREATE TABLE IF NOT EXISTS payslips (
  payslip_id BIGSERIAL PRIMARY KEY,
  company_id BIGINT NOT NULL REFERENCES core.companies(company_id),
  payroll_period_id BIGINT NOT NULL REFERENCES pay.payroll_periods(period_id),
  employee_id BIGINT NOT NULL REFERENCES pay.employees(employee_id),
  number TEXT NOT NULL,
  currency_id BIGINT NOT NULL REFERENCES core.currencies(currency_id),
  gross_pay NUMERIC(15,2) NOT NULL DEFAULT 0,
  total_earnings NUMERIC(15,2) NOT NULL DEFAULT 0,
  total_deductions NUMERIC(15,2) NOT NULL DEFAULT 0,
  employer_costs NUMERIC(15,2) NOT NULL DEFAULT 0,
  net_pay NUMERIC(15,2) NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'draft', -- draft, approved, paid
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (company_id, number),
  UNIQUE (payroll_period_id, employee_id)
);

CREATE TABLE IF NOT EXISTS payslip_lines (
  payslip_line_id BIGSERIAL PRIMARY KEY,
  payslip_id BIGINT NOT NULL REFERENCES pay.payslips(payslip_id) ON DELETE CASCADE,
  line_type TEXT NOT NULL, -- earning, deduction, employer
  earning_type_id BIGINT REFERENCES pay.earning_types(earning_type_id),
  deduction_type_id BIGINT REFERENCES pay.deduction_types(deduction_type_id),
  description TEXT,
  quantity NUMERIC(10,3) DEFAULT 1,
  rate NUMERIC(15,4) DEFAULT 0,
  amount NUMERIC(15,2) NOT NULL,
  sort_order INT DEFAULT 0,
  CHECK (amount >= 0)
);

CREATE OR REPLACE FUNCTION pay.trg_payslip_rollup() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
  UPDATE pay.payslips p SET
    total_earnings = COALESCE((SELECT SUM(amount) FROM pay.payslip_lines l WHERE l.payslip_id = p.payslip_id AND l.line_type='earning'),0),
    total_deductions = COALESCE((SELECT SUM(amount) FROM pay.payslip_lines l WHERE l.payslip_id = p.payslip_id AND l.line_type='deduction'),0),
    employer_costs = COALESCE((SELECT SUM(amount) FROM pay.payslip_lines l WHERE l.payslip_id = p.payslip_id AND l.line_type='employer'),0),
    gross_pay = COALESCE((SELECT SUM(amount) FROM pay.payslip_lines l WHERE l.payslip_id = p.payslip_id AND l.line_type='earning'),0),
    net_pay = COALESCE((SELECT SUM(CASE WHEN line_type='earning' THEN amount ELSE 0 END) FROM pay.payslip_lines WHERE payslip_id=p.payslip_id),0)
             - COALESCE((SELECT SUM(amount) FROM pay.payslip_lines WHERE payslip_id=p.payslip_id AND line_type='deduction'),0)
  WHERE p.payslip_id = COALESCE(NEW.payslip_id, OLD.payslip_id);
  RETURN COALESCE(NEW, OLD);
END $$;

DROP TRIGGER IF EXISTS payslip_lines_rollup_aiud ON pay.payslip_lines;
CREATE TRIGGER payslip_lines_rollup_aiud
AFTER INSERT OR UPDATE OR DELETE ON pay.payslip_lines
FOR EACH ROW EXECUTE FUNCTION pay.trg_payslip_rollup();

COMMIT;
