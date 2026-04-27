-- ============================================================
--  BVTU Members Portal — Database Setup
--  Run this once in Hostinger's phpMyAdmin
-- ============================================================

-- Table 1: Valid employee numbers
-- Add your executive employee numbers here for the pilot,
-- then expand to all members when ready.
CREATE TABLE IF NOT EXISTS valid_employee_numbers (
  employee_number VARCHAR(20) PRIMARY KEY,
  name            VARCHAR(100) NOT NULL,
  added_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table 2: Registered member accounts
CREATE TABLE IF NOT EXISTS members (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(100) NOT NULL,
  email           VARCHAR(255) NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NOT NULL,
  employee_number VARCHAR(20)  NOT NULL UNIQUE,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_number) REFERENCES valid_employee_numbers(employee_number)
);

-- ============================================================
--  PILOT: Add executive employee numbers below.
--  Replace the example numbers with real ones.
--  Format: INSERT INTO valid_employee_numbers (employee_number, name) VALUES ('12345', 'First Last');
-- ============================================================

-- INSERT INTO valid_employee_numbers (employee_number, name) VALUES ('11111', 'Executive Name 1');
-- INSERT INTO valid_employee_numbers (employee_number, name) VALUES ('22222', 'Executive Name 2');
-- INSERT INTO valid_employee_numbers (employee_number, name) VALUES ('33333', 'Executive Name 3');
