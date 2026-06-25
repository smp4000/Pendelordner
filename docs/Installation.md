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

## 6. Automatischer Bankabruf (FinTS, Modul 1)

Voraussetzung: Pro Bank ein **FinTS-Zugang** anlegen (Panel → Bank → FinTS-Zugänge)
mit BLZ, FinTS-URL, Benutzerkennung, PIN und (bei VR-Bank) TAN-Verfahren.

**Empfohlen für VR-Bank & Co. (Konten automatisch ermitteln):**
Panel → Bank → **„FinTS-Konten abrufen"**:
1. FinTS-Zugang wählen → **„Konten abrufen"**.
2. Falls die Bank eine **TAN** verlangt, erscheint ein Eingabefeld – TAN
   eingeben und bestätigen.
3. Die gefundenen Konten auswählen und **speichern** (legt die Bankkonten an,
   „FinTS aktiv" wird automatisch gesetzt).
4. Pro Konto **„Umsätze abrufen"** (auch hier ggf. TAN). Danach läuft der
   tägliche automatische Abruf für diese Konten.

Alternativ Bankkonto manuell anlegen und beim Konto „FinTS aktiv" setzen sowie
den Zugang zuordnen.

Manuell abrufen geht jederzeit über den Button **„FinTS abrufen"** am Bankkonto
oder per Befehl:

```bash
php artisan bank:fetch              # alle FinTS-Konten
php artisan bank:fetch --account=1 # nur Konto 1
php artisan bank:fetch --days=30   # Zeitraum 30 Tage
```

**Automatisch (zeitgesteuert):** Der Abruf ist im Laravel-Scheduler auf täglich
06:00 Uhr eingeplant (`routes/console.php`, Uhrzeit per `BANK_FETCH_TIME` in
`.env`). Damit das läuft, muss der Scheduler regelmäßig gestartet werden.

Variante A – dauerhaft laufender Worker (einfach):
```bash
php artisan schedule:work
```

Variante B – Windows-Aufgabenplanung (empfohlen für den Dauerbetrieb):
1. Aufgabenplanung öffnen → „Einfache Aufgabe erstellen".
2. Trigger: täglich, alle 1 Minute wiederholen (oder zur gewünschten Uhrzeit).
3. Aktion „Programm starten":
   - Programm: `C:\xampp\php\php.exe`
   - Argumente: `artisan schedule:run`
   - Starten in: `C:\xampp\htdocs\Pendelordner`

> Hinweis TAN/PSD2: Reiner Umsatzabruf ist bei vielen Banken ohne TAN möglich
> (ggf. nach einmaliger Freischaltung, 90-Tage-Fenster). Verlangt die Bank pro
> Abruf eine TAN, ist ein vollautomatischer Lauf nicht möglich – das Konto wird
> dann übersprungen und im FinTS-Zugang („letzte Meldung") vermerkt; nutze in
> dem Fall den manuellen Abruf oder den Datei-Import (MT940/CAMT/CSV).

## 7. Tests

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
