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

ALTER TABLE third_parties
    ADD COLUMN branch_id INT NULL AFTER id,
    ADD CONSTRAINT third_parties_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES report_branches(id);

INSERT INTO permissions (module, action) VALUES
('branches','view'),('branches','create'),('branches','update');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name IN ('Administrador', 'Contabilidad')
  AND p.module = 'branches';
