-- Voorraad tabel voor Circuleather (leer-voorraadbeheer)

CREATE TABLE IF NOT EXISTS voorraad (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partij_code VARCHAR(50) NOT NULL,
    locatie VARCHAR(100),
    gewicht_kg DECIMAL(8,2) DEFAULT 0,
    kleur VARCHAR(50),
    breedte_cm DECIMAL(8,2) DEFAULT 0,
    lengte_cm DECIMAL(8,2) DEFAULT 0,
    status ENUM('beschikbaar', 'gereserveerd', 'in_bewerking', 'verkocht') DEFAULT 'beschikbaar',
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP
);
