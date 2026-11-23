CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  username VARCHAR(80) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','seller') NOT NULL,
  active BOOLEAN NOT NULL DEFAULT 1,
  session_version INT UNSIGNED NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS salaries (
  user_id INT NOT NULL REFERENCES users(id),
  month CHAR(7) NOT NULL,
  amount BIGINT NOT NULL,
  PRIMARY KEY (user_id, month)
);
CREATE TABLE IF NOT EXISTS materials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  thickness DECIMAL(8,3) UNIQUE,
  stock DECIMAL(20,6) NOT NULL DEFAULT 0,
  rate DECIMAL(22,10) NULL
);
INSERT IGNORE INTO materials (name) VALUES ('Pegamento');
CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  size VARCHAR(100) NOT NULL,
  category VARCHAR(100) NOT NULL,
  mdf_id INT NOT NULL REFERENCES materials(id),
  area DECIMAL(20,6) NOT NULL,
  assembly BOOLEAN NOT NULL,
  glue DECIMAL(20,6) NOT NULL,
  labor BIGINT NOT NULL,
  retail BIGINT NOT NULL,
  wholesale BIGINT NULL,
  filename VARCHAR(255) NOT NULL,
  plot MEDIUMBLOB NOT NULL,
  selection TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(40) NOT NULL DEFAULT '',
  email VARCHAR(160) NOT NULL DEFAULT '',
  address VARCHAR(250) NOT NULL DEFAULT ''
);
CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  seller_id INT NOT NULL REFERENCES users(id),
  customer VARCHAR(120) NOT NULL,
  customer_id INT NULL REFERENCES customers(id),
  total BIGINT NOT NULL,
  status ENUM('review','pending','delivered') NOT NULL DEFAULT 'review',
  stocked BOOLEAN NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL
);
CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL REFERENCES orders(id),
  product_id INT NOT NULL REFERENCES products(id),
  name VARCHAR(120) NOT NULL,
  quantity INT NOT NULL,
  channel ENUM('retail','wholesale') NOT NULL,
  price BIGINT NOT NULL,
  unit_cost BIGINT NOT NULL,
  mdf_id INT NOT NULL REFERENCES materials(id),
  area DECIMAL(20,6) NOT NULL,
  glue DECIMAL(20,6) NOT NULL
);
CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL REFERENCES orders(id),
  stage ENUM('deposit','balance') NOT NULL,
  amount BIGINT NOT NULL,
  status ENUM('review','approved','rejected') NOT NULL DEFAULT 'review',
  mime VARCHAR(50) NOT NULL,
  proof MEDIUMBLOB NOT NULL,
  reason VARCHAR(250) NULL,
  submitted_at DATETIME NOT NULL,
  reviewed_at DATETIME NULL,
  reviewed_by INT NULL REFERENCES users(id),
  INDEX (order_id, stage, id),
  INDEX (status, reviewed_at)
);
CREATE TABLE IF NOT EXISTS movements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  material_id INT NOT NULL REFERENCES materials(id),
  actor_id INT NOT NULL REFERENCES users(id),
  order_id INT NULL REFERENCES orders(id),
  kind ENUM('purchase','adjustment','consumption') NOT NULL,
  quantity DECIMAL(20,6) NOT NULL,
  cost BIGINT NULL,
  plate_width DECIMAL(12,3) NULL,
  plate_height DECIMAL(12,3) NULL,
  plates INT NULL,
  reason VARCHAR(250) NULL,
  created_at DATETIME NOT NULL,
  UNIQUE (order_id, material_id)
);
CREATE TABLE IF NOT EXISTS order_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL REFERENCES orders(id),
  actor_id INT NOT NULL REFERENCES users(id),
  description VARCHAR(250) NOT NULL,
  created_at DATETIME NOT NULL
);
