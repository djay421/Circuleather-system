# Circuleather-system

## Starten

Vereisten: Docker Desktop met Docker Compose.

Start de applicatie vanuit deze map:

```sh
docker compose up --build
```

Open daarna:

- Applicatie: http://localhost:8080
- phpMyAdmin: http://localhost:8081

De database wordt aangemaakt met `init.sql` wanneer de MySQL-volume voor het eerst wordt aangemaakt. Om de database volledig opnieuw te initialiseren:

```sh
docker compose down -v
docker compose up --build
```