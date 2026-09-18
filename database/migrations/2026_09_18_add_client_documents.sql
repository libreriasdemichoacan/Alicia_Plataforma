CREATE TABLE client_documents (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    third_party_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    document_type VARCHAR(80) NOT NULL,
    custom_label VARCHAR(180) NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(80) NOT NULL UNIQUE,
    mime_type VARCHAR(100) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX client_documents_third_party_idx (third_party_id),
    INDEX client_documents_type_idx (document_type),
    INDEX client_documents_created_idx (created_at),
    CONSTRAINT client_documents_third_party_fk FOREIGN KEY (third_party_id) REFERENCES third_parties(id) ON DELETE CASCADE,
    CONSTRAINT client_documents_user_fk FOREIGN KEY (uploaded_by) REFERENCES third_party_users(id) ON DELETE CASCADE
);
