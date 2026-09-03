-- Incrementele migratie (draaiende database): QR-labels bijhouden.
-- Uitvoeren: docker exec -i circuleather_db mysql -uroot -proot_password circuleather_crm < .freebuff/migrate_qr_labels.sql

CREATE TABLE IF NOT EXISTS qr_labels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    gebruiker_id INT NULL,
    aangemaakt_op DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) DEFAULT CHARSET=utf8mb4;

-- Codes BB-2026-004..006 zijn al als testlabel gedrukt (map test-qr/):
-- de generator gaat daarna verder vanaf BB-2026-007.
INSERT INTO qr_labels (code) VALUES
    ('BB-2026-004'),
    ('BB-2026-005'),
    ('BB-2026-006');
