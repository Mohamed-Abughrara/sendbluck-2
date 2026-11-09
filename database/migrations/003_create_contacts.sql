CREATE TABLE contacts (
  id {{PK}},
  name VARCHAR(100) NOT NULL,
  email VARCHAR(254) NOT NULL,
  created_by {{INT}} NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  CONSTRAINT fk_contacts_user FOREIGN KEY (created_by) REFERENCES users(id)
){{ENGINE}};
CREATE INDEX idx_contacts_name ON contacts (name);
--sqlite:CREATE UNIQUE INDEX uq_contacts_email_active ON contacts (email) WHERE deleted_at IS NULL;
--mysql:ALTER TABLE contacts ADD COLUMN email_active VARCHAR(254) AS (IF(deleted_at IS NULL, email, NULL)) STORED;
--mysql:CREATE UNIQUE INDEX uq_contacts_email_active ON contacts (email_active);
