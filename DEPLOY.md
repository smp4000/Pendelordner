# Deployment auf all-inkl (dat.aral-welle.com)

Voraussetzungen: SSH-Zugang (PrivatPlus+), PHP **8.4**, eine MySQL-Datenbank.
Pfade unten ggf. an deinen Benutzernamen anpassen (`/www/htdocs/wXXXXXXX/...`).

---

## 1. Im KAS vorbereiten (Weboberfläche)

1. **Datenbank anlegen**: KAS → Datenbanken → neue MySQL-DB. Name, Benutzer und
   Passwort notieren (für die `.env`).
2. **Subdomain**: `dat.aral-welle.com` anlegen. **Wichtig:** als Webspace-Ziel
   **`/dat.aral-welle.com/public/`** setzen (nicht den Projekt-Stamm!).
   Tipp: erst mit `/dat.aral-welle.com/` anlegen, nach dem Upload (Schritt 2)
   auf `…/public/` ändern.
3. **PHP-Version 8.4** für die Subdomain einstellen.
4. **SSL (Let's Encrypt)** für die Subdomain aktivieren.

---

## 2. Code auf den Server (per SSH)

```bash
ssh sshXXXXXXX@dat.aral-welle.com         # bzw. dein SSH-Host aus dem KAS
cd /www/htdocs/wXXXXXXX/dat.aral-welle.com

# Repo in dieses Verzeichnis klonen (Projekt-Stamm = dieser Ordner, public = Web-Root)
git clone https://github.com/smp4000/Pendelordner.git .

# Abhängigkeiten ohne Dev-Pakete, optimiert
php8.4 $(which composer) install --no-dev --optimize-autoloader
# Falls 'composer' nicht gefunden wird:
#   curl -sS https://getcomposer.org/installer | php8.4 && php8.4 composer.phar install --no-dev --optimize-autoloader
```

---

## 3. Konfiguration

```bash
cp .env.production.example .env
nano .env          # DB_DATABASE / DB_USERNAME / DB_PASSWORD eintragen, APP_URL prüfen
php8.4 artisan key:generate
```

---

## 4. Datenbank & Assets

```bash
php8.4 artisan migrate --force --seed     # Schema + Stammdaten + 1545 Kontenrahmen
php8.4 artisan storage:link               # Symlink public/storage
php8.4 artisan filament:assets            # Filament-CSS/JS nach public/ veröffentlichen

# Produktions-Caches
php8.4 artisan config:cache
php8.4 artisan route:cache
php8.4 artisan view:cache
```

Schreibrechte sicherstellen (falls nötig):
```bash
chmod -R 775 storage bootstrap/cache
```

---

## 5. Cron (geplante Aufgaben)

Im KAS → Cronjobs einen Job anlegen, der **jede Minute** läuft:
```
php8.4 /www/htdocs/wXXXXXXX/dat.aral-welle.com/artisan schedule:run
```
Damit laufen automatisch: täglicher Bankabruf (`bank:fetch`) und – falls
aktiviert – der Mail-Eingang für Belege (`belege:fetch-mail`).

---

## 6. Erststart & Sicherheit

- Aufruf: **https://dat.aral-welle.com** → Login **admin@admin.com**.
- **Sofort das Admin-Passwort ändern** (Profil) – das Seeder-Passwort ist nicht sicher.
- Prüfen, dass `APP_DEBUG=false` ist und die Seite ohne Fehlerdetails lädt.
- Bankumsätze live per **CAMT-Import** einspielen.

---

## Updates später einspielen

```bash
cd /www/htdocs/wXXXXXXX/dat.aral-welle.com
php8.4 artisan down
git pull
php8.4 $(which composer) install --no-dev --optimize-autoloader
php8.4 artisan migrate --force
php8.4 artisan config:cache && php8.4 artisan route:cache && php8.4 artisan view:cache
php8.4 artisan filament:assets
php8.4 artisan up
```

---

## Hinweise

- **Bild-OCR (Tesseract)** steht auf Shared Hosting i. d. R. nicht zur Verfügung
  (`TESSERACT_PATH` leer lassen). PDF-Text-Extraktion funktioniert trotzdem.
- **`.env` niemals** ins Web-Root oder ins Git – sie liegt im Projekt-Stamm,
  der durch das `public/`-Web-Root geschützt ist.
- Bei „500"-Fehler: `storage/logs/laravel.log` prüfen; meist fehlt `APP_KEY`,
  ein DB-Wert oder Schreibrechte auf `storage/`.
