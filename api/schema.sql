CREATE TABLE IF NOT EXISTS ntf_form_submissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  form_type VARCHAR(32) NOT NULL,
  full_name VARCHAR(160) NULL,
  email VARCHAR(220) NOT NULL,
  organization VARCHAR(220) NULL,
  interest VARCHAR(160) NULL,
  topic VARCHAR(220) NULL,
  title_role VARCHAR(220) NULL,
  professional_bio TEXT NULL,
  message TEXT NULL,
  payload JSON NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ntf_form_type_created (form_type, created_at),
  KEY idx_ntf_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
