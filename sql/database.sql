CREATE DATABASE IF NOT EXISTS aj_alfresco_rms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE aj_alfresco_rms;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  phone VARCHAR(30),
  secondary_phone VARCHAR(30),
  business_name VARCHAR(150),
  business_type VARCHAR(100),
  address TEXT,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','tenant') NOT NULL DEFAULT 'tenant',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE stalls (
  id INT AUTO_INCREMENT PRIMARY KEY,
  stall_number VARCHAR(20) NOT NULL UNIQUE,
  stall_name VARCHAR(100),
  location_description VARCHAR(255),
  monthly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  size_sqm DECIMAL(6,2),
  status ENUM('available','occupied','maintenance') NOT NULL DEFAULT 'available',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE contracts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  stall_id INT NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  monthly_rent DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  deposit_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  terms TEXT,
  duration_type VARCHAR(50) NOT NULL DEFAULT '1 year',
  status ENUM('active','expired','pending_renewal','terminated') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_contracts_tenant FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_contracts_stall FOREIGN KEY (stall_id) REFERENCES stalls(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contract_id INT NOT NULL,
  tenant_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  payment_date DATE NOT NULL,
  payment_for_month VARCHAR(20) NOT NULL,
  payment_method ENUM('cash','gcash','bank_transfer','other') NOT NULL DEFAULT 'cash',
  reference_number VARCHAR(100),
  receipt_number VARCHAR(50) NOT NULL UNIQUE,
  operator VARCHAR(150),
  notes TEXT,
  status ENUM('paid','pending','overdue','partial') NOT NULL DEFAULT 'paid',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
  CONSTRAINT fk_payments_tenant FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  message TEXT NOT NULL,
  type ENUM('due_date','contract_expiry','payment','general') NOT NULL DEFAULT 'general',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Default Admin Account
-- Email: admin@ajalfresco.com
-- Password: admin123
INSERT INTO users(full_name,email,phone,password,role,status) VALUES
('A&J Alfresco Admin','admin@ajalfresco.com','09171234567',
'$2y$10$XphWBZgND/N2tGi6ItH26.4CqKsvcWBcrYBgMbNoUCSDxDly2lWSG',
'admin','active');

