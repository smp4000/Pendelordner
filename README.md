# Digitaler Pendelordner

Lokale Webanwendung zur Digitalisierung des Pendelordner-Prozesses
(Beleg- und Bankmanagement) für Tankstellen, Kfz-Werkstatt und
Sachverständigenbüro.

Statt Kontoauszüge auszudrucken und Rechnungen chronologisch dahinter
abzuheften, bildet die Anwendung den kompletten Ablauf digital ab:
Bankumsätze abrufen → Belege erfassen (OCR) → automatisch zuordnen →
auswerten → Pendelordner-PDF für den Steuerberater erzeugen.

> Status: **Module 1–12 funktionsfähig** (Bankimport + FinTS, OCR-Belegarchiv,
> lernfähige Zuordnung, 3-Spalten-Kontoumsatzdetails, Dashboard/Charts,
> Steuerberater-PDF, globale Suche). Kontierung/DATEV (13/14) als Datenmodell
> vorbereitet. Testsuite grün. Details in der [Roadmap](docs/Roadmap.md).

---

## Technischer Stack

| Bereich | Technologie |
|---------|-------------|
| Backend | Laravel 12, PHP 8.2+ (XAMPP) |
| Admin-UI | Filament 5, Livewire 3, TailwindCSS |
| Datenbank | MySQL / MariaDB |
| PDF | barryvdh/laravel-dompdf |
| OCR | Tesseract OCR + smalot/pdfparser |
| Bankanbindung | nemiah/php-fints (FinTS/HBCI) + MT940/CAMT/CSV-Import |
| Charts | Chart.js (Filament-Widgets) |

---

## Schnellstart

Voraussetzungen: XAMPP (Apache optional, MySQL/MariaDB), PHP ≥ 8.2,
Composer, optional Tesseract OCR.

```bash
# 1. Abhängigkeiten
composer install

# 2. Umgebung
cp .env.example .env
php artisan key:generate
#  .env anpassen: DB_DATABASE=pendelordner, DB_USERNAME=root, DB_PASSWORD=

# 3. Datenbank anlegen (MariaDB/MySQL)
#    CREATE DATABASE pendelordner CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# 4. Migrationen + Stammdaten
php artisan migrate --seed

# 5. Speicherverknüpfung für Belegvorschau
php artisan storage:link

# 6. Starten
php artisan serve
```

Panel: <http://127.0.0.1:8000/admin>
Login: `admin@admin.com` / `password`

Ausführliche Anleitung: [docs/Installation.md](docs/Installation.md)

---

## Module (Spezifikation)

| Modul | Inhalt | Status |
|------:|--------|--------|
| 1 | Bankanbindung (FinTS + Import MT940/CAMT/CSV, Dublettenprüfung) | ✅ |
| 2 | Bankumsätze (Lexware-Tabelle, Status-Ampel, Filter) | ✅ |
| 3 | Belegarchiv (Upload, OCR via Tesseract/pdfparser) | ✅ |
| 4 | Automatische Zuordnung (lernfähige Matching-Engine) | ✅ |
| 5 | Mehrere Belege pro Umsatz (Teilbeträge) | ✅ |
| 6 | Kontenumsatzdetails (3-Spalten + PDF-Vorschau) | ✅ |
| 7 | Betriebe / Tankstellen | ✅ |
| 8 | Kategorien | ✅ |
| 9 | Kostenstellen (Mehrfach vorbereitet) | ✅ |
| 10 | Auswertungen / Dashboard / Charts | ✅ |
| 11 | Globale Suche | ✅ |
| 12 | PDF-Bericht (Steuerberater-Pendelordner) | ✅ |
| 13 | Kontierung SKR03/04 (Vorbereitung) | Datenmodell ✅ |
| 14 | DATEV-Export (nur Datenmodell) | Datenmodell ✅ |

---

## Projektstruktur

Siehe [docs/Projektstruktur.md](docs/Projektstruktur.md). Wichtigste Pfade:

```
app/
  Enums/                 Status-/Typ-Enums (engl. Code, deutsche Labels/Farben)
  Models/                Eloquent-Models (engl. Tabellen, deutsche Kommentare)
  Filament/Resources/    Panel-Resources je Entität (deutsche UI)
  Services/              OCR, Matching, Import, FinTS, PDF (in Arbeit)
config/pendelordner.php  OCR-, Matching- und Kontierungs-Konfiguration
database/migrations/     16 Tabellen
database/seeders/        Stammdaten (Businesses, Categories, Suppliers, Rules)
docs/                    ER-Diagramm, Installation, Roadmap, Struktur
```

> **Benennung:** Schema und Code englisch (Laravel-Standard), Oberfläche
> deutsch (Filament-Labels), Kommentare deutsch. Glossar Deutsch↔Code in
> [docs/Projektstruktur.md](docs/Projektstruktur.md).

---

## Dokumentation

- [ER-Diagramm](docs/ER-Diagramm.md)
- [Installationsanleitung](docs/Installation.md)
- [Projektstruktur](docs/Projektstruktur.md)
- [Roadmap](docs/Roadmap.md)
