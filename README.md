CREATE DATABASE bakehouse;
USE bakehouse;

-- ==============================
-- Users Table
-- ==============================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    mobile VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    district VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('customer', 'admin', 'manager') DEFAULT 'customer' NOT NULL,
    date_joined DATE NOT NULL,
    profile_picture VARCHAR(255) DEFAULT NULL,
    last_login TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- Orders Table
-- ==============================
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    order_date DATE NOT NULL,
    quantity INT DEFAULT 1,
    status ENUM('Pending','Processing','Shipped','Delivered','Cancelled') DEFAULT 'Pending' NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_orders_order_date (order_date),
    KEY idx_orders_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- Products Table
-- ==============================
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    description TEXT,
    image VARCHAR(255),
    quantity INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- Special Products Table
-- ==============================
CREATE TABLE s_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    description TEXT,
    image VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- Newsletter Table
-- ==============================
CREATE TABLE newsletter (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- Bookings Table
-- ==============================
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bookingId VARCHAR(50) UNIQUE NOT NULL,
    customerName VARCHAR(100) NOT NULL,
    date DATE NOT NULL,
    time TIME NOT NULL,
    tableNumber INT NOT NULL,
    status VARCHAR(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- Bills Table
-- ==============================
CREATE TABLE bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE bill_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    qty INT NOT NULL,
    FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- Sales Table
-- ==============================
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    customer VARCHAR(255) NOT NULL,
    quantity INT DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    status ENUM('Pending','Paid','Completed','Cancelled') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_sales_date (date),
    KEY idx_sales_customer (customer),
    KEY idx_sales_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- Stock Table
-- ==============================
CREATE TABLE stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partNumber VARCHAR(50) NOT NULL,
    date DATE NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity INT DEFAULT 0,
    category VARCHAR(100) NOT NULL,
    status ENUM('In Stock','Low','Out of Stock') DEFAULT 'In Stock',
    unit VARCHAR(20) NOT NULL DEFAULT 'pcs'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- OTP Table
-- ==============================
CREATE TABLE otp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    otp VARCHAR(6) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
