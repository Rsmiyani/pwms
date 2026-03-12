-- Party Worker Management System - Database Setup
-- Run this SQL in phpMyAdmin or MySQL CLI

CREATE DATABASE IF NOT EXISTS pwms CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE pwms;

-- Users table (for both admin and workers)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(15) NOT NULL UNIQUE,
    email VARCHAR(100) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'worker') NOT NULL DEFAULT 'worker',
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Areas table
CREATE TABLE IF NOT EXISTS areas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    constituency VARCHAR(100) NOT NULL,
    ward VARCHAR(100) DEFAULT NULL,
    booth_code VARCHAR(50) DEFAULT NULL,
    booth_name VARCHAR(100) DEFAULT NULL,
    latitude DECIMAL(10, 8) DEFAULT NULL,
    longitude DECIMAL(11, 8) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Workers table
CREATE TABLE IF NOT EXISTS workers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(15) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    role VARCHAR(100) DEFAULT 'Volunteer',
    party_position VARCHAR(100) DEFAULT NULL,
    constituency VARCHAR(100) DEFAULT NULL,
    ward VARCHAR(100) DEFAULT NULL,
    booth VARCHAR(100) DEFAULT NULL,
    responsibility_type ENUM('Door-to-door', 'Social Media', 'Event Management', 'Data Collection', 'Call Outreach', 'Other') DEFAULT 'Door-to-door',
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tasks table
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    constituency VARCHAR(100) DEFAULT NULL,
    ward VARCHAR(100) DEFAULT NULL,
    booth VARCHAR(100) DEFAULT NULL,
    priority ENUM('Low', 'Medium', 'High') NOT NULL DEFAULT 'Medium',
    campaign_type VARCHAR(100) DEFAULT NULL,
    due_date DATE DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Task assignments table
CREATE TABLE IF NOT EXISTS task_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    worker_id INT NOT NULL,
    status ENUM('Pending', 'In Progress', 'Completed') NOT NULL DEFAULT 'Pending',
    remarks TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('task_assigned', 'task_updated', 'general') NOT NULL DEFAULT 'general',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    link VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Worker check-ins (geolocation tracking)
CREATE TABLE IF NOT EXISTS worker_checkins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    worker_id INT NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    location_name VARCHAR(255) DEFAULT NULL,
    type ENUM('check-in', 'check-out') NOT NULL DEFAULT 'check-in',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Task proof uploads
CREATE TABLE IF NOT EXISTS task_proofs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES task_assignments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Worker points log (gamification)
CREATE TABLE IF NOT EXISTS worker_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    worker_id INT NOT NULL,
    points INT NOT NULL,
    reason VARCHAR(200) NOT NULL,
    reference_type ENUM('task', 'checkin', 'proof', 'badge', 'manual') NOT NULL DEFAULT 'manual',
    reference_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Worker badges earned
CREATE TABLE IF NOT EXISTS worker_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    worker_id INT NOT NULL,
    badge_key VARCHAR(50) NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_badge (worker_id, badge_key)
) ENGINE=InnoDB;

-- Voter feedback with AI sentiment analysis
CREATE TABLE IF NOT EXISTS voter_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    worker_id INT NOT NULL,
    voter_name VARCHAR(100) DEFAULT NULL,
    ward VARCHAR(100) NOT NULL,
    constituency VARCHAR(100) DEFAULT NULL,
    feedback_text TEXT NOT NULL,
    sentiment ENUM('Positive','Neutral','Negative') NOT NULL DEFAULT 'Neutral',
    sentiment_score DECIMAL(5,3) DEFAULT 0.000,
    confidence DECIMAL(3,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Saved performance reports
CREATE TABLE IF NOT EXISTS performance_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_title VARCHAR(200) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    report_html LONGTEXT NOT NULL,
    generated_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Login attempts tracking (brute-force protection)
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(15) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone_time (phone, attempted_at),
    INDEX idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB;

-- Insert default admin user (password: admin123)
INSERT INTO users (name, phone, email, password, role) VALUES
('Admin', '9999999999', 'admin@pwms.com', '$2y$10$e0qLL4chvFxKHs94t8EZm.ilFzz2LHA/h//kbRLe2JLc5b0s1HwoC', 'admin');

-- Insert some sample areas
INSERT INTO areas (constituency, ward, booth_code, booth_name, latitude, longitude) VALUES
('Constituency A', 'Ward 1', 'B001', 'Booth 1', 23.02250000, 72.57140000),
('Constituency A', 'Ward 1', 'B002', 'Booth 2', 23.03000000, 72.58000000),
('Constituency A', 'Ward 2', 'B003', 'Booth 3', 23.01500000, 72.56000000),
('Constituency B', 'Ward 3', 'B004', 'Booth 4', 23.04000000, 72.55000000),
('Constituency B', 'Ward 4', 'B005', 'Booth 5', 23.00800000, 72.59000000);

-- Insert some sample workers
INSERT INTO workers (name, phone, email, role, party_position, constituency, ward, booth, responsibility_type, status) VALUES
('Rajesh Kumar', '9876543210', 'rajesh@email.com', 'Booth President', 'President', 'Constituency A', 'Ward 1', 'Booth 1', 'Door-to-door', 'Active'),
('Priya Sharma', '9876543211', 'priya@email.com', 'Volunteer', NULL, 'Constituency A', 'Ward 1', 'Booth 2', 'Social Media', 'Active'),
('Amit Patel', '9876543212', 'amit@email.com', 'Mandal Head', 'Secretary', 'Constituency A', 'Ward 2', 'Booth 3', 'Event Management', 'Active'),
('Suman Devi', '9876543213', 'suman@email.com', 'Volunteer', NULL, 'Constituency B', 'Ward 3', 'Booth 4', 'Data Collection', 'Active'),
('Vikram Singh', '9876543214', 'vikram@email.com', 'Volunteer', NULL, 'Constituency B', 'Ward 4', 'Booth 5', 'Call Outreach', 'Active');
