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
