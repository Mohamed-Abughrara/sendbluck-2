CREATE TABLE audit_log (
  id {{BIGPK}},
  user_id {{INT}} NULL,
  action VARCHAR(50) NOT NULL,
  context {{LONGTEXT}} NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL
){{ENGINE}};
CREATE INDEX idx_audit_action_created ON audit_log (action, created_at);
