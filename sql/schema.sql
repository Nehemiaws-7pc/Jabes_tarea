-- MySQL 8.4. Inicialización de una base nueva, sin tasas ni datos inventados.
CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tax_id VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    address VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    price_usd DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (price_usd > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS exchange_rates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_date DATE NOT NULL,
    rate DECIMAL(12,6) NOT NULL,
    fetched_at DATETIME NOT NULL,
    source VARCHAR(30) NOT NULL DEFAULT 'BANGUAT_SOAP',
    CHECK (rate > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sales (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_key CHAR(36) NOT NULL UNIQUE,
    request_hash CHAR(64) NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    customer_tax_id VARCHAR(30) NOT NULL,
    customer_name VARCHAR(120) NOT NULL,
    customer_address VARCHAR(255) NOT NULL,
    exchange_rate_id INT UNSIGNED NOT NULL,
    exchange_rate DECIMAL(12,6) NOT NULL,
    exchange_date DATE NOT NULL,
    rate_fetched_at DATETIME NOT NULL,
    rate_usage ENUM('current', 'saved') NOT NULL,
    payment_method ENUM('Efectivo', 'Tarjeta', 'Transferencia') NOT NULL,
    subtotal_usd DECIMAL(12,2) NOT NULL,
    tax_percent DECIMAL(5,2) NOT NULL,
    tax_usd DECIMAL(12,2) NOT NULL,
    total_usd DECIMAL(12,2) NOT NULL,
    total_gtq DECIMAL(16,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (exchange_rate_id) REFERENCES exchange_rates(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sale_details (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    product_code VARCHAR(40) NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price_usd DECIMAL(10,2) NOT NULL,
    subtotal_usd DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id),
    FOREIGN KEY (product_id) REFERENCES products(id),
    UNIQUE KEY sale_product (sale_id, product_id),
    CHECK (quantity > 0)
) ENGINE=InnoDB;

