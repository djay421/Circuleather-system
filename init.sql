-- Basis schema voor Circuleather CRM

CREATE TABLE IF NOT EXISTS klanten (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bedrijfsnaam VARCHAR(150) NOT NULL,
    email VARCHAR(150),
    telefoon VARCHAR(50),
    adres VARCHAR(255),
    status ENUM('lead', 'actief', 'inactief') DEFAULT 'lead',
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS contactpersonen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    klant_id INT NOT NULL,
    naam VARCHAR(150) NOT NULL,
    functie VARCHAR(100),
    email VARCHAR(150),
    telefoon VARCHAR(50),
    FOREIGN KEY (klant_id) REFERENCES klanten(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS deals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    klant_id INT NOT NULL,
    titel VARCHAR(150) NOT NULL,
    waarde DECIMAL(10,2) DEFAULT 0,
    fase ENUM('nieuw', 'in_overleg', 'offerte', 'gewonnen', 'verloren') DEFAULT 'nieuw',
    verwachte_afsluitdatum DATE,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (klant_id) REFERENCES klanten(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS activiteiten (
    id INT AUTO_INCREMENT PRIMARY KEY,
    klant_id INT NOT NULL,
    type ENUM('call', 'email', 'meeting', 'notitie') DEFAULT 'notitie',
    omschrijving TEXT,
    datum DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (klant_id) REFERENCES klanten(id) ON DELETE CASCADE
);

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
