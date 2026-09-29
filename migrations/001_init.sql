CREATE TABLE IF NOT EXISTS hfc_devices (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, uuid VARCHAR(64) NOT NULL UNIQUE, name VARCHAR(120) NOT NULL,
 host VARCHAR(255) NOT NULL, port INT UNSIGNED NOT NULL DEFAULT 80, protocol ENUM('http','https') NOT NULL DEFAULT 'http',
 username VARCHAR(120) NOT NULL, password_enc TEXT NOT NULL, model VARCHAR(120) NULL, serial_no VARCHAR(120) NULL,
 firmware VARCHAR(120) NULL, role ENUM('enrollment','destination','both') NOT NULL DEFAULT 'both',
 face_lib_id VARCHAR(64) NOT NULL DEFAULT '1', face_lib_type VARCHAR(32) NOT NULL DEFAULT 'blackFD',
 active TINYINT(1) NOT NULL DEFAULT 1, capabilities_json LONGTEXT NULL, last_seen_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_hfc_devices_host(host), INDEX idx_hfc_devices_active(active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hfc_persons (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, person_code VARCHAR(64) NOT NULL UNIQUE, name VARCHAR(160) NOT NULL,
 valid_from DATETIME NOT NULL, valid_to DATETIME NOT NULL, status ENUM('active','disabled','expired') NOT NULL DEFAULT 'active',
 metadata_json LONGTEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_hfc_persons_name(name), INDEX idx_hfc_persons_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hfc_cards (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, person_id BIGINT UNSIGNED NOT NULL, card_no VARCHAR(128) NOT NULL UNIQUE,
 card_type VARCHAR(64) NULL, source_device_id BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_hfc_cards_person FOREIGN KEY(person_id) REFERENCES hfc_persons(id) ON DELETE CASCADE,
 CONSTRAINT fk_hfc_cards_source_device FOREIGN KEY(source_device_id) REFERENCES hfc_devices(id) ON DELETE SET NULL,
 INDEX idx_hfc_cards_person(person_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hfc_faces (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, person_id BIGINT UNSIGNED NOT NULL UNIQUE, image_path VARCHAR(500) NOT NULL,
 mime_type VARCHAR(100) NOT NULL DEFAULT 'image/jpeg', sha256 CHAR(64) NOT NULL, capture_device_id BIGINT UNSIGNED NULL,
 pull_token CHAR(64) NOT NULL UNIQUE, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_hfc_faces_person FOREIGN KEY(person_id) REFERENCES hfc_persons(id) ON DELETE CASCADE,
 CONSTRAINT fk_hfc_faces_capture_device FOREIGN KEY(capture_device_id) REFERENCES hfc_devices(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hfc_sync_jobs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, person_id BIGINT UNSIGNED NOT NULL, device_id BIGINT UNSIGNED NOT NULL,
 action ENUM('upsert','delete') NOT NULL DEFAULT 'upsert', status ENUM('queued','running','success','error') NOT NULL DEFAULT 'queued',
 attempts INT UNSIGNED NOT NULL DEFAULT 0, last_error TEXT NULL, result_json LONGTEXT NULL, next_attempt_at DATETIME NULL,
 started_at DATETIME NULL, finished_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_hfc_sync_jobs_person FOREIGN KEY(person_id) REFERENCES hfc_persons(id) ON DELETE CASCADE,
 CONSTRAINT fk_hfc_sync_jobs_device FOREIGN KEY(device_id) REFERENCES hfc_devices(id) ON DELETE CASCADE,
 INDEX idx_hfc_sync_jobs_queue(status,next_attempt_at), INDEX idx_hfc_sync_jobs_person_device(person_id,device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hfc_sync_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, sync_job_id BIGINT UNSIGNED NOT NULL,
 level ENUM('info','success','warning','error') NOT NULL, message TEXT NOT NULL, context_json LONGTEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_hfc_sync_logs_job FOREIGN KEY(sync_job_id) REFERENCES hfc_sync_jobs(id) ON DELETE CASCADE,
 INDEX idx_hfc_sync_logs_job(sync_job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hfc_audit_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, actor VARCHAR(160) NULL, action VARCHAR(160) NOT NULL,
 entity_type VARCHAR(80) NULL, entity_id VARCHAR(80) NULL, ip_address VARCHAR(64) NULL, context_json LONGTEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_hfc_audit_action(action), INDEX idx_hfc_audit_entity(entity_type,entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
