CREATE TABLE mail_configuration (
  id {{PK}},
  sender_name VARCHAR(100) NOT NULL,
  sender_email VARCHAR(254) NOT NULL,
  smtp_host VARCHAR(255) NOT NULL,
  smtp_port INTEGER NOT NULL CHECK (smtp_port BETWEEN 1 AND 65535),
  encryption VARCHAR(4) NOT NULL DEFAULT 'tls' CHECK (encryption IN ('tls','ssl','none')),
  smtp_username VARCHAR(254) NOT NULL,
  smtp_password_enc TEXT NOT NULL,
  is_verified TINYINT NOT NULL DEFAULT 0,
  last_tested_at DATETIME NULL,
  updated_by {{INT}} NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_config_user FOREIGN KEY (updated_by) REFERENCES users(id)
){{ENGINE}};
