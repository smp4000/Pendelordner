# Installationsanleitung (Windows / XAMPP)

## 1. Voraussetzungen

| Software | Version | Hinweis |
|----------|---------|---------|
| XAMPP | aktuell | liefert PHP, Apache, MariaDB |
| PHP | ≥ 8.2 | in XAMPP enthalten (`C:\xampp\php`) |
| Composer | ≥ 2.5 | <https://getcomposer.org> |
| Tesseract OCR | ≥ 5 | optional, für Belegerkennung (Modul 3) |

> Hinweis: Die Spezifikation nennt PHP 8.4. Das Projekt läuft auch auf der in
> XAMPP enthaltenen PHP-8.2-Version. Ein späteres Upgrade auf 8.4 ist möglich.

### Tesseract OCR installieren (optional)

1. Installer von <https://github.com/UB-Mannheim/tesseract/wiki> laden.
2. Bei der Installation das **deutsche Sprachpaket (deu)** mitauswählen.
3. Pfad in `.env` eintragen:
   ```
   TESSERACT_PATH="C:/Program Files/Tesseract-OCR/tesseract.exe"
   TESSERACT_LANG=deu
   ```

## 2. MariaDB starten & Datenbank anlegen

XAMPP Control Panel → **MySQL → Start**, dann:

```sql
CREATE DATABASE pendelordner CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

(oder via `C:\xampp\mysql\bin\mysql -u root`).

## 3. Projekt einrichten

```bash
cd C:\xampp\htdocs\Pendelordner

composer install
cp .env.example .env       # falls noch keine .env existiert
php artisan key:generate
```

`.env` prüfen/anpassen:

```ini
APP_LOCALE=de
DB_CONNECTION=mysql
DB_DATABASE=pendelordner
DB_USERNAME=root
DB_PASSWORD=
```

## 4. Datenbank befüllen

```bash
php artisan migrate --seed
php artisan storage:link
```

Damit werden 16 Tabellen angelegt und Stammdaten geladen (Betriebe,
Kostenstellen, 19 Kategorien mit SKR03/04-Kontierung, Beispiel-Lieferanten
und lernfähige Zuordnungsregeln) sowie ein Admin-Benutzer erstellt.

## 5. Starten

```bash
php artisan serve
```

- Panel: <http://127.0.0.1:8000/admin>
- Login: `admin@admin.com` / `password`

### Alternative: Betrieb über Apache (XAMPP)

Da das Projekt unter `C:\xampp\htdocs\Pendelordner` liegt, kann auch der
Apache-DocumentRoot auf `public/` zeigen (VirtualHost) – für den lokalen
Betrieb genügt aber `php artisan serve`.

## 6. Tests

```bash
php artisan test
```

## Fehlerbehebung

| Problem | Lösung |
|---------|--------|
| `SSL certificate problem` bei Composer | `composer config --global cafile "C:/xampp/apache/bin/curl-ca-bundle.crt"` |
| `Module "openssl" is already loaded` | harmlose Warnung; ggf. doppelten `extension=openssl`-Eintrag in `php.ini` entfernen |
| Belegvorschau lädt nicht | `php artisan storage:link` ausführen |
| 403 im Panel | sicherstellen, dass `User::canAccessPanel()` `true` zurückgibt |
