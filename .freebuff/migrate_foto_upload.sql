-- Migratie: productfoto's voor leersamples (galerij).
ALTER TABLE voorraad ADD COLUMN foto VARCHAR(255) NULL COMMENT 'geüploade productfoto (relatief pad, uploads/...)' AFTER opmerking;
