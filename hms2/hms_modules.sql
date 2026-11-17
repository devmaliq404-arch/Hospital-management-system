-- HMS modules: admissions, laboratory, pharmacy, settings (+ sample data)
-- Run once in phpMyAdmin (SQL tab) on the `hospitalmanagement` database.
-- Needs at least 3 patients to exist (the earlier hms_reset.sql added 3).

CREATE TABLE IF NOT EXISTS admissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    bed_id INT NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'Admitted',          -- 'Admitted' or 'Discharged'
    admitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    discharged_at DATETIME DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS lab_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    test_name VARCHAR(100) NOT NULL,
    requested_by VARCHAR(120) DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'Pending',           -- Pending / In progress / Completed
    result VARCHAR(255) DEFAULT NULL,
    test_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS medications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    category VARCHAR(60) DEFAULT NULL,
    stock INT NOT NULL DEFAULT 0,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL DEFAULT 10,
    expiry_date DATE DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value VARCHAR(255) DEFAULT NULL
);

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('hospital_name', 'HMS Hospital'),
('hospital_phone', ''),
('hospital_email', ''),
('hospital_address', '');

-- Sample medications
INSERT INTO medications (name, category, stock, unit_price, reorder_level, expiry_date) VALUES
('Paracetamol 500mg', 'Analgesic', 18, 150.00, 50, DATE_ADD(CURDATE(), INTERVAL 400 DAY)),
('Amoxicillin 500mg', 'Antibiotic', 240, 450.00, 60, DATE_ADD(CURDATE(), INTERVAL 300 DAY)),
('Artemether/Lumefantrine', 'Antimalarial', 120, 1800.00, 40, DATE_ADD(CURDATE(), INTERVAL 60 DAY)),
('Metformin 500mg', 'Antidiabetic', 300, 200.00, 80, DATE_ADD(CURDATE(), INTERVAL 500 DAY)),
('Amlodipine 5mg', 'Antihypertensive', 90, 250.00, 40, DATE_ADD(CURDATE(), INTERVAL 450 DAY)),
('Oral Rehydration Salts', 'Rehydration', 25, 100.00, 30, DATE_ADD(CURDATE(), INTERVAL 200 DAY)),
('Ibuprofen 400mg', 'Analgesic', 150, 120.00, 50, DATE_ADD(CURDATE(), INTERVAL 350 DAY)),
('Vitamin C 1000mg', 'Supplement', 200, 80.00, 40, DATE_ADD(CURDATE(), INTERVAL 20 DAY));

-- Sample medical records, lab tests and bills for the first three patients
INSERT INTO medical_records (patient_id, doctor_name, diagnosis, treatment, record_date) VALUES
((SELECT id FROM patients ORDER BY id LIMIT 1 OFFSET 0), 'Dr. Okafor', 'Hypertension', 'Amlodipine 5mg daily. Review in 2 weeks. Reduce salt intake.', CURDATE()),
((SELECT id FROM patients ORDER BY id LIMIT 1 OFFSET 1), 'Dr. Bello', 'Uncomplicated malaria', 'Artemether/Lumefantrine 3-day course. Plenty of fluids.', CURDATE());

INSERT INTO lab_tests (patient_id, test_name, requested_by, status, result, test_date) VALUES
((SELECT id FROM patients ORDER BY id LIMIT 1 OFFSET 0), 'Lipid profile', 'Dr. Okafor', 'Completed', 'Total cholesterol 5.9 mmol/L', CURDATE()),
((SELECT id FROM patients ORDER BY id LIMIT 1 OFFSET 1), 'Malaria parasite test', 'Dr. Bello', 'Pending', NULL, CURDATE()),
((SELECT id FROM patients ORDER BY id LIMIT 1 OFFSET 2), 'X-ray', 'Dr. Adeyemi', 'In progress', NULL, CURDATE());

INSERT INTO bills (patient_id, description, amount, status, bill_date) VALUES
((SELECT id FROM patients ORDER BY id LIMIT 1 OFFSET 0), 'Cardiology consultation', 15000.00, 'Paid', CURDATE()),
((SELECT id FROM patients ORDER BY id LIMIT 1 OFFSET 1), 'Pediatric consultation and malaria test', 9500.00, 'Unpaid', CURDATE()),
((SELECT id FROM patients ORDER BY id LIMIT 1 OFFSET 2), 'Orthopedic consultation and X-ray', 22000.00, 'Unpaid', CURDATE());
