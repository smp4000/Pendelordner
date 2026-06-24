# Roadmap

## ✅ Phase 1 – Fundament (abgeschlossen)

- Laravel 12 + Filament 5 Grundgerüst, deutsche Lokalisierung
- Vollständiges Datenmodell (16 Tabellen, Migrationen, Soft Deletes, Indizes)
- Eloquent-Models mit Beziehungen, Enums, berechneten Attributen
- Seeder: Betriebe, Kostenstellen, 19 Kategorien (SKR03/04), Lieferanten,
  lernfähige Zuordnungsregeln, Admin-Benutzer
- Filament-Panel mit allen Kern-Resources (Bankumsatz im Lexware-Stil,
  Beleg-Upload, Stammdaten), Navigationsgruppen, Status-Ampel
- Beleg↔Umsatz-Zuordnung mit Teilbeträgen (Relation Manager)
- Render-Smoke-Test (grün)

## ✅ Phase 2 – Services & Belegfluss (abgeschlossen)

- **OcrService**: PDF-Text via `smalot/pdfparser`, sonst Tesseract; Erkennung
  von Rechnungsnummer, Datum, Beträgen, Steuer, IBAN (ReceiptParser)
- **BankImportService**: MT940-, CAMT.053- und CSV-Import mit Dublettenprüfung
  (`dedup_hash`) und automatischer Vorkontierung über die Regeln
- **FinTSService**: Live-Abruf über `nemiah/php-fints`, Mapping ins Importformat,
  TAN-Behandlung (fortsetzbare Exception)
- **MatchingEngine**: gewichteter Score aus Betrag, Lieferant, Datum, IBAN;
  Vorschläge ab Schwellwert; Regeln lernen (`hit_count`)
- UI: Import-/FinTS-Aktionen am Bankkonto, OCR-Aktion + Auto-OCR beim Beleg

## ✅ Phase 3 – Ansichten & Auswertungen (abgeschlossen)

- **Kontoumsatzdetails** (Modul 6): 3-Spalten-Ansicht (Umsatzdetails | Belege +
  Vorschläge | PDF-Vorschau) inkl. „geprüft“ und Ein-Klick-Zuordnung
- **Dashboard-Widgets** (Modul 10): Kennzahlen, Monatsvergleich, Kosten je
  Kategorie, Top-Lieferanten (Chart.js)
- **PDF-Bericht** (Modul 12): Seite „Steuerberater-Bericht“ + PdfReportService
  (DomPDF + FPDI), Belege in exakter Umsatzreihenfolge angehängt
- **Globale Suche** (Modul 11): Bankumsatz, Beleg (inkl. OCR-Text), Lieferant

## 🔜 Phase 4 – Buchhaltung & Komfort

- **Kontierung** (Modul 13): SKR03/04-Buchungssätze aus Regeln/Kategorien
  konkret erzeugen und in `account_assignments` ablegen (Datenmodell steht)
- **DATEV-Export** (Modul 14): EXTF-Buchungsstapel-CSV, Debitoren/Kreditoren
- **Auswertungen vertiefen**: Kosten je Tankstelle/Kostenstelle/Bankkonto,
  Jahresvergleich, Kostenentwicklung als eigene Auswertungsseite
- Queue-Jobs für OCR/Import (Server-Betrieb)
- FinTS-Scheduler (automatischer Abruf), TAN-Eingabe-Flow in der UI
- Mehrfach-Kostenstellen-Verteilung in der UI aktivieren

## 🔮 Später

- Mandantenfähigkeit & Benutzerrechte (für Server-Betrieb)
- Tankstellen 2..n im Echtbetrieb
