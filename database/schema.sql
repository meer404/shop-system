-- Purchase & Sales Manager schema (with same tables/views)
DROP TABLE IF EXISTS sale_items;
DROP TABLE IF EXISTS sales;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS customers;
DROP VIEW IF EXISTS v_customer_totals;
DROP VIEW IF EXISTS v_customer_balance;

CREATE TABLE customers (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, phone VARCHAR(50) DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE products (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, price DECIMAL(10,2) NOT NULL DEFAULT 0.00, stock INT NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE sales (id INT AUTO_INCREMENT PRIMARY KEY, customer_id INT NOT NULL, sale_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00, paid DECIMAL(10,2) NOT NULL DEFAULT 0.00, is_credit TINYINT(1) NOT NULL DEFAULT 0, FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE ON UPDATE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE sale_items (id INT AUTO_INCREMENT PRIMARY KEY, sale_id INT NOT NULL, product_id INT NOT NULL, qty INT NOT NULL DEFAULT 1, price DECIMAL(10,2) NOT NULL DEFAULT 0.00, line_total DECIMAL(10,2) NOT NULL DEFAULT 0.00, FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE ON UPDATE CASCADE, FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT ON UPDATE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE payments (id INT AUTO_INCREMENT PRIMARY KEY, customer_id INT NOT NULL, amount DECIMAL(10,2) NOT NULL, paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, note VARCHAR(255) DEFAULT NULL, FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE ON UPDATE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE VIEW v_customer_totals AS
SELECT c.id AS customer_id, COALESCE(SUM(s.subtotal),0) AS total_purchased, COALESCE(SUM(s.paid),0) AS total_paid_at_sale_time, COALESCE(SUM(CASE WHEN s.is_credit=1 THEN (s.subtotal - s.paid) ELSE 0 END),0) AS credit_from_sales
FROM customers c
LEFT JOIN sales s ON s.customer_id = c.id
GROUP BY c.id;

CREATE VIEW v_customer_balance AS
SELECT c.id AS customer_id, c.name, COALESCE(v.total_purchased,0) AS total_purchased, COALESCE(v.total_paid_at_sale_time,0) + COALESCE(pay.total_payments,0) AS total_paid, (COALESCE(v.total_purchased,0) - (COALESCE(v.total_paid_at_sale_time,0) + COALESCE(pay.total_payments,0))) AS balance
FROM customers c
LEFT JOIN v_customer_totals v ON v.customer_id = c.id
LEFT JOIN (SELECT customer_id, COALESCE(SUM(amount),0) AS total_payments FROM payments GROUP BY customer_id) pay ON pay.customer_id = c.id;
