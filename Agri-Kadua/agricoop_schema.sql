-- ============================================================================
--  AgriCoop Central - Cooperative Management System
--  MySQL 8.0.16+ (uses CHECK constraints and generated columns)
--
--  Derived from the wireframe PDF:
--    * Login / role selection
--    * Field Coordinator Portal (expected deliveries, schedule change, input requests)
--    * Farmer-Member Registry
--    * Warehouse Receiving (5-step delivery entry)
--    * Inventory & Stocks, Outbound / Milling Release
--    * Sync & Central Database
--    * Payment & Recon, Receipt Module, Audit Trail, Dashboard & Reports
--    * Role dashboards (Accounting Clerk, Warehouse Assistant, Field Coordinator,
--      Cooperative Manager)
--
--  Usage:   mysql -u root -p < agricoop_schema.sql
--  NOTE:    Seed users carry a PLACEHOLDER password hash. Replace before use.
-- ============================================================================

DROP DATABASE IF EXISTS agricoop;
CREATE DATABASE agricoop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE agricoop;
SET NAMES utf8mb4;

-- ============================================================================
-- 1. ORGANISATION & ACCESS
-- ============================================================================

CREATE TABLE branches (
  branch_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branch_code   VARCHAR(20)  NOT NULL UNIQUE,
  branch_name   VARCHAR(100) NOT NULL,                 -- 'Region VII Branch'
  region        VARCHAR(60)  NULL,
  address       VARCHAR(255) NULL,
  currency_code CHAR(3)      NOT NULL DEFAULT 'PHP',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE terminals (
  terminal_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branch_id   INT UNSIGNED NOT NULL,
  device_code VARCHAR(20)  NOT NULL UNIQUE,            -- 'W-402' (shown as "#W-402")
  description VARCHAR(100) NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  CONSTRAINT fk_terminals_branch FOREIGN KEY (branch_id) REFERENCES branches(branch_id)
) ENGINE=InnoDB;

CREATE TABLE roles (
  role_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_code   VARCHAR(30)  NOT NULL UNIQUE,
  role_name   VARCHAR(60)  NOT NULL,
  description VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE users (
  user_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_code VARCHAR(20)  NOT NULL UNIQUE,          -- 'CO-VII-8839'
  full_name     VARCHAR(120) NOT NULL,
  job_title     VARCHAR(80)  NULL,                     -- 'Lead Admin & Receptionist'
  email         VARCHAR(150) NULL UNIQUE,
  phone         VARCHAR(30)  NULL,
  password_hash VARCHAR(255) NOT NULL,
  role_id       INT UNSIGNED NOT NULL,
  branch_id     INT UNSIGNED NOT NULL,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  last_login_at DATETIME     NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_role   FOREIGN KEY (role_id)   REFERENCES roles(role_id),
  CONSTRAINT fk_users_branch FOREIGN KEY (branch_id) REFERENCES branches(branch_id),
  INDEX idx_users_role (role_id)
) ENGINE=InnoDB;

-- Every sign-in attempt, incl. the "Acknowledge System Security Guidelines" checkbox
CREATE TABLE login_events (
  event_id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id                 INT UNSIGNED NULL,
  employee_code_entered   VARCHAR(20)  NOT NULL,
  selected_role_id        INT UNSIGNED NULL,
  terminal_id             INT UNSIGNED NULL,
  guidelines_acknowledged TINYINT(1)   NOT NULL DEFAULT 0,
  success                 TINYINT(1)   NOT NULL,
  ip_address              VARCHAR(45)  NULL,
  logged_in_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_login_user     FOREIGN KEY (user_id)          REFERENCES users(user_id),
  CONSTRAINT fk_login_role     FOREIGN KEY (selected_role_id) REFERENCES roles(role_id),
  CONSTRAINT fk_login_terminal FOREIGN KEY (terminal_id)      REFERENCES terminals(terminal_id),
  INDEX idx_login_time (logged_in_at)
) ENGINE=InnoDB;

-- ============================================================================
-- 2. REFERENCE DATA
-- ============================================================================

CREATE TABLE crops (
  crop_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  crop_name VARCHAR(60) NOT NULL UNIQUE                -- Coffee, Paddy Rice, Corn ...
) ENGINE=InnoDB;

CREATE TABLE crop_varieties (
  variety_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  crop_id            INT UNSIGNED  NOT NULL,
  variety_name       VARCHAR(100)  NOT NULL,           -- 'Arabica Red Catimor'
  moisture_limit_pct DECIMAL(5,2)  NOT NULL DEFAULT 12.00,  -- "Baseline limit is 12%"
  impurity_limit_pct DECIMAL(5,2)  NOT NULL DEFAULT 1.00,   -- "Limit 1% before penalty"
  is_active          TINYINT(1)    NOT NULL DEFAULT 1,
  UNIQUE KEY uq_variety (crop_id, variety_name),
  CONSTRAINT fk_variety_crop FOREIGN KEY (crop_id) REFERENCES crops(crop_id)
) ENGINE=InnoDB;

-- Co-op base purchase rate per kg (e.g. Arabica = 120.00 / kg)
CREATE TABLE crop_rates (
  rate_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  variety_id     INT UNSIGNED  NOT NULL,
  rate_per_kg    DECIMAL(10,2) NOT NULL,
  effective_from DATE          NOT NULL,
  effective_to   DATE          NULL,
  CONSTRAINT fk_rate_variety FOREIGN KEY (variety_id) REFERENCES crop_varieties(variety_id),
  CONSTRAINT chk_rate_pos CHECK (rate_per_kg >= 0),
  INDEX idx_rate_lookup (variety_id, effective_from)
) ENGINE=InnoDB;

CREATE TABLE storage_locations (
  location_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branch_id     INT UNSIGNED NOT NULL,
  location_name VARCHAR(80)  NOT NULL,                 -- 'Silo Section 4B', 'Warehouse Dry Bay 1'
  location_type ENUM('silo','dry_bay','cold_storage','receiving_bay','other') NOT NULL DEFAULT 'dry_bay',
  capacity_kg   DECIMAL(14,2) NULL,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  UNIQUE KEY uq_location (branch_id, location_name),
  CONSTRAINT fk_loc_branch FOREIGN KEY (branch_id) REFERENCES branches(branch_id)
) ENGINE=InnoDB;

CREATE TABLE communities (
  community_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branch_id    INT UNSIGNED NOT NULL,
  name         VARCHAR(80)  NOT NULL,                  -- 'Kitala North', 'Mango Hill'
  UNIQUE KEY uq_community (branch_id, name),
  CONSTRAINT fk_comm_branch FOREIGN KEY (branch_id) REFERENCES branches(branch_id)
) ENGINE=InnoDB;

CREATE TABLE farmer_groups (
  group_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  community_id INT UNSIGNED NULL,
  group_name   VARCHAR(60)  NOT NULL UNIQUE,           -- 'Group 14'
  CONSTRAINT fk_group_comm FOREIGN KEY (community_id) REFERENCES communities(community_id)
) ENGINE=InnoDB;

-- Subsidised inputs (seeds, organic fertilizers, tools)
CREATE TABLE input_categories (
  category_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE input_products (
  product_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id        INT UNSIGNED  NOT NULL,
  product_name       VARCHAR(100)  NOT NULL,
  unit_label         VARCHAR(30)   NOT NULL,           -- 'bag (50kg)'
  unit_price         DECIMAL(12,2) NOT NULL,
  subsidy_rate_pct   DECIMAL(5,2)  NOT NULL DEFAULT 0.00,  -- 80% Cooperative Subsidised
  is_active          TINYINT(1)    NOT NULL DEFAULT 1,
  CONSTRAINT fk_prod_cat FOREIGN KEY (category_id) REFERENCES input_categories(category_id),
  CONSTRAINT chk_subsidy CHECK (subsidy_rate_pct BETWEEN 0 AND 100)
) ENGINE=InnoDB;

-- ============================================================================
-- 3. SYNC & TRANSMISSION
-- ============================================================================

CREATE TABLE sync_records (
  sync_id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  record_code   VARCHAR(20) NOT NULL UNIQUE,           -- 'TX-9921'
  source_module ENUM('farmer_members','warehouse_receiving','field_coordinator',
                     'inventory','payments','milling_outbound','receipts') NOT NULL,
  entity_type   VARCHAR(40) NULL,
  entity_id     BIGINT UNSIGNED NULL,
  size_kb       DECIMAL(8,2) NOT NULL DEFAULT 0,
  status        ENUM('saved','syncing','retry','failed') NOT NULL DEFAULT 'syncing',
  retry_count   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error    VARCHAR(255) NULL,
  terminal_id   INT UNSIGNED NULL,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  synced_at     DATETIME NULL,
  CONSTRAINT fk_sync_terminal FOREIGN KEY (terminal_id) REFERENCES terminals(terminal_id),
  CONSTRAINT fk_sync_user     FOREIGN KEY (created_by)  REFERENCES users(user_id),
  INDEX idx_sync_status (status, created_at)
) ENGINE=InnoDB;

-- "Module Availability" panel
CREATE TABLE sync_module_status (
  module_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  module_name     VARCHAR(60) NOT NULL UNIQUE,         -- Farmer Records, Warehouse Scales ...
  status          ENUM('online','degraded','offline') NOT NULL DEFAULT 'online',
  last_checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- "Force Manual Sync" and background syncs
CREATE TABLE sync_runs (
  run_id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  terminal_id    INT UNSIGNED NULL,
  trigger_type   ENUM('auto','manual') NOT NULL DEFAULT 'auto',
  triggered_by   INT UNSIGNED NULL,
  started_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at    DATETIME NULL,
  records_sent   INT UNSIGNED NOT NULL DEFAULT 0,
  records_failed INT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT fk_run_terminal FOREIGN KEY (terminal_id)  REFERENCES terminals(terminal_id),
  CONSTRAINT fk_run_user     FOREIGN KEY (triggered_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- ============================================================================
-- 4. FARMER-MEMBER REGISTRY
-- ============================================================================

CREATE TABLE farmer_members (
  member_id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_code           VARCHAR(20)  NOT NULL UNIQUE,  -- 'FM-1002'
  full_name             VARCHAR(120) NOT NULL,
  phone                 VARCHAR(30)  NULL,
  land_location         VARCHAR(255) NULL,             -- 'Barangay San Jose, Lot 4A & 4B'
  farm_size_ha          DECIMAL(8,2) NOT NULL DEFAULT 0,
  soil_classification   VARCHAR(80)  NULL,             -- 'Clay Loam (Highly Fertile)'
  main_crop_id          INT UNSIGNED NULL,
  primary_variety_id    INT UNSIGNED NULL,             -- 'Arabica Red Catimor'
  expected_yield_kg     DECIMAL(12,2) NULL,            -- 3,500 kg dry beans
  expected_yield_season VARCHAR(30)  NULL,             -- 'Q2 harvest'
  status                ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  registered_on         DATE         NOT NULL,
  branch_id             INT UNSIGNED NOT NULL,
  community_id          INT UNSIGNED NULL,
  group_id              INT UNSIGNED NULL,
  coordinator_id        INT UNSIGNED NULL,             -- assigned field coordinator
  created_by            INT UNSIGNED NULL,
  created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_fm_crop    FOREIGN KEY (main_crop_id)       REFERENCES crops(crop_id),
  CONSTRAINT fk_fm_variety FOREIGN KEY (primary_variety_id) REFERENCES crop_varieties(variety_id),
  CONSTRAINT fk_fm_branch  FOREIGN KEY (branch_id)          REFERENCES branches(branch_id),
  CONSTRAINT fk_fm_comm    FOREIGN KEY (community_id)       REFERENCES communities(community_id),
  CONSTRAINT fk_fm_group   FOREIGN KEY (group_id)           REFERENCES farmer_groups(group_id),
  CONSTRAINT fk_fm_coord   FOREIGN KEY (coordinator_id)     REFERENCES users(user_id),
  CONSTRAINT fk_fm_creator FOREIGN KEY (created_by)         REFERENCES users(user_id),
  INDEX idx_fm_name (full_name),
  INDEX idx_fm_status (status),
  FULLTEXT KEY ft_fm_search (full_name, member_code)
) ENGINE=InnoDB;

-- ============================================================================
-- 5. DELIVERIES: SCHEDULE -> WAREHOUSE RECEIVING
-- ============================================================================

-- "Expected Cooperative Deliveries" list
CREATE TABLE scheduled_deliveries (
  schedule_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id      INT UNSIGNED  NOT NULL,
  variety_id     INT UNSIGNED  NOT NULL,
  scheduled_date DATE          NOT NULL,
  estimated_kg   DECIMAL(12,2) NOT NULL,
  status         ENUM('scheduled','on_time','silo_pending','delayed','received','cancelled')
                 NOT NULL DEFAULT 'scheduled',
  created_by     INT UNSIGNED NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_sd_member  FOREIGN KEY (member_id)  REFERENCES farmer_members(member_id),
  CONSTRAINT fk_sd_variety FOREIGN KEY (variety_id) REFERENCES crop_varieties(variety_id),
  CONSTRAINT fk_sd_creator FOREIGN KEY (created_by) REFERENCES users(user_id),
  CONSTRAINT chk_sd_kg CHECK (estimated_kg > 0),
  INDEX idx_sd_date (scheduled_date, status)
) ENGINE=InnoDB;

-- "Schedule Change" card -> "Transmit Schedule Change"
CREATE TABLE schedule_changes (
  change_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  schedule_id    INT UNSIGNED NOT NULL,
  old_date       DATE         NOT NULL,
  revised_date   DATE         NOT NULL,
  reason         VARCHAR(255) NOT NULL,
  changed_by     INT UNSIGNED NOT NULL,
  transmitted_at DATETIME     NULL,
  sync_id        BIGINT UNSIGNED NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sc_schedule FOREIGN KEY (schedule_id) REFERENCES scheduled_deliveries(schedule_id),
  CONSTRAINT fk_sc_user     FOREIGN KEY (changed_by)  REFERENCES users(user_id),
  CONSTRAINT fk_sc_sync     FOREIGN KEY (sync_id)     REFERENCES sync_records(sync_id)
) ENGINE=InnoDB;

-- Warehouse Receiving (5 steps) + Receipt "RC-xxxx"
--   net = gross - deduction - rejected   (1,250 - 25 - 15 = 1,210)
CREATE TABLE delivery_receipts (
  receipt_id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  receipt_code        VARCHAR(20)  NOT NULL UNIQUE,    -- 'RC-1091'
  schedule_id         INT UNSIGNED NULL,
  member_id           INT UNSIGNED NOT NULL,
  variety_id          INT UNSIGNED NOT NULL,
  branch_id           INT UNSIGNED NOT NULL,
  terminal_id         INT UNSIGNED NULL,
  delivery_date       DATE         NOT NULL,
  -- step 2
  gross_weight_kg     DECIMAL(12,2) NOT NULL,
  -- step 3
  moisture_pct        DECIMAL(5,2) NOT NULL DEFAULT 0,
  impurities_pct      DECIMAL(5,2) NOT NULL DEFAULT 0,
  moisture_limit_pct  DECIMAL(5,2) NOT NULL DEFAULT 12.00,
  impurity_limit_pct  DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  deduction_pct       DECIMAL(5,2) NOT NULL DEFAULT 0,  -- "2.0% total deduction applied"
  deduction_kg        DECIMAL(12,2) AS (ROUND(gross_weight_kg * deduction_pct / 100, 2)) STORED,
  rejected_kg         DECIMAL(12,2) NOT NULL DEFAULT 0, -- spoiled beans discarded
  -- step 4
  net_weight_kg       DECIMAL(12,2) AS (gross_weight_kg - ROUND(gross_weight_kg * deduction_pct / 100, 2) - rejected_kg) STORED,
  -- workflow
  status              ENUM('draft','completed','stored','voided') NOT NULL DEFAULT 'draft',
  verification_status ENUM('unverified','verified','re_weighed')  NOT NULL DEFAULT 'unverified',
  location_id         INT UNSIGNED NULL,               -- set when storage is approved
  notes               VARCHAR(255) NULL,
  received_by         INT UNSIGNED NOT NULL,
  approved_by         INT UNSIGNED NULL,
  approved_at         DATETIME NULL,
  completed_at        DATETIME NULL,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_dr_schedule FOREIGN KEY (schedule_id) REFERENCES scheduled_deliveries(schedule_id),
  CONSTRAINT fk_dr_member   FOREIGN KEY (member_id)   REFERENCES farmer_members(member_id),
  CONSTRAINT fk_dr_variety  FOREIGN KEY (variety_id)  REFERENCES crop_varieties(variety_id),
  CONSTRAINT fk_dr_branch   FOREIGN KEY (branch_id)   REFERENCES branches(branch_id),
  CONSTRAINT fk_dr_terminal FOREIGN KEY (terminal_id) REFERENCES terminals(terminal_id),
  CONSTRAINT fk_dr_location FOREIGN KEY (location_id) REFERENCES storage_locations(location_id),
  CONSTRAINT fk_dr_receiver FOREIGN KEY (received_by) REFERENCES users(user_id),
  CONSTRAINT fk_dr_approver FOREIGN KEY (approved_by) REFERENCES users(user_id),
  CONSTRAINT chk_dr_gross   CHECK (gross_weight_kg > 0),
  CONSTRAINT chk_dr_pcts    CHECK (moisture_pct BETWEEN 0 AND 100 AND impurities_pct BETWEEN 0 AND 100
                                   AND deduction_pct BETWEEN 0 AND 100),
  CONSTRAINT chk_dr_rej     CHECK (rejected_kg >= 0),
  INDEX idx_dr_member (member_id, delivery_date),
  INDEX idx_dr_status (status, verification_status),
  INDEX idx_dr_date   (delivery_date)
) ENGINE=InnoDB;

-- ============================================================================
-- 6. PAYMENTS & RECEIPT SLIPS
-- ============================================================================

CREATE TABLE payment_channels (
  channel_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  channel_name VARCHAR(100) NOT NULL UNIQUE,           -- 'Cooperative Rural Bank - Account Transfer'
  is_active    TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- Payment Calculation panel:
--   gross value = net kg x base rate ; final = gross - standard deductions
CREATE TABLE payments (
  payment_id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_code        VARCHAR(20)  NOT NULL UNIQUE,    -- 'REC-882109'
  member_id           INT UNSIGNED NOT NULL,
  receipt_id          BIGINT UNSIGNED NOT NULL UNIQUE, -- one payout per delivery receipt
  variety_id          INT UNSIGNED NOT NULL,
  net_weight_kg       DECIMAL(12,2) NOT NULL,          -- snapshot of verified net weight
  base_rate_per_kg    DECIMAL(10,2) NOT NULL,          -- snapshot of co-op base rate
  gross_value         DECIMAL(14,2) AS (ROUND(net_weight_kg * base_rate_per_kg, 2)) STORED,
  standard_deductions DECIMAL(14,2) NOT NULL DEFAULT 0,
  final_amount        DECIMAL(14,2) AS (ROUND(net_weight_kg * base_rate_per_kg, 2) - standard_deductions) STORED,
  channel_id          INT UNSIGNED NULL,
  bank_reference      VARCHAR(60)  NULL,               -- 'TXN-BKR-99382109'
  status              ENUM('pending','ready_to_pay','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
  paid_at             DATETIME NULL,
  recorded_by         INT UNSIGNED NULL,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_pay_member  FOREIGN KEY (member_id)   REFERENCES farmer_members(member_id),
  CONSTRAINT fk_pay_receipt FOREIGN KEY (receipt_id)  REFERENCES delivery_receipts(receipt_id),
  CONSTRAINT fk_pay_variety FOREIGN KEY (variety_id)  REFERENCES crop_varieties(variety_id),
  CONSTRAINT fk_pay_channel FOREIGN KEY (channel_id)  REFERENCES payment_channels(channel_id),
  CONSTRAINT fk_pay_user    FOREIGN KEY (recorded_by) REFERENCES users(user_id),
  CONSTRAINT chk_pay_ded    CHECK (standard_deductions >= 0),
  INDEX idx_pay_member (member_id, status),
  INDEX idx_pay_status (status),
  INDEX idx_pay_bankref (bank_reference)
) ENGINE=InnoDB;

-- Official Delivery Receipt / Settlement slip (print thermal, download PDF)
CREATE TABLE receipt_slips (
  slip_id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slip_code     VARCHAR(30) NOT NULL UNIQUE,           -- 'REC-2025-1024-88A'
  receipt_id    BIGINT UNSIGNED NOT NULL,
  payment_id    BIGINT UNSIGNED NULL,
  barcode_value VARCHAR(40) NOT NULL,                  -- '*REC2025102488A*'
  issued_by     INT UNSIGNED NOT NULL,                 -- operator
  issued_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  member_signed TINYINT(1) NOT NULL DEFAULT 0,
  print_count   INT UNSIGNED NOT NULL DEFAULT 0,
  last_printed_at DATETIME NULL,
  pdf_path      VARCHAR(255) NULL,
  CONSTRAINT fk_slip_receipt FOREIGN KEY (receipt_id) REFERENCES delivery_receipts(receipt_id),
  CONSTRAINT fk_slip_payment FOREIGN KEY (payment_id) REFERENCES payments(payment_id),
  CONSTRAINT fk_slip_user    FOREIGN KEY (issued_by)  REFERENCES users(user_id),
  INDEX idx_slip_receipt (receipt_id)
) ENGINE=InnoDB;

-- ============================================================================
-- 7. INVENTORY, RELEASES, INPUTS, DISPATCH
-- ============================================================================

-- One row per variety per storage location ("Stock Status & Thresholds")
CREATE TABLE stock_items (
  stock_id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branch_id              INT UNSIGNED  NOT NULL,
  variety_id             INT UNSIGNED  NOT NULL,
  location_id            INT UNSIGNED  NOT NULL,
  quantity_kg            DECIMAL(14,2) NOT NULL DEFAULT 0,
  low_stock_threshold_kg DECIMAL(14,2) NOT NULL DEFAULT 1000,
  updated_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_stock (variety_id, location_id),
  CONSTRAINT fk_stock_branch   FOREIGN KEY (branch_id)   REFERENCES branches(branch_id),
  CONSTRAINT fk_stock_variety  FOREIGN KEY (variety_id)  REFERENCES crop_varieties(variety_id),
  CONSTRAINT fk_stock_location FOREIGN KEY (location_id) REFERENCES storage_locations(location_id),
  CONSTRAINT chk_stock_qty CHECK (quantity_kg >= 0)
) ENGINE=InnoDB;

-- "Outbound / Milling Release" -> "Authorise Milling Release"
CREATE TABLE milling_releases (
  release_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  milling_order_code VARCHAR(20)  NOT NULL UNIQUE,     -- 'MO-8832'
  variety_id         INT UNSIGNED NULL,
  quantity_kg        DECIMAL(14,2) NOT NULL,
  destination        VARCHAR(120) NULL,                -- 'Regional distributors'
  status             ENUM('pending','authorised','released','cancelled') NOT NULL DEFAULT 'pending',
  authorised_by      INT UNSIGNED NULL,
  authorised_at      DATETIME NULL,
  released_at        DATETIME NULL,
  created_by         INT UNSIGNED NOT NULL,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mr_variety FOREIGN KEY (variety_id)    REFERENCES crop_varieties(variety_id),
  CONSTRAINT fk_mr_auth    FOREIGN KEY (authorised_by) REFERENCES users(user_id),
  CONSTRAINT fk_mr_creator FOREIGN KEY (created_by)    REFERENCES users(user_id),
  CONSTRAINT chk_mr_qty CHECK (quantity_kg > 0)
) ENGINE=InnoDB;

CREATE TABLE trucks (
  truck_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  truck_code  VARCHAR(20) NOT NULL UNIQUE,             -- 'RB-12'
  description VARCHAR(100) NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- Warehouse dashboard: dispatches ("DSP-228", "Ready to dispatch")
CREATE TABLE dispatches (
  dispatch_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dispatch_code VARCHAR(20) NOT NULL UNIQUE,           -- 'DSP-228'
  branch_id     INT UNSIGNED NOT NULL,
  destination   VARCHAR(100) NOT NULL,                 -- 'West Zone', 'East Zone'
  truck_id      INT UNSIGNED NULL,
  status        ENUM('planned','ready','dispatched','delivered','cancelled') NOT NULL DEFAULT 'planned',
  scheduled_at  DATETIME NULL,
  dispatched_at DATETIME NULL,
  created_by    INT UNSIGNED NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_dsp_branch FOREIGN KEY (branch_id) REFERENCES branches(branch_id),
  CONSTRAINT fk_dsp_truck  FOREIGN KEY (truck_id)  REFERENCES trucks(truck_id),
  CONSTRAINT fk_dsp_user   FOREIGN KEY (created_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE dispatch_lines (
  line_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dispatch_id INT UNSIGNED NOT NULL,
  item_type   ENUM('crop','input') NOT NULL,
  variety_id  INT UNSIGNED NULL,
  product_id  INT UNSIGNED NULL,
  quantity    DECIMAL(12,2) NOT NULL,
  unit        VARCHAR(20) NOT NULL DEFAULT 'kg',       -- kg, bags
  CONSTRAINT fk_dl_dispatch FOREIGN KEY (dispatch_id) REFERENCES dispatches(dispatch_id),
  CONSTRAINT fk_dl_variety  FOREIGN KEY (variety_id)  REFERENCES crop_varieties(variety_id),
  CONSTRAINT fk_dl_product  FOREIGN KEY (product_id)  REFERENCES input_products(product_id),
  CONSTRAINT chk_dl_item CHECK (
    (item_type = 'crop'  AND variety_id IS NOT NULL AND product_id IS NULL) OR
    (item_type = 'input' AND product_id IS NOT NULL AND variety_id IS NULL))
) ENGINE=InnoDB;

-- Append-only stock ledger (receipts in, releases/dispatches out, recounts)
CREATE TABLE stock_movements (
  movement_id   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  stock_id      INT UNSIGNED NOT NULL,
  movement_type ENUM('receipt_in','release_out','dispatch_out','adjustment','recount') NOT NULL,
  quantity_kg   DECIMAL(14,2) NOT NULL,                -- signed: + in, - out
  receipt_id    BIGINT UNSIGNED NULL,
  release_id    INT UNSIGNED NULL,
  dispatch_id   INT UNSIGNED NULL,
  reason        VARCHAR(255) NULL,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sm_stock    FOREIGN KEY (stock_id)    REFERENCES stock_items(stock_id),
  CONSTRAINT fk_sm_receipt  FOREIGN KEY (receipt_id)  REFERENCES delivery_receipts(receipt_id),
  CONSTRAINT fk_sm_release  FOREIGN KEY (release_id)  REFERENCES milling_releases(release_id),
  CONSTRAINT fk_sm_dispatch FOREIGN KEY (dispatch_id) REFERENCES dispatches(dispatch_id),
  CONSTRAINT fk_sm_user     FOREIGN KEY (created_by)  REFERENCES users(user_id),
  INDEX idx_sm_stock (stock_id, created_at)
) ENGINE=InnoDB;

-- Stock of subsidised inputs (fertilizer, seeds, tools) incl. reservations
CREATE TABLE input_stock (
  input_stock_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id     INT UNSIGNED NOT NULL,
  location_id    INT UNSIGNED NOT NULL,
  qty_on_hand    DECIMAL(12,2) NOT NULL DEFAULT 0,
  qty_reserved   DECIMAL(12,2) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_input_stock (product_id, location_id),
  CONSTRAINT fk_is_product  FOREIGN KEY (product_id)  REFERENCES input_products(product_id),
  CONSTRAINT fk_is_location FOREIGN KEY (location_id) REFERENCES storage_locations(location_id),
  CONSTRAINT chk_is_qty CHECK (qty_on_hand >= 0 AND qty_reserved >= 0 AND qty_reserved <= qty_on_hand)
) ENGINE=InnoDB;

-- "Field Input Request" -> "Submit & Reserve Inputs"
--   15 bags x 1,875.00 = 28,125.00 ; 80% subsidy = 22,500.00
CREATE TABLE field_input_requests (
  request_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_code      VARCHAR(20) NOT NULL UNIQUE,
  member_id         INT UNSIGNED NOT NULL,
  product_id        INT UNSIGNED NOT NULL,
  quantity          DECIMAL(10,2) NOT NULL,
  unit_price        DECIMAL(12,2) NOT NULL,            -- snapshot
  subsidy_rate_pct  DECIMAL(5,2)  NOT NULL,            -- snapshot
  total_value       DECIMAL(14,2) AS (ROUND(quantity * unit_price, 2)) STORED,
  subsidy_value     DECIMAL(14,2) AS (ROUND(quantity * unit_price * subsidy_rate_pct / 100, 2)) STORED,
  member_share      DECIMAL(14,2) AS (ROUND(quantity * unit_price, 2) - ROUND(quantity * unit_price * subsidy_rate_pct / 100, 2)) STORED,
  pickup_date       DATE NOT NULL,
  reserved_location_id INT UNSIGNED NULL,
  status            ENUM('draft','submitted','reserved','picked_up','cancelled') NOT NULL DEFAULT 'draft',
  submitted_by      INT UNSIGNED NULL,
  submitted_at      DATETIME NULL,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_fir_member   FOREIGN KEY (member_id)  REFERENCES farmer_members(member_id),
  CONSTRAINT fk_fir_product  FOREIGN KEY (product_id) REFERENCES input_products(product_id),
  CONSTRAINT fk_fir_location FOREIGN KEY (reserved_location_id) REFERENCES storage_locations(location_id),
  CONSTRAINT fk_fir_user     FOREIGN KEY (submitted_by) REFERENCES users(user_id),
  CONSTRAINT chk_fir_qty CHECK (quantity > 0)
) ENGINE=InnoDB;

-- Inbound loads on the Warehouse dashboard ("Receive maize from Greenridge...")
CREATE TABLE inbound_loads (
  load_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branch_id       INT UNSIGNED NOT NULL,
  source_name     VARCHAR(100) NOT NULL,               -- 'Greenridge collection point'
  variety_id      INT UNSIGNED NULL,
  expected_tonnes DECIMAL(10,2) NOT NULL,
  bay_location_id INT UNSIGNED NULL,
  eta             DATETIME NULL,
  status          ENUM('expected','arriving','received','cancelled') NOT NULL DEFAULT 'expected',
  receipt_id      BIGINT UNSIGNED NULL,
  CONSTRAINT fk_il_branch   FOREIGN KEY (branch_id)       REFERENCES branches(branch_id),
  CONSTRAINT fk_il_variety  FOREIGN KEY (variety_id)      REFERENCES crop_varieties(variety_id),
  CONSTRAINT fk_il_bay      FOREIGN KEY (bay_location_id) REFERENCES storage_locations(location_id),
  CONSTRAINT fk_il_receipt  FOREIGN KEY (receipt_id)      REFERENCES delivery_receipts(receipt_id)
) ENGINE=InnoDB;

CREATE TABLE stock_counts (
  count_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  count_code    VARCHAR(20) NOT NULL UNIQUE,
  title         VARCHAR(120) NOT NULL,                 -- 'Weekly count for cold storage'
  location_id   INT UNSIGNED NULL,
  status        ENUM('scheduled','in_progress','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  scheduled_for DATE NULL,
  completed_at  DATETIME NULL,
  counted_by    INT UNSIGNED NULL,
  CONSTRAINT fk_cnt_loc  FOREIGN KEY (location_id) REFERENCES storage_locations(location_id),
  CONSTRAINT fk_cnt_user FOREIGN KEY (counted_by)  REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE stock_count_lines (
  line_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  count_id    INT UNSIGNED NOT NULL,
  stock_id    INT UNSIGNED NOT NULL,
  system_qty  DECIMAL(14,2) NOT NULL,
  counted_qty DECIMAL(14,2) NULL,
  variance_qty DECIMAL(14,2) AS (counted_qty - system_qty) STORED,
  CONSTRAINT fk_scl_count FOREIGN KEY (count_id) REFERENCES stock_counts(count_id),
  CONSTRAINT fk_scl_stock FOREIGN KEY (stock_id) REFERENCES stock_items(stock_id)
) ENGINE=InnoDB;

CREATE TABLE warehouse_issues (
  issue_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  issue_code   VARCHAR(20) NOT NULL UNIQUE,            -- 'WH-229'
  title        VARCHAR(150) NOT NULL,                  -- 'Torn grain bags in Bay 4'
  issue_type   VARCHAR(40)  NULL,                      -- damage, moisture, shortage ...
  location_id  INT UNSIGNED NULL,
  severity     ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  status       ENUM('open','assigned','resolved','closed') NOT NULL DEFAULT 'open',
  description  TEXT NULL,
  reported_by  INT UNSIGNED NULL,
  reported_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  assigned_to  INT UNSIGNED NULL,
  resolved_at  DATETIME NULL,
  CONSTRAINT fk_wi_loc      FOREIGN KEY (location_id) REFERENCES storage_locations(location_id),
  CONSTRAINT fk_wi_reporter FOREIGN KEY (reported_by) REFERENCES users(user_id),
  CONSTRAINT fk_wi_assignee FOREIGN KEY (assigned_to) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- ============================================================================
-- 8. FIELD OPERATIONS (Field Coordinator dashboard)
-- ============================================================================

CREATE TABLE field_visits (
  visit_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id       INT UNSIGNED NOT NULL,
  coordinator_id  INT UNSIGNED NOT NULL,
  visit_type      ENUM('crop_assessment','follow_up','collection_check','routine') NOT NULL DEFAULT 'routine',
  community_id    INT UNSIGNED NULL,
  scheduled_start DATETIME NOT NULL,
  scheduled_end   DATETIME NULL,
  status          ENUM('planned','confirmed','completed','missed','cancelled') NOT NULL DEFAULT 'planned',
  crop_health     ENUM('good','fair','poor') NULL,
  notes           TEXT NULL,
  completed_at    DATETIME NULL,
  CONSTRAINT fk_fv_member FOREIGN KEY (member_id)      REFERENCES farmer_members(member_id),
  CONSTRAINT fk_fv_coord  FOREIGN KEY (coordinator_id) REFERENCES users(user_id),
  CONSTRAINT fk_fv_comm   FOREIGN KEY (community_id)   REFERENCES communities(community_id),
  INDEX idx_fv_day (coordinator_id, scheduled_start)
) ENGINE=InnoDB;

CREATE TABLE field_issues (
  issue_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  issue_code   VARCHAR(20) NOT NULL UNIQUE,
  member_id    INT UNSIGNED NULL,
  farm_ref     VARCHAR(20) NULL,                       -- 'FD-0182'
  community_id INT UNSIGNED NULL,
  issue_type   VARCHAR(60) NOT NULL,                   -- 'Fall armyworm', 'Leaf damage'
  priority     ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  status       ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
  description  TEXT NULL,
  reported_by  INT UNSIGNED NULL,
  reported_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at  DATETIME NULL,
  CONSTRAINT fk_fi_member   FOREIGN KEY (member_id)    REFERENCES farmer_members(member_id),
  CONSTRAINT fk_fi_comm     FOREIGN KEY (community_id) REFERENCES communities(community_id),
  CONSTRAINT fk_fi_reporter FOREIGN KEY (reported_by)  REFERENCES users(user_id)
) ENGINE=InnoDB;

-- "Collection forecast" and "Collection estimate updated by Group 09"
CREATE TABLE collection_estimates (
  estimate_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id        INT UNSIGNED NOT NULL,
  variety_id      INT UNSIGNED NOT NULL,
  collection_date DATE NOT NULL,
  estimate_tonnes DECIMAL(10,2) NOT NULL,
  updated_by      INT UNSIGNED NULL,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ce_group   FOREIGN KEY (group_id)   REFERENCES farmer_groups(group_id),
  CONSTRAINT fk_ce_variety FOREIGN KEY (variety_id) REFERENCES crop_varieties(variety_id),
  CONSTRAINT fk_ce_user    FOREIGN KEY (updated_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE collection_routes (
  route_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  route_name      VARCHAR(80) NOT NULL,                -- 'East Corridor'
  collection_date DATE NOT NULL,
  truck_id        INT UNSIGNED NULL,
  status          ENUM('draft','planned','in_progress','completed') NOT NULL DEFAULT 'draft',
  created_by      INT UNSIGNED NULL,
  CONSTRAINT fk_cr_truck FOREIGN KEY (truck_id)   REFERENCES trucks(truck_id),
  CONSTRAINT fk_cr_user  FOREIGN KEY (created_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE route_stops (
  stop_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  route_id     INT UNSIGNED NOT NULL,
  stop_order   SMALLINT UNSIGNED NOT NULL,
  member_id    INT UNSIGNED NULL,
  community_id INT UNSIGNED NULL,
  label        VARCHAR(100) NULL,
  UNIQUE KEY uq_route_order (route_id, stop_order),
  CONSTRAINT fk_rs_route  FOREIGN KEY (route_id)     REFERENCES collection_routes(route_id),
  CONSTRAINT fk_rs_member FOREIGN KEY (member_id)    REFERENCES farmer_members(member_id),
  CONSTRAINT fk_rs_comm   FOREIGN KEY (community_id) REFERENCES communities(community_id)
) ENGINE=InnoDB;

-- ============================================================================
-- 9. ACCOUNTING (Accounting Clerk dashboard)
-- ============================================================================

CREATE TABLE invoices (
  invoice_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_code  VARCHAR(20) NOT NULL UNIQUE,           -- 'INV-1087'
  member_id     INT UNSIGNED NULL,
  customer_name VARCHAR(120) NOT NULL,
  amount        DECIMAL(14,2) NOT NULL,
  amount_paid   DECIMAL(14,2) NOT NULL DEFAULT 0,
  issued_on     DATE NOT NULL,
  due_on        DATE NOT NULL,
  status        ENUM('draft','open','approved','paid','overdue','void') NOT NULL DEFAULT 'open',
  approved_by   INT UNSIGNED NULL,
  approved_at   DATETIME NULL,
  CONSTRAINT fk_inv_member   FOREIGN KEY (member_id)   REFERENCES farmer_members(member_id),
  CONSTRAINT fk_inv_approver FOREIGN KEY (approved_by) REFERENCES users(user_id),
  CONSTRAINT chk_inv_amt CHECK (amount >= 0 AND amount_paid >= 0),
  INDEX idx_inv_status_due (status, due_on)
) ENGINE=InnoDB;

CREATE TABLE invoice_reminders (
  reminder_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id  INT UNSIGNED NOT NULL,
  sent_by     INT UNSIGNED NULL,
  channel     ENUM('email','sms','print') NOT NULL DEFAULT 'email',
  sent_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ir_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id),
  CONSTRAINT fk_ir_user    FOREIGN KEY (sent_by)    REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE bank_deposits (
  deposit_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branch_id      INT UNSIGNED NOT NULL,
  deposit_date   DATE NOT NULL,
  amount         DECIMAL(14,2) NOT NULL,
  bank_reference VARCHAR(60) NULL,
  status         ENUM('unreconciled','needs_attention','reconciled') NOT NULL DEFAULT 'unreconciled',
  reconciled_by  INT UNSIGNED NULL,
  reconciled_at  DATETIME NULL,
  notes          VARCHAR(255) NULL,
  CONSTRAINT fk_bd_branch FOREIGN KEY (branch_id)     REFERENCES branches(branch_id),
  CONSTRAINT fk_bd_user   FOREIGN KEY (reconciled_by) REFERENCES users(user_id),
  INDEX idx_bd_status (status)
) ENGINE=InnoDB;

-- Money coming IN (e.g. "Payment from North Valley Farm posted")
CREATE TABLE payments_received (
  received_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payer_name   VARCHAR(120) NOT NULL,
  member_id    INT UNSIGNED NULL,
  invoice_id   INT UNSIGNED NULL,
  deposit_id   INT UNSIGNED NULL,
  amount       DECIMAL(14,2) NOT NULL,
  method       ENUM('cash','bank_transfer','cheque','mobile','other') NOT NULL DEFAULT 'bank_transfer',
  received_on  DATE NOT NULL,
  posted_by    INT UNSIGNED NULL,
  posted_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pr_member  FOREIGN KEY (member_id)  REFERENCES farmer_members(member_id),
  CONSTRAINT fk_pr_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id),
  CONSTRAINT fk_pr_deposit FOREIGN KEY (deposit_id) REFERENCES bank_deposits(deposit_id),
  CONSTRAINT fk_pr_user    FOREIGN KEY (posted_by)  REFERENCES users(user_id),
  INDEX idx_pr_date (received_on)
) ENGINE=InnoDB;

CREATE TABLE expense_batches (
  batch_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_label  VARCHAR(100) NOT NULL,                  -- 'Fuel expense batch'
  submitted_by_dept VARCHAR(60) NULL,                  -- 'Operations'
  status       ENUM('submitted','in_review','approved','rejected') NOT NULL DEFAULT 'submitted',
  reviewed_by  INT UNSIGNED NULL,
  reviewed_at  DATETIME NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_eb_user FOREIGN KEY (reviewed_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE expense_claims (
  expense_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_id     INT UNSIGNED NULL,
  category     VARCHAR(60) NOT NULL,                   -- 'Field fuel'
  description  VARCHAR(255) NULL,
  amount       DECIMAL(14,2) NOT NULL,
  incurred_on  DATE NOT NULL,
  has_receipt  TINYINT(1) NOT NULL DEFAULT 0,
  submitted_by INT UNSIGNED NULL,
  status       ENUM('submitted','in_review','approved','rejected') NOT NULL DEFAULT 'submitted',
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ec_batch FOREIGN KEY (batch_id)     REFERENCES expense_batches(batch_id),
  CONSTRAINT fk_ec_user  FOREIGN KEY (submitted_by) REFERENCES users(user_id),
  INDEX idx_ec_date (incurred_on)
) ENGINE=InnoDB;

CREATE TABLE budgets (
  budget_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branch_id     INT UNSIGNED NOT NULL,
  period_month  DATE NOT NULL,                         -- first day of month
  category      VARCHAR(60) NOT NULL,
  budget_amount DECIMAL(14,2) NOT NULL,
  UNIQUE KEY uq_budget (branch_id, period_month, category),
  CONSTRAINT fk_bud_branch FOREIGN KEY (branch_id) REFERENCES branches(branch_id)
) ENGINE=InnoDB;

CREATE TABLE petty_cash_closings (
  closing_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branch_id        INT UNSIGNED NOT NULL,
  period_month     DATE NOT NULL,
  expected_balance DECIMAL(14,2) NOT NULL,
  counted_balance  DECIMAL(14,2) NULL,
  difference       DECIMAL(14,2) AS (counted_balance - expected_balance) STORED,
  status           ENUM('open','needs_check','closed') NOT NULL DEFAULT 'open',
  closed_by        INT UNSIGNED NULL,
  closed_at        DATETIME NULL,
  UNIQUE KEY uq_petty (branch_id, period_month),
  CONSTRAINT fk_pc_branch FOREIGN KEY (branch_id) REFERENCES branches(branch_id),
  CONSTRAINT fk_pc_user   FOREIGN KEY (closed_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- ============================================================================
-- 10. MANAGEMENT (Cooperative Manager dashboard)
-- ============================================================================

CREATE TABLE procurement_plans (
  plan_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branch_id    INT UNSIGNED NOT NULL,
  title        VARCHAR(120) NOT NULL,                  -- 'October procurement plan'
  period_month DATE NOT NULL,
  total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  status       ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
  submitted_by INT UNSIGNED NULL,
  approved_by  INT UNSIGNED NULL,
  approved_at  DATETIME NULL,
  CONSTRAINT fk_pp_branch    FOREIGN KEY (branch_id)    REFERENCES branches(branch_id),
  CONSTRAINT fk_pp_submitter FOREIGN KEY (submitted_by) REFERENCES users(user_id),
  CONSTRAINT fk_pp_approver  FOREIGN KEY (approved_by)  REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE board_meetings (
  meeting_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branch_id     INT UNSIGNED NOT NULL,
  meeting_date  DATE NOT NULL,
  agenda        TEXT NULL,
  agenda_status ENUM('draft','confirmed') NOT NULL DEFAULT 'draft',
  confirmed_by  INT UNSIGNED NULL,
  CONSTRAINT fk_bm_branch FOREIGN KEY (branch_id)    REFERENCES branches(branch_id),
  CONSTRAINT fk_bm_user   FOREIGN KEY (confirmed_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE operational_issues (
  issue_id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branch_id         INT UNSIGNED NOT NULL,
  title             VARCHAR(150) NOT NULL,             -- 'East Depot capacity at 94%'
  area              VARCHAR(60)  NULL,
  requires_decision TINYINT(1) NOT NULL DEFAULT 0,
  status            ENUM('open','decided','resolved') NOT NULL DEFAULT 'open',
  decision          TEXT NULL,
  decided_by        INT UNSIGNED NULL,
  decided_at        DATETIME NULL,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_oi_branch FOREIGN KEY (branch_id)  REFERENCES branches(branch_id),
  CONSTRAINT fk_oi_user   FOREIGN KEY (decided_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE volume_targets (
  target_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branch_id     INT UNSIGNED NOT NULL,
  period_month  DATE NOT NULL,
  target_tonnes DECIMAL(12,2) NOT NULL,
  UNIQUE KEY uq_target (branch_id, period_month),
  CONSTRAINT fk_vt_branch FOREIGN KEY (branch_id) REFERENCES branches(branch_id)
) ENGINE=InnoDB;

-- ============================================================================
-- 11. SHARED: TASKS, ACTIVITY FEED, NOTIFICATIONS, AUDIT, REPORTS
-- ============================================================================

-- Powers every "Today's tasks" / "Manager tasks" list on the role dashboards
CREATE TABLE tasks (
  task_id      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branch_id    INT UNSIGNED NOT NULL,
  task_area    ENUM('accounting','warehouse','field','manager') NOT NULL,
  title        VARCHAR(200) NOT NULL,
  detail       VARCHAR(255) NULL,                      -- sub-line, e.g. 'Deposit • $6,240 • Oct 1'
  badge_label  VARCHAR(30)  NULL,                      -- 'Due today', 'Urgent', 'Approve' ...
  priority     ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  status       ENUM('open','in_progress','done','cancelled') NOT NULL DEFAULT 'open',
  due_at       DATETIME NULL,
  assigned_to  INT UNSIGNED NULL,
  related_type VARCHAR(40) NULL,
  related_id   BIGINT UNSIGNED NULL,
  created_by   INT UNSIGNED NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  CONSTRAINT fk_task_branch   FOREIGN KEY (branch_id)   REFERENCES branches(branch_id),
  CONSTRAINT fk_task_assignee FOREIGN KEY (assigned_to) REFERENCES users(user_id),
  CONSTRAINT fk_task_creator  FOREIGN KEY (created_by)  REFERENCES users(user_id),
  INDEX idx_task_area (task_area, status, due_at)
) ENGINE=InnoDB;

-- "Recent financial activity", "Warehouse activity", "Field updates", "Recent activity"
CREATE TABLE activity_feed (
  activity_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branch_id   INT UNSIGNED NOT NULL,
  scope       ENUM('accounting','warehouse','field','manager','dashboard') NOT NULL,
  message     VARCHAR(200) NOT NULL,
  detail      VARCHAR(255) NULL,
  related_type VARCHAR(40) NULL,
  related_id   BIGINT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_af_branch FOREIGN KEY (branch_id) REFERENCES branches(branch_id),
  INDEX idx_af_scope (scope, created_at)
) ENGINE=InnoDB;

CREATE TABLE notifications (
  notification_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  title      VARCHAR(150) NOT NULL,
  body       VARCHAR(255) NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(user_id),
  INDEX idx_notif_user (user_id, is_read)
) ENGINE=InnoDB;

-- Tamper-evident, append-only audit trail (UPDATE / DELETE blocked by triggers)
CREATE TABLE audit_logs (
  audit_id     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NULL,
  actor_label  VARCHAR(80)  NULL,                      -- 'System Scaler' when user_id is NULL
  module       VARCHAR(40)  NOT NULL,                  -- farmer_members, warehouse_receiving, payments ...
  record_ref   VARCHAR(40)  NOT NULL,                  -- 'FM-1002', 'RC-1090', 'TX-9924'
  action       VARCHAR(40)  NOT NULL,                  -- 'Update Profile', 'Value Override', 'Sync Failure'
  reason       VARCHAR(255) NULL,
  before_value TEXT NULL,
  after_value  TEXT NULL,
  terminal_id  INT UNSIGNED NULL,
  changed_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  row_hash     CHAR(64) NULL DEFAULT NULL,             -- SHA-256 filled by trigger
  CONSTRAINT fk_audit_user     FOREIGN KEY (user_id)     REFERENCES users(user_id),
  CONSTRAINT fk_audit_terminal FOREIGN KEY (terminal_id) REFERENCES terminals(terminal_id),
  INDEX idx_audit_time   (changed_at),
  INDEX idx_audit_record (record_ref),
  INDEX idx_audit_module (module, changed_at),
  INDEX idx_audit_user   (user_id, changed_at)
) ENGINE=InnoDB;

CREATE TABLE report_templates (
  template_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_name VARCHAR(100) NOT NULL UNIQUE,
  description   VARCHAR(255) NULL
) ENGINE=InnoDB;

-- "Generate Report Export" -> "Export Statement"
CREATE TABLE report_exports (
  export_id      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_id    INT UNSIGNED NOT NULL,
  export_format  ENUM('csv_excel','pdf') NOT NULL DEFAULT 'csv_excel',
  period_start   DATE NULL,
  period_end     DATE NULL,
  generated_by   INT UNSIGNED NOT NULL,
  terminal_id    INT UNSIGNED NULL,
  file_path      VARCHAR(255) NULL,
  signature_hash CHAR(64) NULL,                        -- verified with the Region security master key
  generated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_re_template FOREIGN KEY (template_id)  REFERENCES report_templates(template_id),
  CONSTRAINT fk_re_user     FOREIGN KEY (generated_by) REFERENCES users(user_id),
  CONSTRAINT fk_re_terminal FOREIGN KEY (terminal_id)  REFERENCES terminals(terminal_id)
) ENGINE=InnoDB;

-- ============================================================================
-- 12. TRIGGERS & STORED PROCEDURES
-- ============================================================================

DELIMITER $$

CREATE TRIGGER trg_audit_logs_bi BEFORE INSERT ON audit_logs
FOR EACH ROW
BEGIN
  IF NEW.changed_at IS NULL THEN
    SET NEW.changed_at = NOW();
  END IF;
  SET NEW.row_hash = SHA2(CONCAT_WS('|', NEW.user_id, NEW.actor_label, NEW.module, NEW.record_ref,
                                    NEW.action, NEW.reason, NEW.before_value, NEW.after_value,
                                    NEW.changed_at), 256);
END$$

CREATE TRIGGER trg_audit_logs_bu BEFORE UPDATE ON audit_logs
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs is append-only: updates are not allowed';
END$$

CREATE TRIGGER trg_audit_logs_bd BEFORE DELETE ON audit_logs
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs is append-only: deletes are not allowed';
END$$

-- "APPROVE STORAGE" on Pending Inventory Actions:
-- moves a completed receipt's net weight into stock, writes ledger + audit rows.
CREATE PROCEDURE sp_approve_storage(
  IN p_receipt_id  BIGINT UNSIGNED,
  IN p_location_id INT UNSIGNED,
  IN p_user_id     INT UNSIGNED
)
BEGIN
  DECLARE v_variety INT UNSIGNED;
  DECLARE v_branch  INT UNSIGNED;
  DECLARE v_net     DECIMAL(12,2);
  DECLARE v_status  VARCHAR(20);
  DECLARE v_code    VARCHAR(20);
  DECLARE v_stock   INT UNSIGNED;

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  START TRANSACTION;

  SELECT variety_id, branch_id, net_weight_kg, status, receipt_code
    INTO v_variety, v_branch, v_net, v_status, v_code
    FROM delivery_receipts
   WHERE receipt_id = p_receipt_id
   FOR UPDATE;

  IF v_status IS NULL OR v_status <> 'completed' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Receipt must exist and be in completed status';
  END IF;

  INSERT INTO stock_items (branch_id, variety_id, location_id, quantity_kg)
  VALUES (v_branch, v_variety, p_location_id, v_net)
  ON DUPLICATE KEY UPDATE quantity_kg = quantity_kg + v_net;

  SELECT stock_id INTO v_stock
    FROM stock_items
   WHERE variety_id = v_variety AND location_id = p_location_id;

  INSERT INTO stock_movements (stock_id, movement_type, quantity_kg, receipt_id, reason, created_by)
  VALUES (v_stock, 'receipt_in', v_net, p_receipt_id, CONCAT('Storage approved for ', v_code), p_user_id);

  UPDATE delivery_receipts
     SET status = 'stored', location_id = p_location_id,
         approved_by = p_user_id, approved_at = NOW()
   WHERE receipt_id = p_receipt_id;

  INSERT INTO audit_logs (user_id, module, record_ref, action, reason, before_value, after_value)
  VALUES (p_user_id, 'inventory', v_code, 'Approve Storage', 'Storage approved', 'completed', 'stored');

  COMMIT;
END$$

DELIMITER ;

-- ============================================================================
-- 13. VIEWS (screen-ready queries)
-- ============================================================================

-- Inventory & Stocks: "Stock Status & Thresholds"
CREATE VIEW v_stock_status AS
SELECT s.stock_id, c.crop_name, v.variety_name, l.location_name,
       s.quantity_kg, s.low_stock_threshold_kg,
       CASE WHEN s.quantity_kg <= s.low_stock_threshold_kg THEN 'LOW STOCK' ELSE 'NORMAL' END AS stock_status
  FROM stock_items s
  JOIN crop_varieties v     ON v.variety_id  = s.variety_id
  JOIN crops c              ON c.crop_id     = v.crop_id
  JOIN storage_locations l  ON l.location_id = s.location_id;

-- Inventory & Stocks: "Inventory Overview" tiles
CREATE VIEW v_inventory_overview AS
SELECT
  (SELECT COALESCE(SUM(quantity_kg),0) FROM stock_items)                               AS total_bulk_stock_kg,
  (SELECT COUNT(*) FROM delivery_receipts WHERE status = 'completed')                  AS receipts_pending_storage,
  (SELECT COALESCE(SUM(quantity_kg),0) FROM milling_releases WHERE status = 'authorised') AS active_releases_kg,
  (SELECT COUNT(*) FROM stock_items WHERE quantity_kg <= low_stock_threshold_kg)       AS low_stock_alerts;

-- Inventory & Stocks: "Pending Inventory Actions"
CREATE VIEW v_pending_storage_approvals AS
SELECT r.receipt_id, r.receipt_code, r.net_weight_kg, c.crop_name, v.variety_name,
       m.full_name AS received_from, r.delivery_date
  FROM delivery_receipts r
  JOIN farmer_members m  ON m.member_id  = r.member_id
  JOIN crop_varieties v  ON v.variety_id = r.variety_id
  JOIN crops c           ON c.crop_id    = v.crop_id
 WHERE r.status = 'completed';

-- Field Portal: "Expected Cooperative Deliveries"
CREATE VIEW v_expected_deliveries AS
SELECT d.schedule_id, d.scheduled_date, m.member_code, m.full_name,
       c.crop_name, v.variety_name, d.estimated_kg, d.status
  FROM scheduled_deliveries d
  JOIN farmer_members m  ON m.member_id  = d.member_id
  JOIN crop_varieties v  ON v.variety_id = d.variety_id
  JOIN crops c           ON c.crop_id    = v.crop_id
 WHERE d.status NOT IN ('received','cancelled')
 ORDER BY d.scheduled_date;

-- Farmer Registry: "Recent Delivery Transactions"
CREATE VIEW v_member_delivery_transactions AS
SELECT r.member_id, r.receipt_code, r.delivery_date,
       c.crop_name, v.variety_name,
       r.net_weight_kg,
       p.final_amount,
       COALESCE(p.status, 'pending') AS payment_status
  FROM delivery_receipts r
  JOIN crop_varieties v ON v.variety_id = r.variety_id
  JOIN crops c          ON c.crop_id    = v.crop_id
  LEFT JOIN payments p  ON p.receipt_id = r.receipt_id
 WHERE r.status IN ('completed','stored');

-- Payment & Recon: "Verified Harvest Queue"
CREATE VIEW v_verified_harvest_queue AS
SELECT m.member_code, m.full_name, v.variety_name AS crop_type,
       r.receipt_id, r.receipt_code, r.net_weight_kg, r.verification_status
  FROM delivery_receipts r
  JOIN farmer_members m ON m.member_id  = r.member_id
  JOIN crop_varieties v ON v.variety_id = r.variety_id
  LEFT JOIN payments p  ON p.receipt_id = r.receipt_id AND p.status = 'paid'
 WHERE r.verification_status IN ('verified','re_weighed')
   AND r.status IN ('completed','stored')
   AND p.payment_id IS NULL;

-- Audit Trail: "Change Log"
CREATE VIEW v_audit_change_log AS
SELECT a.audit_id,
       COALESCE(u.full_name, a.actor_label) AS user_name,
       a.changed_at, a.module, a.record_ref, a.action, a.reason,
       a.before_value, a.after_value, a.row_hash
  FROM audit_logs a
  LEFT JOIN users u ON u.user_id = a.user_id
 ORDER BY a.changed_at DESC;

-- Dashboard & Reports: KPI tiles
CREATE VIEW v_dashboard_kpis AS
SELECT
  (SELECT COALESCE(SUM(net_weight_kg),0)
     FROM delivery_receipts
    WHERE status IN ('completed','stored')
      AND delivery_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01'))                          AS harvest_yield_kg_this_month,
  (SELECT COALESCE(SUM(s.quantity_kg * r.rate_per_kg),0)
     FROM stock_items s
     JOIN crop_rates r ON r.variety_id = s.variety_id
                      AND r.effective_from <= CURDATE()
                      AND (r.effective_to IS NULL OR r.effective_to >= CURDATE()))     AS cooperative_valuation,
  (SELECT COUNT(*) FROM farmer_members WHERE status = 'active')                         AS active_members,
  (SELECT COUNT(*) FROM farmer_members)                                                 AS total_members,
  (SELECT COALESCE(SUM(final_amount),0) FROM payments WHERE status = 'paid')            AS disbursed_settlements;

-- Sync & Transmission: "Retry Needed" banner
CREATE VIEW v_sync_retry_queue AS
SELECT record_code, source_module, size_kb, retry_count, last_error, created_at
  FROM sync_records
 WHERE status IN ('retry','failed');

-- ============================================================================
-- 14. SEED DATA (taken from the wireframe; sample values where noted)
-- ============================================================================

INSERT INTO roles (role_id, role_code, role_name, description) VALUES
 (1,'field_staff',        'Field Staff',         'Field coordinators: delivery schedules, input requests, field visits'),
 (2,'warehouse_staff',    'Warehouse Staff',     'Receiving, inventory, dispatch'),
 (3,'central_admin',      'Central Admin',       'Registry, payments, audit, reports, sync'),
 (4,'accounting_clerk',   'Accounting Clerk',    'Payments, invoices, expenses, reconciliations'),
 (5,'cooperative_manager','Cooperative Manager', 'Approvals and organisation overview');

INSERT INTO branches (branch_id, branch_code, branch_name, region, currency_code) VALUES
 (1,'REG-VII','Region VII Branch','Region VII','PHP');

INSERT INTO terminals (terminal_id, branch_id, device_code, description) VALUES
 (1,1,'W-402','Silo Terminal');

-- Sample users. employee_code CO-VII-8839 is from the login screen; the others are samples.
-- password_hash is a PLACEHOLDER: replace with real bcrypt/argon2 hashes.
INSERT INTO users (user_id, employee_code, full_name, job_title, role_id, branch_id, password_hash) VALUES
 (1,'CO-VII-8839','Clara Ocampo', 'Lead Admin & Receptionist', 3, 1, '$2y$10$REPLACE_WITH_REAL_HASH_000000000000000000000000000000'),
 (2,'CO-VII-8840','Mateo De Silva','Warehouse Receiver',       2, 1, '$2y$10$REPLACE_WITH_REAL_HASH_000000000000000000000000000000'),
 (3,'CO-VII-8841','Lina Brooks',   'Accounting Clerk',          4, 1, '$2y$10$REPLACE_WITH_REAL_HASH_000000000000000000000000000000'),
 (4,'CO-VII-8842','Samuel Kato',   'Warehouse Assistant',       2, 1, '$2y$10$REPLACE_WITH_REAL_HASH_000000000000000000000000000000'),
 (5,'CO-VII-8843','Nadia Okafor',  'Field Coordinator',         1, 1, '$2y$10$REPLACE_WITH_REAL_HASH_000000000000000000000000000000'),
 (6,'CO-VII-8844','Mira Cole',     'Cooperative Manager',       5, 1, '$2y$10$REPLACE_WITH_REAL_HASH_000000000000000000000000000000');

INSERT INTO crops (crop_id, crop_name) VALUES
 (1,'Coffee'),(2,'Paddy Rice'),(3,'Corn'),(4,'Sweet Potato');

INSERT INTO crop_varieties (variety_id, crop_id, variety_name) VALUES
 (1,1,'Arabica Red Catimor'),
 (2,1,'Robusta'),
 (3,2,'High-Yield Paddy RC-228'),
 (4,3,'Yellow Corn Sweet-Corn F1'),
 (5,3,'Organic Maize'),
 (6,4,'Sweet Potato');

INSERT INTO crop_rates (variety_id, rate_per_kg, effective_from) VALUES
 (1,120.00,'2025-01-01'),   -- Arabica: PHP 120.00 / kg (Payment screen)
 (5, 30.00,'2025-01-01');   -- Organic Maize: derived from 93,000 / 3,100 kg

INSERT INTO storage_locations (location_id, branch_id, location_name, location_type) VALUES
 (1,1,'Silo Section 4B',     'silo'),
 (2,1,'Warehouse Dry Bay 1', 'dry_bay'),
 (3,1,'Warehouse Dry Bay 3', 'dry_bay'),
 (4,1,'Warehouse Dry Bay 2', 'dry_bay');

INSERT INTO input_categories (category_id, category_name) VALUES
 (1,'Seeds'),(2,'Organic Fertilizer'),(3,'Tools');

-- 15 bags x 1,875.00 = 28,125.00 ; 80% subsidy = 22,500.00 (Field Input Request screen)
INSERT INTO input_products (product_id, category_id, product_name, unit_label, unit_price, subsidy_rate_pct) VALUES
 (1,2,'Organic Fertilizer','bag (50kg)',1875.00,80.00);

INSERT INTO payment_channels (channel_id, channel_name) VALUES
 (1,'Cooperative Rural Bank - Account Transfer');

INSERT INTO sync_module_status (module_name, status) VALUES
 ('Farmer Records','online'),('Warehouse Scales','online'),
 ('Silo Inventory','online'),('Milling Outbound','degraded');

INSERT INTO sync_records (record_code, source_module, size_kb, status, retry_count, last_error, terminal_id, created_at, synced_at) VALUES
 ('TX-9921','warehouse_receiving',1.2,'saved',  0,NULL,             1, NOW() - INTERVAL 12 SECOND, NOW()),
 ('TX-9922','warehouse_receiving',2.4,'syncing',0,NULL,             1, NOW(),                      NULL),
 ('TX-9923','field_coordinator',  0.8,'saved',  0,NULL,             1, NOW() - INTERVAL 1 MINUTE,  NOW()),
 ('TX-9924','field_coordinator',  1.5,'retry',  1,'Network timeout',1, NOW() - INTERVAL 2 MINUTE,  NULL);

-- Farmer registry
INSERT INTO farmer_members
 (member_id, member_code, full_name, phone, land_location, farm_size_ha, soil_classification,
  main_crop_id, primary_variety_id, expected_yield_kg, expected_yield_season, status, registered_on,
  branch_id, coordinator_id, created_by) VALUES
 (1,'FM-1002','Ramon Valenzuela','+63 917 123 4567','Barangay San Jose, Lot 4A & 4B',14.5,'Clay Loam (Highly Fertile)',
    1,1,3500,'Q2 harvest','active','2021-01-01',1,5,1),   -- registered_on: wireframe shows year 2021 only
 (2,'FM-1003','Elena Santos',     NULL,NULL, 8.2,NULL,2,3,NULL,NULL,'active',  '2021-01-01',1,5,1),
 (3,'FM-1004','Josephine Cruz',   NULL,NULL,12.0,NULL,3,4,NULL,NULL,'inactive','2021-01-01',1,5,1),
 (4,'FM-1005','Mateo Alcantara',  NULL,NULL,22.1,NULL,4,6,NULL,NULL,'active',  '2021-01-01',1,5,1),
 (5,'FM-1006','Simeon De Vega',   NULL,NULL, 5.0,NULL,1,2,NULL,NULL,'active',  '2021-01-01',1,5,1);

-- Field Portal: expected deliveries (Oct 24 / 25 / 26, 2025)
INSERT INTO scheduled_deliveries (schedule_id, member_id, variety_id, scheduled_date, estimated_kg, status, created_by) VALUES
 (1,1,1,'2025-10-24',1250,'on_time',     5),
 (2,2,3,'2025-10-25',4000,'silo_pending',5),
 (3,4,6,'2025-10-26',1800,'scheduled',   5);

-- Delivery receipts. Past deliveries for Ramon (Registry screen) + today's receipts.
INSERT INTO delivery_receipts
 (receipt_code, schedule_id, member_id, variety_id, branch_id, terminal_id, delivery_date,
  gross_weight_kg, moisture_pct, impurities_pct, deduction_pct, rejected_kg,
  status, verification_status, received_by, completed_at) VALUES
 ('RC-1070',NULL,1,1,1,1,'2025-08-15', 850.00,12.0,1.0,0.0, 0.0,'stored',   'verified',2,'2025-08-15 10:00:00'),
 ('RC-1080',NULL,1,5,1,1,'2025-09-28',3100.00,12.0,1.0,0.0, 0.0,'stored',   'verified',2,'2025-09-28 10:00:00'),
 ('RC-1086',NULL,1,1,1,1,'2025-10-12',1240.00,12.0,1.0,0.0, 0.0,'stored',   'verified',2,'2025-10-12 10:00:00'),
 ('RC-1090',   2,2,3,1,1,'2025-10-24',3400.00,12.0,1.2,0.0, 0.0,'completed','verified',2,'2025-10-24 13:48:01'),
 -- Warehouse Receiving screen: 1,250 - 25 (2.0%) - 15 rejected = 1,210.0 kg
 ('RC-1091',   1,1,1,1,1,'2025-10-24',1250.00,13.5,1.2,2.0,15.0,'completed','verified',1,'2025-10-24 14:10:00');

-- Payments: Ramon's history + today's disbursement (Payment & Recon screen)
INSERT INTO payments (payment_code, member_id, receipt_id, variety_id, net_weight_kg, base_rate_per_kg,
                      standard_deductions, channel_id, bank_reference, status, paid_at, recorded_by)
SELECT 'REC-881070', r.member_id, r.receipt_id, r.variety_id, r.net_weight_kg, 120.00, 0.00, 1, NULL, 'pending', NULL, 1
  FROM delivery_receipts r WHERE r.receipt_code = 'RC-1070';
INSERT INTO payments (payment_code, member_id, receipt_id, variety_id, net_weight_kg, base_rate_per_kg,
                      standard_deductions, channel_id, bank_reference, status, paid_at, recorded_by)
SELECT 'REC-881080', r.member_id, r.receipt_id, r.variety_id, r.net_weight_kg, 30.00, 0.00, 1, NULL, 'paid', '2025-09-30 09:00:00', 1
  FROM delivery_receipts r WHERE r.receipt_code = 'RC-1080';
INSERT INTO payments (payment_code, member_id, receipt_id, variety_id, net_weight_kg, base_rate_per_kg,
                      standard_deductions, channel_id, bank_reference, status, paid_at, recorded_by)
SELECT 'REC-881086', r.member_id, r.receipt_id, r.variety_id, r.net_weight_kg, 120.00, 0.00, 1, NULL, 'paid', '2025-10-14 09:00:00', 1
  FROM delivery_receipts r WHERE r.receipt_code = 'RC-1086';
-- 1,210 kg x 120 = 145,200.00 - 9,578.00 = 135,622.00
INSERT INTO payments (payment_code, member_id, receipt_id, variety_id, net_weight_kg, base_rate_per_kg,
                      standard_deductions, channel_id, bank_reference, status, paid_at, recorded_by)
SELECT 'REC-882109', r.member_id, r.receipt_id, r.variety_id, r.net_weight_kg, 120.00, 9578.00, 1,
       'TXN-BKR-99382109', 'paid', '2025-10-24 14:15:22', 1
  FROM delivery_receipts r WHERE r.receipt_code = 'RC-1091';

-- Receipt Module: Official Delivery Receipt
INSERT INTO receipt_slips (slip_code, receipt_id, payment_id, barcode_value, issued_by, issued_at)
SELECT 'REC-2025-1024-88A', r.receipt_id, p.payment_id, '*REC2025102488A*', 1, '2025-10-24 14:15:00'
  FROM delivery_receipts r JOIN payments p ON p.receipt_id = r.receipt_id
 WHERE r.receipt_code = 'RC-1091';

-- Inventory: totals 42,350 kg; 2 low-stock items (paddy, sweet potato)
INSERT INTO stock_items (branch_id, variety_id, location_id, quantity_kg, low_stock_threshold_kg) VALUES
 (1,1,1,14200,5000),
 (1,3,2, 4500,5000),
 (1,4,3,22800,5000),
 (1,6,4,  850,1000);   -- 4th row is a sample so the overview totals 42,350 kg

-- Outbound release: 3,400 kg to regional distributors (sample order id from the placeholder)
INSERT INTO milling_releases (milling_order_code, variety_id, quantity_kg, destination, status, authorised_by, authorised_at, created_by) VALUES
 ('MO-8832',3,3400,'Regional distributors','authorised',1,'2025-10-24 11:00:00',1);

-- Field Input Request screen
INSERT INTO field_input_requests (request_code, member_id, product_id, quantity, unit_price, subsidy_rate_pct,
                                  pickup_date, status, submitted_by) VALUES
 ('FIR-0001',1,1,15,1875.00,80.00,'2025-11-01','draft',1);

-- Audit trail (hash is generated by trigger)
INSERT INTO audit_logs (user_id, actor_label, module, record_ref, action, reason, before_value, after_value, terminal_id, changed_at) VALUES
 (1,   NULL,           'farmer_members',      'FM-1002',    'Update Profile','Soil classification corrected',        'Clay',          'Clay Loam (Highly Fertile)', 1,'2025-10-24 14:32:10'),
 (1,   NULL,           'payments',            'REC-882109', 'Disburse Pay',  'Payment approved',                    '₱0.00',         '₱135,622.00',                1,'2025-10-24 14:15:22'),
 (2,   NULL,           'warehouse_receiving', 'RC-1090',    'Value Override','Impurities adjusted after re-sieve',  '1.5%',          '1.2%',                       1,'2025-10-24 13:48:01'),
 (NULL,'System Scaler','sync',                'TX-9924',    'Sync Failure',  'Network timeout',                    'Pending',       'Retrying',                   1,'2025-10-24 12:04:15');

INSERT INTO report_templates (template_name) VALUES
 ('End-Of-Month Reconciliation PDF');

-- ============================================================================
-- END
-- ============================================================================
