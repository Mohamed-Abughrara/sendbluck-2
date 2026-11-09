CREATE TABLE messages (
  id {{BIGPK}},
  user_id {{INT}} NOT NULL,
  recipients {{LONGTEXT}} NOT NULL,
  recipient_count INTEGER NOT NULL,
  subject VARCHAR(200) NOT NULL,
  body {{LONGTEXT}} NOT NULL,
  status VARCHAR(10) NOT NULL CHECK (status IN ('sent','failed')),
  error VARCHAR(500) NULL,
  retried_at DATETIME NULL,
  sent_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_messages_user FOREIGN KEY (user_id) REFERENCES users(id)
){{ENGINE}};
CREATE INDEX idx_messages_status_sent_at ON messages (status, sent_at);
CREATE INDEX idx_messages_user ON messages (user_id);
