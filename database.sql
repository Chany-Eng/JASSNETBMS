-- JASSNET Business Management System Database Schema

CREATE DATABASE IF NOT EXISTS jassnet_bms;
USE jassnet_bms;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('Sales', 'Technician', 'Store Keeper', 'Manager', 'Director', 'Accountant', 'Super Admin') NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    profile_photo VARCHAR(255),
    employee_id VARCHAR(50) UNIQUE,
    password_last_changed DATE DEFAULT CURRENT_DATE,
    is_active BOOLEAN DEFAULT TRUE
);

-- Income table
CREATE TABLE income (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    service_type ENUM('WiFi Voucher', 'Installation', 'Router Sale', 'Subscription', 'Other') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('Cash', 'Mobile Money', 'Bank') NOT NULL,
    transaction_reference VARCHAR(100),
    notes TEXT,
    receipt_file VARCHAR(255),
    user_id INT,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Expense requests table
CREATE TABLE expense_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_date DATE DEFAULT CURRENT_DATE,
    requested_by INT,
    department VARCHAR(50),
    category ENUM('Fuel', 'Equipment', 'Maintenance', 'Transport', 'Salary', 'Other'),
    description TEXT,
    amount_requested DECIMAL(10,2),
    reason TEXT,
    project_ref VARCHAR(100),
    notes TEXT,
    status ENUM('Pending Manager Approval', 'Pending Director Approval', 'Pending Accountant Processing', 'Waiting for Receipt', 'Completed', 'Rejected') DEFAULT 'Pending Manager Approval',
    manager_approved BOOLEAN DEFAULT NULL,
    director_approved BOOLEAN DEFAULT NULL,
    accountant_processed BOOLEAN DEFAULT NULL,
    receipt_uploaded BOOLEAN DEFAULT NULL,
    FOREIGN KEY (requested_by) REFERENCES users(id)
);

-- Expense payments table
CREATE TABLE expense_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expense_request_id INT,
    amount_paid DECIMAL(10,2),
    payment_method VARCHAR(50),
    payment_date DATE,
    accountant_id INT,
    FOREIGN KEY (expense_request_id) REFERENCES expense_requests(id),
    FOREIGN KEY (accountant_id) REFERENCES users(id)
);

-- Receipts table
CREATE TABLE receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expense_request_id INT,
    receipt_file VARCHAR(255),
    vendor_name VARCHAR(100),
    receipt_number VARCHAR(100),
    actual_amount DECIMAL(10,2),
    notes TEXT,
    FOREIGN KEY (expense_request_id) REFERENCES expense_requests(id)
);

-- Inventory table
CREATE TABLE inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(100) NOT NULL,
    category ENUM('Router', 'Cable', 'Antenna', 'Tools', 'Accessories') NOT NULL,
    quantity INT NOT NULL,
    purchase_price DECIMAL(10,2),
    selling_price DECIMAL(10,2),
    supplier VARCHAR(100),
    purchase_date DATE,
    status ENUM('Available', 'Installed', 'Damaged') DEFAULT 'Available',
    notes TEXT
);

-- Equipment requests table
CREATE TABLE equipment_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_date DATE DEFAULT CURRENT_DATE,
    requested_by INT,
    item_id INT,
    quantity INT,
    reason TEXT,
    project VARCHAR(100),
    status ENUM('Pending', 'Approved', 'Issued', 'Rejected') DEFAULT 'Pending',
    approved_by INT,
    issued_by INT,
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (item_id) REFERENCES inventory(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (issued_by) REFERENCES users(id)
);

-- Station requests table
CREATE TABLE station_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_date DATE DEFAULT CURRENT_DATE,
    requested_by INT,
    station_name VARCHAR(100),
    location VARCHAR(255),
    gps VARCHAR(50),
    description TEXT,
    coverage_area VARCHAR(100),
    installation_type ENUM('Hotspot', 'Tower', 'Relay', 'Fiber Node'),
    total_estimated_cost DECIMAL(10,2),
    status ENUM('Pending Approval', 'Approved', 'Equipment Issued', 'Installation in Progress', 'Completed', 'Rejected') DEFAULT 'Pending Approval',
    approved_by INT,
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- Station equipment table
CREATE TABLE station_equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    station_request_id INT,
    equipment_name VARCHAR(100),
    quantity INT,
    source ENUM('Inventory', 'Purchase'),
    inventory_id INT NULL,
    purchase_cost DECIMAL(10,2),
    supplier VARCHAR(100),
    FOREIGN KEY (station_request_id) REFERENCES station_requests(id),
    FOREIGN KEY (inventory_id) REFERENCES inventory(id)
);

-- Station progress table
CREATE TABLE station_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    station_request_id INT,
    status VARCHAR(50),
    date DATE DEFAULT CURRENT_DATE,
    notes TEXT,
    FOREIGN KEY (station_request_id) REFERENCES station_requests(id)
);

-- Insert sample data for testing
INSERT INTO users (username, password, role, full_name, employee_id) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Admin', 'Super Admin', 'ADM001'),
('manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Manager', 'John Manager', 'MGR001'),
('sales', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sales', 'Jane Sales', 'SAL001');

-- Sample inventory
INSERT INTO inventory (item_name, category, quantity, purchase_price, selling_price, supplier) VALUES 
('TP-Link Router', 'Router', 10, 50.00, 70.00, 'Tech Supplier'),
('Ethernet Cable', 'Cable', 100, 5.00, 10.00, 'Cable Co');