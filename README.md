# Circuleather leeropslag

Registreer welke bigbags en leersamples leer in opslag zijn, en classificeer ze
met flexibele selectiecriteria (geur, schade, formaat, kleur, PANTONE, ...).
Gebaseerd op "tabel selectiecriteria leeropslag Circuleather" en
"User cases Leeropslag - Circuleather". De vormgeving volgt de merkstijl
van circuleather.com (Poppins, zwart, koraalrood #f46152, “RE-❤ LEATHER”).

Naast opslagregistratie bevat de app: een beveiligde login met
tweestapsverificatie (Google Authenticator; verplicht voor beheerders),
een verkoopgalerij voor leersamples (met eigen foto's per stuk), tabs en
filters op de voorraadpagina, camera-scannen van bigbag-QR-codes
en een generator voor voorbedrukte zaklabels (alleen beheerder).

## Starten

Vereisten: Docker Desktop met Docker Compose.

```sh
docker compose up --build
```

Open daarna:

- Applicatie: http://localhost:8080
- phpMyAdmin: http://localhost:8081

## Database opnieuw initialiseren

De database wordt aangemaakt met `init.sql` wanneer de MySQL-volume voor het
eerst wordt aangemaakt. Na een wijziging van `init.sql` moet de volume opnieuw
worden aangemaakt:

```sh
docker compose down -v
docker compose up --build
```

> Let op: `down -v` verwijdert de database en alle ingevoerde voorraad.

## Databasemodel

| Tabel | Doel |
| --- | --- |
| `steden` | Herkomststeden van bigbags (Gouda, Breda, Almere), uitbreidbaar |
| `criteria` | Selectiecriteria (beheerbaar in backend): label, toepassing (bigbag/leersample), soort (keuze/getal/tekst), eenheid, "meerdere ja/nee", volgorde |
| `criteria_opties` | Keuzemogelijkheden per criterium |
| `voorraad` | Eén rij per bigbag of leersample. **Bigbag**: unieke QR-code, herkomststad, datum, gewicht en inhoud. **Leersample**: geen QR — handmatig geregistreerd, optioneel gekoppeld aan de bigbag waar het uit komt via `bigbag_id`. Kolom `foto` bevat een geüploade productfoto (bestand in `src/uploads/`) |
| `voorraad_criteria` | Gekozen waarden per voorraad-item (optie en/of vrije waarde) |
| `qr_labels` | Voorbedrukte bigbaglabels: welke codes al als label zijn gegenereerd, door wie en wanneer (voorkomt dubbele nummers). Een code komt pas in `voorraad` na scannen + registreren |
| `gebruikers` | Accounts + rollen (admin/medewerker). Kolom `totp_secret` bevat het 2FA-geheim (Google Authenticator) |
| `recovery_codes` | Eenmalige 2FA-herstelcodes per gebruiker (gehasht opgeslagen) |
| `verkopen` | Verkoopregistraties van leersamples: welke sample, door wie en wanneer verkocht (basis voor logboek/ongedaan maken) |

Per de user story draagt **alleen de bigbag** een QR/streepjescode; de QR
verwijst naar de bigbag-registratie (stad, datum, kg, inhoud). Leersamples
worden met de hand geregistreerd (persoon B) en kunnen aan hun herkomst-bigbag
worden gekoppeld, zodat stad en datum herleidbaar blijven. Als een bigbag
wordt verwijderd, blijven de samples bestaan (alleen de koppeling vervalt).

Criteria en hun keuzes zijn dus **geen code maar data**: extra variabelen
kunnen later worden toegevoegd, aangepast of verwijderd via de database,
zonder dat de applicatie herbouwd hoeft te worden.

## Voorbeeldgegevens (uit de aangeleverde Excel)

- Bigbag: inhoud (leersample / restleer), gewicht (kg)
- Leersample: geur (sterke geur / neutraal), schade (HIGH/MEDIUM/LOW,
  meerdere mogelijk), formaat (XXL–XXS), gewicht (gram), dikte (mm),
  soepelheid (HIGH/MEDIUM/LOW, meerdere), optisch (uitstekend–slecht,
  meerdere), kleurcategorie (Zwart, Wit, ...), kleur detail (Antraciet, ...,
  meerdere), PANTONE-code en vrij in te vullen velden voor Pantone TCX,
  TPG en kleurnaam.

## Projectstructuur

- `init.sql` — volledige database: schema + voorbeeldgegevens (steden, criteria, opties, gebruikers)
- `src/` — de applicatie (platte PHP, geen framework)
  - `index.php` voorraadoverzicht met tabs (Alles/Bigbags/Leersamples) en filters
  - `galerij.php` verkoopgalerij van leersamples (foto's, verkoop + ongedaan maken)
  - `scan.php` camera-scannen met QR/streepjescode
  - `labels.php` + `qrcode.min.js` QR-labels genereren (alleen beheerder)
  - `login.php`/`logout.php`/`auth.php` inloggen en rechten
  - `totp.php` + `tweestap.php` + `2fa-setup.php` tweestapsverificatie (Google Authenticator)
  - `profile.php` "Mijn account" (2FA zelf regelen) · `users.php`/`user_edit.php` accountbeheer (admin)
  - `functies.php` gedeelde helpers (dynamische criteria) · `nav.php` navigatie
  - `style.css` + `fonts/` merkstijl (Poppins, kleuren van circuleather.com) · `db.php` databaseverbinding
  - `head.php` gedeelde `<head>` (PWA-manifest, iconen, stijl) · `manifest.webmanifest` + `icon-*.png` PWA-ondersteuning
  - `uploads/` geüploade productfoto's (map wordt bijgehouden, staat in .gitignore)
  - `live.js` live-updates + app-hulp (toasts, FAB, inklapbare filters)
- `docker-compose.yml` + `Dockerfile` — lokale omgeving (Apache + PHP + MySQL + phpMyAdmin)

## Voorraad: tabs en filters

De pagina "Voorraad" heeft drie tabbladen: **Alles**, **Bigbags (n)** en
**Leersamples (n)** — de aantallen worden live bijgewerkt. Per tab kun je
filteren:

- Bigbags: status, herkomststad, inhoud (leersample/restleer) en zoektekst.
- Leersamples: status, kleurcategorie, formaat, geur en de andere
  criteria die in de database staan — extra criteria die je later in de
  backend toevoegt verschijnen dus automatisch als filter.
- Het tabblad Alles biedt de gemeenschappelijke filters (status, stad, zoek).

Bij elke leersample staat een **kleurbolletje** (de kleurcategorie van het
stuk leer), zodat samples in één oogopslag herkenbaar zijn. De filters en
tellingen werken ook op de live-update-fragmenten (zie hieronder).

## Live-updates (zonder herladen)

De pagina's "Voorraad" en "Galerij" pollen elke 3 seconden een licht
fragment-eindpunt (`?deel=tbody` / `?deel=grid`) en vervangen alleen het
veranderde deel (rijen/kaarten + tellingen). Zet iemand anders een item toe,
wijzigt de status of verkoopt een sample, dan zie je dat op een open pagina
binnen enkele seconden verschijnen — zonder te verversen. In een tab op de
achtergrond wordt er niet gepollt; zodra je terugkeert volgt direct een
controle. De gegevens komen uit dezelfde database en dezelfde sessiebeveiliging
als de gewone pagina's.

## Mobiel & PWA (installeren op je telefoon)

De app is mobiel-eerst ontworpen: op een telefoon krijg je een echte
app-ervaring in plaats van een verkleinde website.

- **Onderste navigatiebalk** met pictogrammen (Voorraad, Galerij, Scan en
  Beheer/Labels voor beheerders of Account voor medewerkers) — altijd
  binnen handbereik, ook op schermen met een "thuistoets" (safe-area).
- **Zwevende actieknop (+)**: op de voorraadpagina open je daarmee in één
  tik "Bigbag toevoegen", "Leersample toevoegen" of "Scannen".
- **Filters inklapbaar**: op telefoons staat één "Filters"-knop (met het
  aantal actieve filters) in plaats van een muur van dropdowns; op laptop
  staan de filters gewoon inline.
- **Vaste opslaan-balk**: bij toevoegen/bewerken blijft "Annuleren/Opslaan"
  onderaan in beeld terwijl je scrollt.
- **Toasts**: succesmeldingen verschijnen als korte melding bovenaan en
  verdwijnen vanzelf; foutmeldingen blijven staan.
- **Installeren (PWA)**: de app heeft een manifest + app-iconen. Op je
  telefoon kies je "Toevoegen aan beginscherm" (Android: menu → App
  installeren; iPhone: Delen → Zet op beginscherm). De app opent daarna
  eigen venster (standalone) met een eigen icoon, zoals een echte app.
- Grotere knoppen en invoervelden (geen per ongeluk zoom op iOS), live-stipje
  op pagina's die automatisch bijwerken, en rijen als kaarten met een
  kleurrand (rood = bigbag, goud = leersample) en een pijltje naar Bewerken.

## Inloggen en accounts

De applicatie is beveiligd met een login. Standaardaccounts (eerste keer
meteen het wachtwoord wijzigen via Medewerkers → Bewerken):

| Rol | E-mail | Wachtwoord |
| --- | --- | --- |
| Beheerder | admin@circuleather.nl | admin123 |
| Medewerker | medewerker@circuleather.nl | medewerker123 |

- **Beheerder (admin)** kan alles, inclusief medewerkers aanmaken, bewerken
  en verwijderen (pagina "Medewerkers"), rollen/wachtwoorden instellen,
  QR-labels genereren (pagina "QR-labels") en 2FA van accounts uitzetten
  (bijv. verloren telefoon). Beheerders zijn verplicht 2FA te gebruiken.
- **Medewerker** kan voorraad bekijken, toevoegen, bewerken en scannen, en
  samples verkopen via de Galerij (geen QR-labels genereren, geen
  medewerkersbeheer). 2FA is voor medewerkers optioneel (Mijn account).

> ⚠️ Zodra de app via een publieke tunnel of op internet staat, moet je de
> standaardwachtwoorden meteen wijzigen (Medewerkers → Bewerken). Anders kan
> iedereen met de link inloggen en data wijzigen.

## Tweestapsverificatie (Google Authenticator)

- **Verplicht voor beheerders**: bij de eerste login na deze update (of na een
  verse install) verschijnt direct een scherm om 2FA in te stellen: scan de
  QR-code met Google Authenticator (of Authy/Microsoft Authenticator), voer
  de code in en bewaar de tien eenmalige herstelcodes die daarna verschijnen.
  Bij elke volgende login wordt ná het wachtwoord de 6-cijferige code
  gevraagd; een herstelcode werkt als vervanging (elke code één keer).
- **Optioneel voor medewerkers**: via de pagina "Mijn account" (klik op je
  naam) kun je 2FA aan- of uitzetten of opnieuw instellen. Uitzetten en
  opnieuw instellen vragen om een geldige code.
- Telefoon kwijt? Een beheerder zet 2FA voor het account uit via
  Medewerkers → Bewerken; daarna stelt de gebruiker het opnieuw in.
- De tijd van de telefoon moet kloppen (anders wijkt de code af).

> Let op: wie het wachtwoord van een beheerder bezit én de 2FA-setup nog
> niet heeft afgerond, kan zelf de setup doorlopen. Stel 2FA daarom direct
> in zodra de app publiek bereikbaar is (en wijzig het standaardwachtwoord).

## Logboek (audit-log, alleen beheerder)

Alles wat er in de app gebeurt wordt vastgelegd in de tabel `logboek`:
wie deed wat en wanneer, vanaf welk IP-adres en welk apparaat
(inloggen, mislukte inlogpogingen, toevoegen/bewerken/verwijderen van
voorraad, statuswijzigingen, verkoop + ongedaan maken, foto's, gebruikers-
beheer, 2FA-wijzigingen, labelgeneratie, uitloggen).

- **Pagina "Logboek"** (menu boven, en via Beheer → "📋 Logboek bekijken"):
  één overzicht dat **live** bijwerkt — een actie van iemand anders verschijnt
  binnen enkele seconden zonder herladen.
- **Filters**: periode (van/tot), persoon, actietype en vrije zoektekst
  (beschrijving, IP of apparaat).
- **Download**: met de knop "⬇ Download CSV" sla je de (gefilterde) lijst op
  als CSV (Excel-vriendelijk, met BOM) — gesegmenteerd naar periode, persoon
  en/of actie zoals in de user cases gevraagd.

## Onthouden apparaten (2FA niet elke keer)

Na het invoeren van je 2FA-code kun je **"Onthoud dit apparaat 30 dagen"**
aanvinken: op die telefoon of computer wordt de code de komende 30 dagen
overgeslagen (het wachtwoord blijft altijd nodig). Dit apparaat wordt
vastgelegd in de tabel `apparaten`.

- **Mijn account → "Onthouden apparaten"**: zie elk onthouden apparaat
  (naam, laatste gebruik, IP) en verwijder er één — daar wordt dan weer om
  een code gevraagd.
- **Beheerder → Medewerkers → Bewerken**: zie hoeveel apparaten een account
  onthoudt, wis ze allemaal (verloren telefoon), en bij een 2FA-reset worden
  alle onthouden apparaten automatisch ongeldig.
- Veiligheid: het apparaat wordt alleen herkend via een uniek, onomkeerbaar
  gehasht token in een beveiligde cookie (HttpOnly + SameSite=Lax, alleen
  https); de code staat nooit in de cookie.

## Galerij en verkoop (leersamples)

De pagina "Galerij" toont alle leersamples als verkoopkaarten met een
**foto van het stuk leer** (of bij ontbreken daarvan een kleurstaal op basis
van de kleurcategorie), formaat, gewicht en andere criteria, plus de
koppeling naar de herkomst-bigbag. Filters: Te koop / Alles / Verkocht,
kleurcategorie en vrije zoektekst.

- **Foto's**: voeg bij het registreren ("+ Leersample toevoegen") of via
  Bewerken een foto toe — op je telefoon kun je direct met de camera een
  foto maken. Ook staat op elke galerijkaart een klein knopje
  ("📷 Foto" / "📷 Vervang") om ter plekke een foto te maken of te
  vervangen; foto's worden opgeslagen in `src/uploads/` en bij vervangen/
  verwijderen opgeruimd.
- **Verkoop**: op een te-koop-sample druk je op "Verkoop" — de sample wordt
  `verkocht` en verdwijnt uit de te-koop-lijst; de actie (wie, wanneer,
  welke sample) wordt vastgelegd in de tabel `verkopen` (basis voor een
  logboek).
- **Ongedaan maken**: onder "Verkocht" staat per sample een knop om de
  verkoop terug te draaien (sample weer `beschikbaar`).

De galerij is bedoeld om aan een klant te tonen vanaf telefoon of tablet;
registratie van verkoopprijzen en een volledig logboek zijn logische
volgende stappen (zie ook de user cases).

## Scannen met de camera

Op "📷 Scannen" kun je met de camera van je telefoon een QR-code of
streepjescode scannen. Zo werkt het in de praktijk: de QR zit al op de (lege)
bigbag voordat die geregistreerd wordt. Een <strong>onbekende code</strong> is dus
een nieuwe zak — de app biedt aan die direct als nieuwe bigbag te registreren
(code staat vooringevuld, daarna wegen + herkomst/datum/inhoud invullen).
Een <strong>bekende code</strong> toont het geregistreerde item, met de samples
die eruit zijn geregistreerd en een status die je in één tik bijwerkt.

Automatisch scannen gebruikt de browser-API `BarcodeDetector`
(Chrome/Edge/Android en recent Safari). Belangrijk:

- De camera werkt alleen via **https** of **localhost** (browsers blokkeren
  camera op een gewone http:// adres). Via http://IP-adres kun je daarom
  niet met de camera scannen — dan werkt de handmatige code-invoer.
- Browsers zonder barcode-ondersteuning tonen automatisch het
  handmatige invoerveld als alternatief.

### Voorbeeld-QR-codes om te testen

In de map `test-qr/` (alleen lokaal, staat in .gitignore) staan echte,
realistische bigbagcodes zoals ze op zakken zouden verschijnen
(`BB-<jaar>-<volgnummer>`). Open `test-qr/index.html` in je browser voor een
overzicht met alle QR's.

- `BB-2026-004` t/m `BB-2026-006` — **verse zaklabels**: deze codes staan
  bewust <em>niet</em> in de database. Dat is het echte testdoel: scan zo'n
  code en de app zegt "Nieuwe zak", waarna je de bigbag registreert en hij
  in de voorraad komt. `labels.html` is een printbare A4-pagina met deze
  drie labels om op lege zakken te plakken.
- `BB-2026-001` t/m `BB-2026-003` — **demo-voorraad**: deze bigbags staan in
  de lokale database (bij `BB-2026-001` hoort ook één gekoppeld leersample).
  Scan zo'n code en je ziet het verschil: "al geregistreerd".

Na een database-reset zet je de demo-voorraad terug met
`test-qr/herstel-demo.sql` (via phpMyAdmin of de mysql-client). Codes
004–006 blijven daarna bewust ongeregistreerd — dat zijn de verse zaklabels.
Elk `BB-….png` bevat alleen de code (scannen in de scan-pagina), geen
link — zo werkt het ook in het echt: de code staat op de zak, het adres van
de app kan later wijzigen.

### QR-labels genereren (alleen beheerder)

Op de pagina "QR-labels" (menu, alleen zichtbaar voor beheerders) maak je
voorbedrukte zaklabels. De app kiest zelf de eerstvolgende vrije nummers
(`BB-<jaar>-<volgnummer>`) en toont ze als printbare A4-labels — gedrukte
nummers worden nooit hergebruikt. Plak zo'n label op een lege bigbag; bij
inname scan je de code (📷 Scannen) en wordt de zak als nieuwe bigbag
geregistreerd. De gegenereerde codes staan in tabel `qr_labels` (code, door
wie, wanneer) en komen pas in `voorraad` zodra ze zijn gescand en
geregistreerd. De scan-pagina herkent zo'n label en meldt "Label is eerder
gegenereerd door … op …".

## Gratis online zetten (testen op je telefoon)

### Snelste optie: tijdelijke https-tunnel naar je lokale Docker-app

Dit vraagt niets op te zetten en gebruikt gewoon je lopende database.

1. Installeer Cloudflared (Windows: `winget install cloudflared`, of download
   de exe van https://github.com/cloudflare/cloudflared/releases).
2. Zorg dat Docker met de app draait (`docker compose up`).
3. Start in een terminal: `cloudflared tunnel --url http://localhost:8080`
4. Cloudflared print een https-adres zoals
   `https://iets-willekeurigs.trycloudflare.com`. Open dat adres op je telefoon:
   camera-scannen werkt nu, want het adres is https.

Let op: de laptop en de tunnel moeten aan blijven, het adres verandert bij
elke herstart en alles op het adres is publiek bereikbaar.

### Echt online: gratis hosting met PHP + MySQL (bijv. InfinityFree)

InfinityFree (https://www.infinityfree.com) is gratis, zonder creditcard, met
PHP 8, MySQL en https op je eigen subdomein.

1. Maak een account en een hosting-account aan; je krijgt een subdomein
   (bijv. `circuleather.epizy.com`) met https.
2. Maak in het controlepaneel een MySQL-database aan. Noteer de
   databasenaam, -gebruiker, -wachtwoord en de host (zoals `sqlXXX.infinityfree.com`).
3. Kopieer `src/db.local.example.php` naar `src/db.local.php` en vul die
   gegevens in (dit bestand staat in .gitignore, dus je wachtwoord wordt
   nooit gecommit).
4. Upload alle bestanden uit `src/` (inclusief `db.local.php`) naar de
   `htdocs`-map van je hosting-account, via de bestandsbeheerder of FTP.
5. Importeer `init.sql` in de nieuwe database via het phpMyAdmin van je
   hoster (tabblad Import).
6. Open je subdomein en log in met de standaardaccounts (zie boven).
   Verander meteen de wachtwoorden, want de site is nu publiek.

De online database is een aparte kopie: gegevens die je daar toevoegt staan
niet in je lokale Docker-database en andersom. Na het testen kun je de
online database weer exporteren/importen via phpMyAdmin als je de gegevens
terug wilt.
