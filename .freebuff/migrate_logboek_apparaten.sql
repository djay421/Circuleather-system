-- Migratie: logboek + apparaten (onthouden 2FA-apparaten)
CREATE TABLE IF NOT EXISTS logboek (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gebruiker_id INT NULL,
    actie VARCHAR(60) NOT NULL,
    beschrijving VARCHAR(500) NOT NULL DEFAULT '',
    ip VARCHAR(45) NOT NULL DEFAULT '',
    apparaat VARCHAR(120) NOT NULL DEFAULT '',
    aangemaakt_op DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_logboek_datum (aangemaakt_op),
    KEY idx_logboek_gebruiker (gebruiker_id),
    KEY idx_logboek_actie (actie)
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS apparaten (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gebruiker_id INT NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    label VARCHAR(120) NOT NULL DEFAULT '',
    ip VARCHAR(45) NOT NULL DEFAULT '',
    laatst_gebruikt DATETIME NULL,
    aangemaakt_op DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_apparaten_token (token_hash),
    KEY idx_apparaten_gebruiker (gebruiker_id)
) DEFAULT CHARSET=utf8mb4;