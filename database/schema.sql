CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    level INT NOT NULL DEFAULT 10,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module VARCHAR(60) NOT NULL,
    action VARCHAR(30) NOT NULL,
    UNIQUE KEY permissions_module_action_unique (module, action)
);

CREATE TABLE role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    name VARCHAR(140) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

CREATE TABLE report_branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(140) NOT NULL,
    db_host VARCHAR(180) NOT NULL,
    db_port VARCHAR(10) NOT NULL DEFAULT '3306',
    db_name VARCHAR(140) NOT NULL,
    db_user VARCHAR(140) NOT NULL,
    db_pass VARCHAR(255) NOT NULL,
    db_charset VARCHAR(30) NOT NULL DEFAULT 'utf8mb4',
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE third_parties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NULL,
    type ENUM('client','provider') NOT NULL,
    internal_number VARCHAR(60),
    legal_name VARCHAR(180) NOT NULL,
    tax_id VARCHAR(50),
    email VARCHAR(180),
    phone VARCHAR(40),
    logo_path VARCHAR(255),
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES report_branches(id)
);


CREATE TABLE third_party_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    third_party_id INT NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (third_party_id) REFERENCES third_parties(id) ON DELETE CASCADE
);

CREATE TABLE app_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE account_statements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    third_party_id INT NOT NULL,
    statement_date DATE NOT NULL,
    concept VARCHAR(220) NOT NULL,
    debit DECIMAL(14,2) NOT NULL DEFAULT 0,
    credit DECIMAL(14,2) NOT NULL DEFAULT 0,
    balance DECIMAL(14,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (third_party_id) REFERENCES third_parties(id) ON DELETE CASCADE
);

INSERT INTO roles (name, level) VALUES ('Administrador', 100), ('Contabilidad', 50), ('Consulta', 10);
INSERT INTO permissions (module, action) VALUES
('branches','view'),('branches','create'),('branches','update'),('clients','view'),('clients','create'),('clients','update'),('providers','view'),('providers','create'),('providers','update'),('users','view'),('users','create'),('settings','view'),('settings','update'),('statements','view'),('statements','create');
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.name IN ('Administrador', 'Contabilidad');
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.action = 'view' WHERE r.name = 'Consulta';

-- Cambia este hash después de entrar. Usuario inicial: admin@example.com / Admin1234!
INSERT INTO users (role_id, name, email, password_hash, status)
SELECT id, 'Administrador', 'admin@example.com', '$2y$12$MHeVB1R8HDCS4kCPiOWudO9p7vnI5W3Shy57rZDHvU7FAgGqoKN6G', 'active'
FROM roles WHERE name = 'Administrador';

INSERT INTO app_settings (setting_key, setting_value) VALUES ('app_logo_path', '');
