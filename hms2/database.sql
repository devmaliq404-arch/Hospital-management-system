-- Run this in phpMyAdmin (SQL tab) on your HMS database.
-- It only creates tables that don't exist yet, so it is safe to re-run.
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(30) DEFAULT 'Staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_code VARCHAR(20) DEFAULT NULL,
    name VARCHAR(120) NOT NULL,
    age INT NOT NULL,
    gender VARCHAR(10) NOT NULL,
    phone VARCHAR(25) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    blood_group VARCHAR(5) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    specialty VARCHAR(80) NOT NULL,
    phone VARCHAR(25) DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'Active'
);

CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_name VARCHAR(120) NOT NULL,
    doctor_name VARCHAR(120) NOT NULL,
    department VARCHAR(80) NOT NULL,
    appt_date DATE NOT NULL,
    appt_time VARCHAR(15) NOT NULL,
    status VARCHAR(20) DEFAULT 'Scheduled'
);

CREATE TABLE IF NOT EXISTS beds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ward VARCHAR(60) NOT NULL,
    status VARCHAR(20) DEFAULT 'available'  -- 'occupied' or 'available'
);

-- Sample data so the dashboard shows real numbers
INSERT INTO doctors (name, specialty) VALUES
('Dr. Okafor', 'Cardiology'),
('Dr. Bello', 'Pediatrics'),
('Dr. Adeyemi', 'Orthopedics'),
('Dr. Ibrahim', 'Neurology');

INSERT INTO patients (patient_code, name, age, gender, phone, blood_group) VALUES
('PT-00001', 'Adebayo Johnson', 52, 'Male', '08031234567', 'O+'),
('PT-00002', 'Funke Alabi', 8, 'Female', '08127654321', 'A+'),
('PT-00003', 'Chinedu Eze', 41, 'Male', '09011112222', 'B+');

INSERT INTO appointments (patient_name, doctor_name, department, appt_date, appt_time, status) VALUES
('Adebayo Johnson', 'Dr. Okafor', 'Cardiology', CURDATE(), '09:00 AM', 'Completed'),
('Funke Alabi', 'Dr. Bello', 'Pediatrics', CURDATE(), '10:30 AM', 'In progress'),
('Chinedu Eze', 'Dr. Adeyemi', 'Orthopedics', CURDATE(), '11:15 AM', 'Waiting');

INSERT INTO beds (ward, status) VALUES
('Emergency','occupied'),('Emergency','occupied'),('Emergency','available'),
('Cardiology','occupied'),('Cardiology','available'),
('Maternity','occupied'),('Maternity','available'),
('Surgery','available'),('Surgery','occupied'),('Pediatrics','available');
