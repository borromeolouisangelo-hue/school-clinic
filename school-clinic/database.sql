  -- ============================================================
  -- School Clinic Inventory & Information Management System
  -- Database Schema + Seed Data
  -- Import via phpMyAdmin or: mysql -u root < database.sql
  -- ============================================================

  CREATE DATABASE IF NOT EXISTS school_clinic
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  USE school_clinic;

  SET FOREIGN_KEY_CHECKS = 0;
  DROP TABLE IF EXISTS audit_logs;
  DROP TABLE IF EXISTS failed_logins;
  DROP TABLE IF EXISTS stock_adjustments;
  DROP TABLE IF EXISTS consultations;
  DROP TABLE IF EXISTS medical_certificates;
  DROP TABLE IF EXISTS notifications;
  DROP TABLE IF EXISTS equipment_loans;
  DROP TABLE IF EXISTS equipment;
  DROP TABLE IF EXISTS dispense_logs;
  DROP TABLE IF EXISTS medicine_requests;
  DROP TABLE IF EXISTS stock_movements;
  DROP TABLE IF EXISTS medicine_batches;
  DROP TABLE IF EXISTS medicines;
  DROP TABLE IF EXISTS suppliers;
  DROP TABLE IF EXISTS medicine_categories;
  DROP TABLE IF EXISTS user_allergies;
  DROP TABLE IF EXISTS medical_records;
  DROP TABLE IF EXISTS employee_details;
  DROP TABLE IF EXISTS student_details;
  DROP TABLE IF EXISTS profiles;
  DROP TABLE IF EXISTS users;
  DROP TABLE IF EXISTS system_settings;
  SET FOREIGN_KEY_CHECKS = 1;

  -- ------------------------------------------------------------
  -- USERS
  -- ------------------------------------------------------------
  CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    email         VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('user','staff','admin') NOT NULL DEFAULT 'user',
    account_type  ENUM('student','employee')   NOT NULL DEFAULT 'student',
    status        ENUM('active','inactive')    NOT NULL DEFAULT 'active',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB;

  -- ------------------------------------------------------------
  -- PROFILES
  -- ------------------------------------------------------------
  CREATE TABLE profiles (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT NOT NULL UNIQUE,
    first_name          VARCHAR(80)  NOT NULL,
    middle_name         VARCHAR(80)  DEFAULT NULL,
    last_name           VARCHAR(80)  NOT NULL,
    gender              ENUM('male','female','other') DEFAULT NULL,
    birthdate           DATE DEFAULT NULL,
    address             VARCHAR(255) DEFAULT NULL,
    contact_no          VARCHAR(30)  DEFAULT NULL,
    emergency_name      VARCHAR(120) DEFAULT NULL,
    emergency_contact   VARCHAR(30)  DEFAULT NULL,
    photo               VARCHAR(255) DEFAULT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB;

  -- ------------------------------------------------------------
  -- STUDENT DETAILS
  -- ------------------------------------------------------------
  CREATE TABLE student_details (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL UNIQUE,
    student_no  VARCHAR(40) NOT NULL,
    course      VARCHAR(120) DEFAULT NULL,
    year_level  VARCHAR(40)  DEFAULT NULL,
    section     VARCHAR(60)  DEFAULT NULL,
    CONSTRAINT fk_student_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB;

  -- ------------------------------------------------------------
  -- EMPLOYEE DETAILS
  -- ------------------------------------------------------------
  CREATE TABLE employee_details (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL UNIQUE,
    employee_no  VARCHAR(40) NOT NULL,
    department   VARCHAR(120) DEFAULT NULL,
    position     VARCHAR(120) DEFAULT NULL,
    CONSTRAINT fk_employee_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB;

  -- ------------------------------------------------------------
  -- MEDICAL RECORDS (semester-tracked)
  -- ------------------------------------------------------------
  CREATE TABLE medical_records (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    user_id              INT NOT NULL,
    blood_type           VARCHAR(5)   DEFAULT NULL,
    height_cm            DECIMAL(5,2) DEFAULT NULL,
    weight_kg            DECIMAL(5,2) DEFAULT NULL,
    allergies_text       TEXT         DEFAULT NULL,
    existing_conditions  TEXT         DEFAULT NULL,
    current_medications  TEXT         DEFAULT NULL,
    immunizations        TEXT         DEFAULT NULL,
    current_symptoms     TEXT         DEFAULT NULL,
    school_year          VARCHAR(20)  NOT NULL,
    semester             ENUM('1st','2nd','summer') NOT NULL,
    submitted_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_medrec_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_medrec_sem (user_id, school_year, semester)
  ) ENGINE=InnoDB;

  -- ------------------------------------------------------------
  -- USER ALLERGIES (linked to medicine/category + free text)
  -- ------------------------------------------------------------
  CREATE TABLE user_allergies (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,
    medicine_id   INT DEFAULT NULL,
    category_id   INT DEFAULT NULL,
    allergen_name VARCHAR(150) NOT NULL,
    severity      ENUM('mild','moderate','severe') NOT NULL DEFAULT 'mild',
    notes         VARCHAR(255) DEFAULT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_allergy_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB;

  -- ------------------------------------------------------------
  -- MEDICINE CATEGORIES
  -- ------------------------------------------------------------
  CREATE TABLE medicine_categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    parent_id   INT DEFAULT NULL,
    name        VARCHAR(100) NOT NULL,
    type        ENUM('medicine','first_aid') NOT NULL DEFAULT 'medicine',
    description VARCHAR(255) DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_medcat_parent_name (parent_id, name),
    CONSTRAINT fk_medcat_parent FOREIGN KEY (parent_id) REFERENCES medicine_categories(id) ON DELETE CASCADE
  ) ENGINE=InnoDB;

  -- ------------------------------------------------------------
  -- SUPPLIERS
  -- ------------------------------------------------------------
  CREATE TABLE suppliers (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(150) NOT NULL,
    contact_person VARCHAR(120) DEFAULT NULL,
    phone         VARCHAR(30)  DEFAULT NULL,
    email         VARCHAR(120) DEFAULT NULL,
    address       VARCHAR(255) DEFAULT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB;

  -- ------------------------------------------------------------
  -- MEDICINES
  -- ------------------------------------------------------------
  CREATE TABLE medicines (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(150) NOT NULL,
    generic_name        VARCHAR(150) DEFAULT NULL,
    category_id         INT DEFAULT NULL,
    unit                VARCHAR(40)  NOT NULL DEFAULT 'tablet',
    description         VARCHAR(255) DEFAULT NULL,
    low_stock_threshold INT NOT NULL DEFAULT 10,
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_med_category FOREIGN KEY (category_id) REFERENCES medicine_categories(id) ON DELETE SET NULL
  ) ENGINE=InnoDB;

  -- ------------------------------------------------------------
  -- MEDICINE BATCHES (lot tracking + expiry)
  -- ------------------------------------------------------------
  CREATE TABLE medicine_batches (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id   INT NOT NULL,
    batch_no      VARCHAR(60) DEFAULT NULL,
    lot_no        VARCHAR(60) DEFAULT NULL,
    supplier_id   INT DEFAULT NULL,
    quantity      INT NOT NULL DEFAULT 0,
    expiry_date   DATE DEFAULT NULL,
    date_received DATE NOT NULL,
    unit_cost     DECIMAL(10,2) DEFAULT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_batch_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    CONSTRAINT fk_batch_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
  ) ENGINE=InnoDB;

  -- ------------------------------------------------------------
  -- STOCK MOVEMENTS (audit trail IN/OUT)
  -- ------------------------------------------------------------
  CREATE TABLE stock_movements (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id  INT NOT NULL,
    batch_id     INT DEFAULT NULL,
    type         ENUM('IN','OUT') NOT NULL,
    quantity     INT NOT NULL,
    reason       VARCHAR(255) DEFAULT NULL,
    ref_type     VARCHAR(40)  DEFAULT NULL,
    ref_id       INT DEFAULT NULL,
    performed_by INT DEFAULT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_move_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    CONSTRAINT fk_move_batch FOREIGN KEY (batch_id) REFERENCES medicine_batches(id) ON DELETE SET NULL,
    CONSTRAINT fk_move_user FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL
  ) ENGINE=InnoDB;

  -- ------------------------------------------------------------
  -- MEDICINE REQUESTS
  -- ------------------------------------------------------------
  CREATE TABLE medicine_requests (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    medicine_id     INT NOT NULL,
    quantity        INT NOT NULL DEFAULT 1,
    reason          VARCHAR(255) DEFAULT NULL,
    status          ENUM('dispensed','rejected') NOT NULL DEFAULT 'dispensed',
    allergy_flag    TINYINT(1) NOT NULL DEFAULT 0,
    override_reason TEXT DEFAULT NULL,
    remarks         VARCHAR(255) DEFAULT NULL,
    requested_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_by    INT DEFAULT NULL,
    processed_at    DATETIME DEFAULT NULL,
    CONSTRAINT fk_req_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_req_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    CONSTRAINT fk_req_staff FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
  ) ENGINE=InnoDB;

  -- ------------------------------------------------------------
  -- DISPENSE LOGS
  -- ------------------------------------------------------------
  CREATE TABLE dispense_logs (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    request_id   INT DEFAULT NULL,
    user_id      INT NOT NULL,
    medicine_id  INT NOT NULL,
    batch_id     INT DEFAULT NULL,
    quantity     INT NOT NULL,
    dispensed_by INT DEFAULT NULL,
    remarks      TEXT DEFAULT NULL,
    dispensed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_disp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_disp_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    CONSTRAINT fk_disp_batch FOREIGN KEY (batch_id) REFERENCES medicine_batches(id) ON DELETE SET NULL,
    CONSTRAINT fk_disp_staff FOREIGN KEY (dispensed_by) REFERENCES users(id) ON DELETE SET NULL
  ) ENGINE=InnoDB;

  -- ------------------------------------------------------------
  -- CONSULTATIONS
  -- ------------------------------------------------------------
  CREATE TABLE consultations (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    patient_id        INT NOT NULL,
    staff_id          INT DEFAULT NULL,
    complaint         TEXT DEFAULT NULL,
    blood_pressure    VARCHAR(20) DEFAULT NULL,
    temperature       DECIMAL(4,1) DEFAULT NULL,
    pulse             INT DEFAULT NULL,
    respiration       INT DEFAULT NULL,
    weight_kg         DECIMAL(5,2) DEFAULT NULL,
    height_cm         DECIMAL(5,2) DEFAULT NULL,
    diagnosis         TEXT DEFAULT NULL,
    treatment         TEXT DEFAULT NULL,
    notes             TEXT DEFAULT NULL,
    consultation_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cons_patient FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_cons_staff FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE SET NULL
  ) ENGINE=InnoDB;

  -- ------------------------------------------------------------
  -- EQUIPMENT
  -- ------------------------------------------------------------
  CREATE TABLE equipment (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(150) NOT NULL,
    description   VARCHAR(255) DEFAULT NULL,
    total_qty     INT NOT NULL DEFAULT 1,
    available_qty INT NOT NULL DEFAULT 1,
    condition_note VARCHAR(120) DEFAULT 'good',
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB;

  -- ------------------------------------------------------------
  -- EQUIPMENT LOANS
  -- ------------------------------------------------------------
  CREATE TABLE equipment_loans (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    user_id            INT NOT NULL,
    equipment_id       INT NOT NULL,
    quantity           INT NOT NULL DEFAULT 1,
    borrow_date        DATE NOT NULL,
    borrowed_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    due_date           DATE DEFAULT NULL,
    return_date        DATE DEFAULT NULL,
    status             ENUM('borrowed','returned') NOT NULL DEFAULT 'borrowed',
    condition_on_return VARCHAR(120) DEFAULT NULL,
    issued_by          INT DEFAULT NULL,
    received_by        INT DEFAULT NULL,
    notes              VARCHAR(255) DEFAULT NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_loan_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_loan_equipment FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE,
    CONSTRAINT fk_loan_issued FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_loan_received FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
  ) ENGINE=InnoDB;

  -- ------------------------------------------------------------
  -- USER NOTIFICATIONS
  -- ------------------------------------------------------------
  CREATE TABLE notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    loan_id     INT DEFAULT NULL,
    type        VARCHAR(60) NOT NULL,
    title       VARCHAR(150) NOT NULL,
    message     TEXT NOT NULL,
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_notification_reference (loan_id, type),
    CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notification_loan FOREIGN KEY (loan_id) REFERENCES equipment_loans(id) ON DELETE CASCADE
  ) ENGINE=InnoDB;

  -- ------------------------------------------------------------
  -- MEDICAL CERTIFICATES
  -- ------------------------------------------------------------
  CREATE TABLE medical_certificates (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    cert_no        VARCHAR(40) DEFAULT NULL,
    user_id        INT NOT NULL,
    cert_type      VARCHAR(30) NOT NULL DEFAULT 'medical',
    purpose        VARCHAR(255) DEFAULT NULL,
    findings       TEXT DEFAULT NULL,
    recommendation TEXT DEFAULT NULL,
    rest_days      INT DEFAULT NULL,
    issued_by      INT DEFAULT NULL,
    issued_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cert_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_cert_staff FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL
  ) ENGINE=InnoDB;

  -- ------------------------------------------------------------
  -- AUDIT LOGS
  -- ------------------------------------------------------------
  CREATE TABLE audit_logs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT DEFAULT NULL,
    action     VARCHAR(120) NOT NULL,
    table_name VARCHAR(80)  DEFAULT NULL,
    record_id  INT DEFAULT NULL,
    details    TEXT DEFAULT NULL,
    ip_address VARCHAR(45)  DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
  ) ENGINE=InnoDB;

  -- ------------------------------------------------------------
  -- SYSTEM SETTINGS
  -- ------------------------------------------------------------
  CREATE TABLE system_settings (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    setting_key   VARCHAR(80) NOT NULL UNIQUE,
    setting_value TEXT DEFAULT NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB;

  -- ============================================================
  -- SEED DATA
  -- ============================================================

  -- Default demo accounts.
  --   admin / admin123 | staff / staff123 | student / student123 | employee / employee123
  INSERT INTO users (id, username, email, password_hash, role, account_type, status) VALUES
  (1, 'admin',   'admin@school.edu',   '$2y$10$.U.FXgti./.NCIDY3/wp3OHMRjf1cC1aoyWNo6QddQhNirlu8tIAa', 'admin', 'employee', 'active'),
  (2, 'staff',   'staff@school.edu',   '$2y$10$tRpzsYEbyJvgVc.47k7vEOa07rvCXwXHW1sKgCkxKVgLuDm6oI4PW', 'staff', 'employee', 'active'),
  (3, 'student', 'student@school.edu', '$2y$10$T9Jv20yfcvW6QCozk2FU7es6NTNCHLBCZd7BgiQnxqzDmmNROO8Ey', 'user',  'student',  'active'),
  (4, 'employee','employee@school.edu','$2y$10$MlSJWGdvUgo7Z0f3hdHzMO835EZXSo5buPMnO/x1pOwb.uYsM9evm', 'user', 'employee', 'active');

  INSERT INTO profiles (user_id, first_name, middle_name, last_name, gender, birthdate, address, contact_no, emergency_name, emergency_contact) VALUES
  (1, 'System', 'A', 'Administrator', 'male',   '1985-01-15', 'School Campus', '09000000001', 'Registrar', '09000000011'),
  (2, 'Clinic', 'B', 'Staff',         'female', '1990-05-20', 'School Campus', '09000000002', 'Nurse',     '09000000012'),
  (3, 'Juan',   'C', 'Dela Cruz',     'male',   '2005-08-10', 'Student Dorm',  '09000000003', 'Parent',    '09000000013'),
  (4, 'Maria',  'D', 'Santos',        'female', '1988-04-12', 'School Campus', '09000000004', 'Family',    '09000000014');

  INSERT INTO employee_details (user_id, employee_no, department, position) VALUES
  (1, 'EMP-0001', 'Administration', 'System Administrator'),
  (2, 'EMP-0002', 'Clinic',         'Clinic Staff'),
  (4, 'EMP-0003', 'Registrar',      'Administrative Officer');

  INSERT INTO student_details (user_id, student_no, course, year_level, section) VALUES
  (3, '2024-00001', 'BS Information Technology', '1st Year', 'A');

  INSERT INTO medical_records (user_id, blood_type, height_cm, weight_kg, allergies_text, existing_conditions, current_medications, immunizations, school_year, semester) VALUES
  (3, 'O+', 170.00, 60.00, 'Seafood, Dust', 'None', 'None', 'COVID-19, Tetanus', '2025-2026', '1st');

  INSERT INTO medicine_categories (id, parent_id, name, type, description) VALUES
  (1, NULL, 'Medicines', 'medicine', 'Over-the-counter medicines for common school clinic use'),
  (2, 1, 'Analgesics', 'medicine', 'Pain relievers and fever reducers'),
  (3, 1, 'Antihistamines', 'medicine', 'Allergy relief and symptom control'),
  (4, 1, 'Antacids', 'medicine', 'Gastric and acidity relief'),
  (5, 1, 'Vitamins & Supplements', 'medicine', 'Dietary supplements and wellness support'),
  (6, NULL, 'First Aid / Wound Care', 'first_aid', 'Clinic wound care and emergency care consumables'),
  (7, 6, 'Cleansing & Antiseptics', 'first_aid', 'Skin cleansing and antiseptic solutions'),
  (8, 6, 'Topical Treatments & Ointments', 'first_aid', 'Creams, gels, and topical treatment products'),
  (9, 6, 'Primary Dressings', 'first_aid', 'Direct wound contact dressings and pads'),
  (10, 6, 'Secondary Dressings & Securement', 'first_aid', 'Wraps, tapes, and securing materials'),
  (11, 6, 'Wound Closures & Care Instruments', 'first_aid', 'Closures and small clinical care tools');

  INSERT INTO suppliers (id, name, contact_person, phone, email, address) VALUES
  (1, 'MedSupply Co.',   'Maria Santos',  '09171234567', 'sales@medsupply.com',  'Manila'),
  (2, 'HealthPlus Inc.', 'Pedro Reyes',   '09181234567', 'info@healthplus.com',  'Quezon City');

  INSERT INTO medicines (id, name, generic_name, category_id, unit, description, low_stock_threshold) VALUES
  (1, 'Biogesic', 'Paracetamol', 2, 'tablet', 'Fever and pain reliever', 20),
  (2, 'Paracetamol', 'Paracetamol', 2, 'tablet', 'Generic fever and pain reliever', 20),
  (3, 'Cetirizine', 'Cetirizine', 3, 'tablet', 'Antihistamine for allergies', 15),
  (4, 'Kremil-S', 'Antacid', 4, 'tablet', 'Antacid for hyperacidity', 20),
  (5, 'Vitamin C', 'Ascorbic Acid', 5, 'tablet', 'Immune support supplement', 30),
  (6, 'Antiseptic Wipes', 'Alcohol Prep Pad', 7, 'box', 'Pre-moistened antiseptic cleansing wipes', 20),
  (7, 'Rubbing Alcohol', 'Isopropyl Alcohol', 7, 'bottle', 'Skin antiseptic and cleansing solution', 20),
  (8, 'Saline Wash', 'Normal Saline', 7, 'bottle', 'Sterile wound irrigation solution', 20),
  (9, 'Triple Antibiotic Ointment', 'Bacitracin Blend', 8, 'tube', 'Antibiotic topical ointment', 15),
  (10, 'Hydrocortisone Cream', 'Hydrocortisone 1%', 8, 'tube', 'Anti-itch and inflammation relief', 15),
  (11, 'Burn Gel', 'Aloe Burn Relief', 8, 'tube', 'Cooling burn treatment gel', 15),
  (12, 'Adhesive Bandages', 'Assorted Bandaids', 9, 'box', 'Assorted adhesive bandages for minor cuts', 30),
  (13, 'Sterile Gauze Pads', '2x2 Gauze', 9, 'box', 'Sterile gauze pads 2x2', 20),
  (14, 'Sterile Gauze Pads', '4x4 Gauze', 9, 'box', 'Sterile gauze pads 4x4', 20),
  (15, 'Non-Stick Pads', 'Non-stick Wound Dressing', 9, 'box', 'Non-stick pads for wounds and burns', 20),
  (16, 'Rolled Gauze', 'Kerlix', 10, 'roll', 'Stretch gauze bandage wrap', 15),
  (17, 'Paper Tape', 'Hypoallergenic Paper Tape', 10, 'roll', 'Hypoallergenic securing tape', 15),
  (18, 'Elastic Bandage', 'ACE Wrap', 10, 'roll', 'Elastic compression wrap', 15),
  (19, 'Butterfly Closures', 'Butterfly Strip', 11, 'box', 'Sterile wound closure strips', 15),
  (20, 'Trauma Shears', 'Trauma Shears', 11, 'piece', 'Stainless steel trauma shears', 5),
  (21, 'Fine Tip Tweezers', 'Fine Tip Tweezers', 11, 'piece', 'Precision tweezers for splinters', 5);

  INSERT INTO medicine_batches (medicine_id, batch_no, lot_no, supplier_id, quantity, expiry_date, date_received, unit_cost) VALUES
  (1, 'BIO-2025A', 'LOT-001', 1, 100, '2027-01-31', '2025-06-01', 2.50),
  (2, 'PAR-2025A', 'LOT-002', 1, 100, '2027-03-31', '2025-06-01', 1.80),
  (3, 'CET-2025A', 'LOT-003', 2, 60, '2027-02-28', '2025-06-01', 5.00),
  (4, 'KRE-2025A', 'LOT-004', 1, 80, '2027-05-31', '2025-06-01', 6.50),
  (5, 'VIT-2025A', 'LOT-005', 2, 200, '2027-08-31', '2025-06-01', 3.00),
  (6, 'AW-2025A', 'LOT-006', 1, 30, NULL, '2025-06-01', 0.90),
  (7, 'ALC-2025A', 'LOT-007', 2, 18, NULL, '2025-06-01', 3.25),
  (8, 'SAL-2025A', 'LOT-008', 1, 12, NULL, '2025-06-01', 4.80),
  (9, 'OINT-2025A', 'LOT-009', 2, 24, '2027-02-28', '2025-06-01', 7.50),
  (10, 'CREAM-2025A', 'LOT-010', 1, 20, '2027-03-31', '2025-06-01', 6.25),
  (11, 'BURN-2025A', 'LOT-011', 2, 16, '2027-04-30', '2025-06-01', 8.10),
  (12, 'BAND-2025A', 'LOT-012', 1, 40, NULL, '2025-06-01', 1.20),
  (13, 'GAU2-2025A', 'LOT-013', 2, 36, NULL, '2025-06-01', 1.80),
  (14, 'GAU4-2025A', 'LOT-014', 2, 32, NULL, '2025-06-01', 2.50),
  (15, 'PAD-2025A', 'LOT-015', 1, 28, NULL, '2025-06-01', 2.20),
  (16, 'ROLL-2025A', 'LOT-016', 2, 25, NULL, '2025-06-01', 3.60),
  (17, 'TAPE-2025A', 'LOT-017', 1, 20, NULL, '2025-06-01', 2.90),
  (18, 'ACE-2025A', 'LOT-018', 2, 18, NULL, '2025-06-01', 4.00),
  (19, 'CLOS-2025A', 'LOT-019', 1, 24, NULL, '2025-06-01', 1.10),
  (20, 'SHEAR-2025A', 'LOT-020', 2, 6, NULL, '2025-06-01', 12.00),
  (21, 'TWEE-2025A', 'LOT-021', 1, 6, NULL, '2025-06-01', 11.00);

  INSERT INTO equipment (id, name, description, total_qty, available_qty, condition_note) VALUES
  (1, 'Wheelchair',        'Standard folding wheelchair', 2, 2, 'good'),
  (2, 'Nebulizer',         'Compressor nebulizer kit',    2, 2, 'good'),
  (3, 'Crutches',          'Adjustable aluminum crutches',3, 3, 'good'),
  (4, 'Digital Thermometer','Non-contact thermometer',    5, 5, 'good'),
  (5, 'BP Monitor',        'Digital blood pressure monitor',3, 3, 'good');

  INSERT INTO system_settings (setting_key, setting_value) VALUES
  ('school_name', 'School Clinic'),
  ('clinic_name', 'School Health Clinic'),
  ('clinic_address', 'Main Campus, School Grounds'),
  ('clinic_contact', '09000000000'),
  ('medicine_request_daily_limit', '1'),
  ('current_school_year', '2025-2026'),
  ('current_semester', '1st');

  -- ------------------------------------------------------------
  -- FAILED LOGINS (brute-force protection)
  -- ------------------------------------------------------------
  CREATE TABLE IF NOT EXISTS failed_logins (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50) NOT NULL,
    ip_address    VARCHAR(45) DEFAULT NULL,
    attempted_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_failed_username (username),
    INDEX idx_failed_ip (ip_address)
  ) ENGINE=InnoDB;

  -- ------------------------------------------------------------
  -- STOCK ADJUSTMENTS
  -- ------------------------------------------------------------
  CREATE TABLE stock_adjustments (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id   INT NOT NULL,
    batch_id      INT DEFAULT NULL,
    adjustment_no VARCHAR(40) NOT NULL UNIQUE,
    type          ENUM('IN','OUT') NOT NULL,
    quantity      INT NOT NULL,
    reason        VARCHAR(255) NOT NULL,
    performed_by  INT DEFAULT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_adjust_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    CONSTRAINT fk_adjust_batch FOREIGN KEY (batch_id) REFERENCES medicine_batches(id) ON DELETE SET NULL,
    CONSTRAINT fk_adjust_user FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL
  ) ENGINE=InnoDB;