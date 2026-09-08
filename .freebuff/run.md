# Run doc — Circuleather leeropslag (Docker)

## How to reproduce the artifacts

1. Start Docker Desktop if it is not running (`com.docker.backend` must be up).
   On this machine it usually auto-restores the stack on boot.
2. The stack is defined in `docker-compose.yml` (web + mysql:8 + phpMyAdmin).
   If the containers are missing or stale, start them:

   ```sh
   docker compose up -d --build
   ```

3. No `.env` or copied secrets are needed: `config/db.php` reads
   `DB_HOST/DB_NAME/DB_USER/DB_PASSWORD` from docker-compose environment
   defaults. `src/config/db.local.php` (InfinityFree override) must NOT exist
   locally — it would override the Docker defaults.
4. Database: `database/init.sql` is mounted at
   `/docker-entrypoint-initdb.d/init.sql` and runs only on first creation of
   the `db_data` volume. Seeded logins (see README):
   `admin@circuleather.nl / admin123` (admin), `medewerker@circuleather.nl /
   medewerker123` (medewerker). Both seeded accounts have no TOTP secret, so
   they log in without 2FA (admins get routed to 2FA setup).
5. `src/` is volume-mounted to `/var/www/html`, so PHP edits are live without
   a rebuild.

## How to run the server

- The web container publishes host port **8080** (`${WEB_PORT:-8080}:80` —
  set `WEB_PORT` in a `.env` if 8080 is taken; note Steam also binds
  127.0.0.1:8080 on this machine, but 0.0.0.0:8080 by Docker still works).
- phpMyAdmin is on **8081**, MySQL on 3306.
- App URL: **http://localhost:8080** (root redirects to `login.php`).
- Container name: `circuleather_web` (PHP 8.3-apache). Quick health check:
  `curl -s -o /dev/null -w '%{http_code}' http://localhost:8080/login.php`
  → expect 200.
