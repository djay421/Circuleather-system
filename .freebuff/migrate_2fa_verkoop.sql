-- Incrementele migratie (draaiende database): 2FA + verkoopregistraties.
-- Uitvoeren: docker exec -i circuleather_db mysql -uroot -proot_password circuleather_crm < .freebuff/migrate_2fa_verkoop.sql

ALTER TABLE gebruikers ADD COLUMN totp_secret VARCHAR(64) NULL AFTER actief;

CREATE TABLE IF NOT EXISTS recovery_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gebruiker_id INT NOT NULL,
    code_hash VARCHAR(64) NOT NULL,
    gebruikt TINYINT(1) NOT NULL DEFAULT 0,
    KEY idx_recovery_gebruiker (gebruiker_id)
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS verkopen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voorraad_id INT NOT NULL,
    gebruiker_id INT NULL,
    opmerking VARCHAR(255) NULL,
    verkocht_op DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_verkopen_voorraad (voorraad_id),
    KEY idx_verkopen_datum (verkocht_op)
) DEFAULT CHARSET=utf8mb4;
