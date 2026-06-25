ALTER TABLE third_parties
    ADD COLUMN internal_number VARCHAR(60) NULL AFTER type,
    ADD COLUMN logo_path VARCHAR(255) NULL AFTER phone;

INSERT INTO permissions (module, action) VALUES
('clients','update'),
('providers','update');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name IN ('Administrador', 'Contabilidad')
  AND p.module IN ('clients', 'providers')
  AND p.action = 'update';
