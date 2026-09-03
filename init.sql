-- ============================================================
-- Circuleather leeropslag — databaseschema
-- Gebaseerd op: "tabel selectiecriteria leeropslag Circuleather"
-- en de user cases in "User cases Leeropslag - Circuleather".
--
-- Uitgangspunten:
--  * Voorraad bestaat uit bigbags en leersamples (categorie).
--  * Classificatie-criteria (geur, schade, formaat, kleur, PANTONE,
--    inhoud bigbag, gewicht, dikte ...) zijn NIET vast in de code,
--    maar beheersbaar in de backend: rijen in `criteria` en
--    `criteria_opties`. Zo kunnen variabelen later worden toegevoegd,
--    aangepast of verwijderd zonder de applicatie te herbouwen.
--  * Elk criterium heeft een "meerdere ja/nee" vlag uit het voorbeeld
--    en is gekoppeld aan bigbag en/of leersample.
-- ============================================================

-- Bronsteden van bigbags (uit de user story: Gouda, Breda, Almere,
-- uitbreidbaar/aanpasbaar in de backend).
CREATE TABLE IF NOT EXISTS steden (
    id INT AUTO_INCREMENT PRIMARY KEY,
    naam VARCHAR(100) NOT NULL UNIQUE,
    actief TINYINT(1) NOT NULL DEFAULT 1
) DEFAULT CHARSET=utf8mb4;

-- Selectiecriteria. `toepassing` bepaalt of het criterium geldt voor
-- bigbags of leersamples. `soort`: keuze (lijst), getal (met eenheid)
-- of tekst (vrij in te vullen). `meerdere_waarden` = "multiple ja/nee"
-- uit de voorbeeldtabel.
CREATE TABLE IF NOT EXISTS criteria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(150) NOT NULL,
    toepassing ENUM('bigbag', 'leersample') NOT NULL,
    soort ENUM('keuze', 'getal', 'tekst') NOT NULL DEFAULT 'keuze',
    eenheid VARCHAR(20) NULL,
    meerdere_waarden TINYINT(1) NOT NULL DEFAULT 0,
    actief TINYINT(1) NOT NULL DEFAULT 1,
    volgorde INT NOT NULL DEFAULT 0
) DEFAULT CHARSET=utf8mb4;

-- Keuzemogelijkheden per criterium (voorbeeldregels "criteria keuzes").
CREATE TABLE IF NOT EXISTS criteria_opties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    criterium_id INT NOT NULL,
    waarde VARCHAR(150) NOT NULL,
    actief TINYINT(1) NOT NULL DEFAULT 1,
    volgorde INT NOT NULL DEFAULT 0,
    FOREIGN KEY (criterium_id) REFERENCES criteria(id) ON DELETE CASCADE
) DEFAULT CHARSET=utf8mb4;

-- De fysieke voorraad: één rij per bigbag of leersample.
-- Alleen bigbags hebben een QR-code: `code` is dan verplicht en uniek.
-- Leersamples hebben geen QR; ze worden met de hand geregistreerd en kunnen
-- (optioneel) aan de bigbag gekoppeld worden waar ze uit komen (`bigbag_id`),
-- zodat herkomststad en datum herleidbaar blijven.
CREATE TABLE IF NOT EXISTS voorraad (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NULL UNIQUE COMMENT 'QR/streepjescode (bigbag) of optioneel partij-nummer',
    categorie ENUM('bigbag', 'leersample') NOT NULL,
    stad_id INT NULL COMMENT 'herkomststad van een bigbag',
    bigbag_id INT NULL COMMENT 'leersample: bigbag waar dit sample uit komt',
    locatie VARCHAR(100) NULL,
    status ENUM('beschikbaar', 'gereserveerd', 'in_bewerking', 'verkocht') NOT NULL DEFAULT 'beschikbaar',
    binnenkomst_datum DATE NULL,
    opmerking TEXT NULL,
    foto VARCHAR(255) NULL COMMENT 'geüploade productfoto (relatief pad, uploads/...)',
    aangemaakt_op DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stad_id) REFERENCES steden(id) ON DELETE SET NULL,
    FOREIGN KEY (bigbag_id) REFERENCES voorraad(id) ON DELETE SET NULL
) DEFAULT CHARSET=utf8mb4;

-- Gekozen waarden per voorraad-item: één rij per gekozen optie
-- (bij "meerdere ja" dus meerdere rijen) of één rij met een vrij
-- getal/tekst (gewicht, dikte, PANTONE-nummer ...).
CREATE TABLE IF NOT EXISTS voorraad_criteria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voorraad_id INT NOT NULL,
    criterium_id INT NOT NULL,
    optie_id INT NULL,
    waarde_vrij VARCHAR(255) NULL COMMENT 'vrije waarde bij soort=getal of soort=tekst',
    FOREIGN KEY (voorraad_id) REFERENCES voorraad(id) ON DELETE CASCADE,
    FOREIGN KEY (criterium_id) REFERENCES criteria(id) ON DELETE CASCADE,
    FOREIGN KEY (optie_id) REFERENCES criteria_opties(id) ON DELETE CASCADE,
    UNIQUE KEY uniek_keuze (voorraad_id, criterium_id, optie_id)
) DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Voorbeeldgegevens: steden + selectiecriteria zoals aangeleverd
-- in "tabel selectiecriteria leeropslag Circuleather.xlsx".
-- ============================================================

INSERT INTO steden (naam) VALUES ('Gouda'), ('Breda'), ('Almere');

INSERT INTO criteria (label, toepassing, soort, eenheid, meerdere_waarden, volgorde) VALUES
    ('Inhoud bigbag', 'bigbag', 'keuze', NULL, 0, 10),
    ('Gewicht', 'bigbag', 'getal', 'kg', 0, 20),

    ('Geur', 'leersample', 'keuze', NULL, 0, 30),
    ('Schade', 'leersample', 'keuze', NULL, 1, 40),
    ('Formaat', 'leersample', 'keuze', NULL, 0, 50),
    ('Gewicht', 'leersample', 'getal', 'gram', 0, 60),
    ('Dikte', 'leersample', 'getal', 'mm', 0, 70),
    ('Soepelheid', 'leersample', 'keuze', NULL, 1, 80),
    ('Optisch', 'leersample', 'keuze', NULL, 1, 90),
    ('Kleurcategorie', 'leersample', 'keuze', NULL, 0, 100),
    ('Kleur detail', 'leersample', 'keuze', NULL, 1, 110),
    ('PANTONE code', 'leersample', 'keuze', NULL, 0, 120),
    ('Pantone TCX', 'leersample', 'tekst', NULL, 0, 130),
    ('Pantone TPG', 'leersample', 'tekst', NULL, 0, 140),
    ('Pantone kleurnaam', 'leersample', 'tekst', NULL, 0, 150);

INSERT INTO criteria_opties (criterium_id, waarde, volgorde) VALUES
    ((SELECT id FROM criteria WHERE label = 'Inhoud bigbag' AND toepassing = 'bigbag'), 'leersample', 10),
    ((SELECT id FROM criteria WHERE label = 'Inhoud bigbag' AND toepassing = 'bigbag'), 'restleer', 20),

    ((SELECT id FROM criteria WHERE label = 'Geur' AND toepassing = 'leersample'), 'sterke geur', 10),
    ((SELECT id FROM criteria WHERE label = 'Geur' AND toepassing = 'leersample'), 'neutraal', 20),

    ((SELECT id FROM criteria WHERE label = 'Schade' AND toepassing = 'leersample'), 'HIGH', 10),
    ((SELECT id FROM criteria WHERE label = 'Schade' AND toepassing = 'leersample'), 'MEDIUM', 20),
    ((SELECT id FROM criteria WHERE label = 'Schade' AND toepassing = 'leersample'), 'LOW', 30),

    ((SELECT id FROM criteria WHERE label = 'Formaat' AND toepassing = 'leersample'), 'XXL (XX-XX CM)', 10),
    ((SELECT id FROM criteria WHERE label = 'Formaat' AND toepassing = 'leersample'), 'XL', 20),
    ((SELECT id FROM criteria WHERE label = 'Formaat' AND toepassing = 'leersample'), 'L', 30),
    ((SELECT id FROM criteria WHERE label = 'Formaat' AND toepassing = 'leersample'), 'M', 40),
    ((SELECT id FROM criteria WHERE label = 'Formaat' AND toepassing = 'leersample'), 'S', 50),
    ((SELECT id FROM criteria WHERE label = 'Formaat' AND toepassing = 'leersample'), 'XS', 60),
    ((SELECT id FROM criteria WHERE label = 'Formaat' AND toepassing = 'leersample'), 'XXS', 70),

    ((SELECT id FROM criteria WHERE label = 'Soepelheid' AND toepassing = 'leersample'), 'HIGH', 10),
    ((SELECT id FROM criteria WHERE label = 'Soepelheid' AND toepassing = 'leersample'), 'MEDIUM', 20),
    ((SELECT id FROM criteria WHERE label = 'Soepelheid' AND toepassing = 'leersample'), 'LOW', 30),

    ((SELECT id FROM criteria WHERE label = 'Optisch' AND toepassing = 'leersample'), 'uitstekend', 10),
    ((SELECT id FROM criteria WHERE label = 'Optisch' AND toepassing = 'leersample'), 'goed', 20),
    ((SELECT id FROM criteria WHERE label = 'Optisch' AND toepassing = 'leersample'), 'gemiddeld', 30),
    ((SELECT id FROM criteria WHERE label = 'Optisch' AND toepassing = 'leersample'), 'laag', 40),
    ((SELECT id FROM criteria WHERE label = 'Optisch' AND toepassing = 'leersample'), 'slecht', 50),

    ((SELECT id FROM criteria WHERE label = 'Kleurcategorie' AND toepassing = 'leersample'), 'Zwart', 10),
    ((SELECT id FROM criteria WHERE label = 'Kleurcategorie' AND toepassing = 'leersample'), 'Wit', 20),
    ((SELECT id FROM criteria WHERE label = 'Kleurcategorie' AND toepassing = 'leersample'), 'bruin', 30),
    ((SELECT id FROM criteria WHERE label = 'Kleurcategorie' AND toepassing = 'leersample'), 'rood', 40),
    ((SELECT id FROM criteria WHERE label = 'Kleurcategorie' AND toepassing = 'leersample'), 'groen', 50),
    ((SELECT id FROM criteria WHERE label = 'Kleurcategorie' AND toepassing = 'leersample'), 'geel', 60),
    ((SELECT id FROM criteria WHERE label = 'Kleurcategorie' AND toepassing = 'leersample'), 'blauw', 70),
    ((SELECT id FROM criteria WHERE label = 'Kleurcategorie' AND toepassing = 'leersample'), 'roze', 80),
    ((SELECT id FROM criteria WHERE label = 'Kleurcategorie' AND toepassing = 'leersample'), 'paars', 90),
    ((SELECT id FROM criteria WHERE label = 'Kleurcategorie' AND toepassing = 'leersample'), 'overige', 100),

    ((SELECT id FROM criteria WHERE label = 'Kleur detail' AND toepassing = 'leersample'), 'Antraciet', 10),
    ((SELECT id FROM criteria WHERE label = 'Kleur detail' AND toepassing = 'leersample'), 'beige', 20),
    ((SELECT id FROM criteria WHERE label = 'Kleur detail' AND toepassing = 'leersample'), 'cognac', 30),
    ((SELECT id FROM criteria WHERE label = 'Kleur detail' AND toepassing = 'leersample'), 'kobalt', 40),
    ((SELECT id FROM criteria WHERE label = 'Kleur detail' AND toepassing = 'leersample'), 'grasgroen', 50),
    ((SELECT id FROM criteria WHERE label = 'Kleur detail' AND toepassing = 'leersample'), 'overige', 60),

    ((SELECT id FROM criteria WHERE label = 'PANTONE code' AND toepassing = 'leersample'), '11-0103 TPG', 10),
    ((SELECT id FROM criteria WHERE label = 'PANTONE code' AND toepassing = 'leersample'), '11-0601 TPG', 20),
    ((SELECT id FROM criteria WHERE label = 'PANTONE code' AND toepassing = 'leersample'), '12-0000 TPG', 30),
    ((SELECT id FROM criteria WHERE label = 'PANTONE code' AND toepassing = 'leersample'), '12-0104 TPG', 40),
    ((SELECT id FROM criteria WHERE label = 'PANTONE code' AND toepassing = 'leersample'), '13-0000 TPG', 50),
    ((SELECT id FROM criteria WHERE label = 'PANTONE code' AND toepassing = 'leersample'), '14-0105 TPG', 60),
    ((SELECT id FROM criteria WHERE label = 'PANTONE code' AND toepassing = 'leersample'), '16-0005 TPG', 70),
    ((SELECT id FROM criteria WHERE label = 'PANTONE code' AND toepassing = 'leersample'), '18-4007 TPG', 80),
    ((SELECT id FROM criteria WHERE label = 'PANTONE code' AND toepassing = 'leersample'), '19-0508 TPG', 90);

-- ============================================================
-- Gebruikers / accounts (login). Rollen: admin (beheer) en
-- medewerker (voorraad bijhouden/scannen). Standaardaccounts:
--   admin@circuleather.nl      / admin123        (beheerder)
--   medewerker@circuleather.nl / medewerker123   (medewerker)
-- Verander deze wachtwoorden na de eerste keer inloggen.
-- ============================================================

CREATE TABLE IF NOT EXISTS gebruikers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    naam VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    wachtwoord_hash VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'medewerker') NOT NULL DEFAULT 'medewerker',
    actief TINYINT(1) NOT NULL DEFAULT 1,
    totp_secret VARCHAR(64) NULL,
    aangemaakt_op DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) DEFAULT CHARSET=utf8mb4;

INSERT INTO gebruikers (naam, email, wachtwoord_hash, rol) VALUES
    ('Beheerder', 'admin@circuleather.nl', '$2y$10$hynKEMIYq/cNjq2IQQVY.uIZi/SIro/PiXJtpwe6qwK/QsV1fpbsm', 'admin'),
    ('Medewerker', 'medewerker@circuleather.nl', '$2y$10$fIomwbu3EBCJAFfhRuYkc.cBCwoaEXVGzjsDz.o0byEvglCZ4EfXe', 'medewerker');

-- ============================================================
-- QR-labels: welke bigbagcodes al als (voorbedrukt) label zijn
-- gegenereerd, zodat nummers nooit twee keer worden gebruikt.
-- Een code komt pas in `voorraad` terecht als de zak bij inname
-- is gescand en geregistreerd.
-- ============================================================

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

-- ============================================================
-- Herstelcodes voor 2FA (éénmalig, gehasht opgeslagen) en
-- verkoopregistraties (wie verkocht welke sample wanneer).
-- ============================================================

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

