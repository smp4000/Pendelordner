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

## 🔧 Phase 2 – Services & Belegfluss (in Arbeit)

- **OcrService**: PDF-Text via `smalot/pdfparser`, sonst Tesseract; Erkennung
  von Lieferant, Rechnungsnummer, Datum, Beträgen, Steuer, IBAN
- **BankImportService**: MT940-, CAMT.053- und CSV-Import mit Dublettenprüfung
  (`dedup_hash` aus Referenz + Datum + Betrag + Verwendungszweck)
- **FinTSService**: Live-Abruf über `nemiah/php-fints` (PIN/TAN), Speicherung
  ohne Dubletten, Import-Protokoll
- **MatchingEngine**: gewichteter Score aus Betrag, Lieferant, Datum, IBAN;
  Vorschläge ab Schwellwert; Regeln lernen (`treffer_anzahl`)
- Queue-Jobs für OCR/Import, damit Uploads nicht blockieren

## 🔜 Phase 3 – Ansichten & Auswertungen

- **Kontoumsatzdetails** (Modul 6): 3-Spalten-Ansicht (Umsatzdetails | Belege |
  PDF-Vorschau) inkl. Checkboxen „geprüft“ / „vollständig bezahlt“
- **Dashboard-Widgets** (Modul 10): neue Umsätze, Umsätze ohne Beleg, Belege
  ohne Zahlung, offene Zuordnungen, Top-Lieferanten, Kosten Monat/Jahr
- **Auswertungen/Charts** (Chart.js): Kosten je Tankstelle/Kostenstelle/
  Kategorie/Lieferant/Bankkonto, Monats-/Jahresvergleich, Kostenentwicklung
- **Globale Suche** (Modul 11): Lieferant, Rechnungsnummer, Betrag, IBAN,
  Verwendungszweck, OCR-Text, Kategorie, Kostenstelle

## 🔜 Phase 4 – PDF & Buchhaltung

- **PdfReportService** (Modul 12): Steuerberater-Monatsbericht – Deckblatt,
  Zusammenfassung, chronologische Umsatzliste, danach je Umsatz die
  zugehörigen Belege in exakt der Umsatzreihenfolge
- **Kontierung** (Modul 13): SKR03/04-Buchungssätze aus Regeln/Kategorien
  vorbelegen
- **DATEV-Export** (Modul 14): EXTF-Buchungsstapel-CSV, Debitoren/Kreditoren

## 🔮 Später

- FinTS-Zeitplan (automatischer Abruf per Scheduler)
- Mandantenfähigkeit & Benutzerrechte (für Server-Betrieb)
- Mehrfach-Kostenstellen-Verteilung in der UI aktivieren
- Tankstellen 2..n im Echtbetrieb
