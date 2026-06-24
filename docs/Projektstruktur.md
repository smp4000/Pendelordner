# Projektstruktur

```
Pendelordner/
├─ app/
│  ├─ Enums/                         Typsichere Enums (Filament-Labels/Farben/Icons)
│  │  ├─ BetriebTyp.php
│  │  ├─ BankumsatzStatus.php        Rot=offen, Gelb=teilweise, Grün=fertig
│  │  ├─ BelegTyp.php  BelegStatus.php  OcrStatus.php
│  │  ├─ ImportQuelle.php
│  │  └─ Kontenrahmen.php            SKR03 / SKR04 / Sonstige
│  ├─ Models/                        Eloquent-Models (deutsche Tabellennamen)
│  │  ├─ Betrieb.php  Bankkonto.php  FintsZugang.php
│  │  ├─ Bankumsatz.php              zentral: belege() n:m, statusNeuBerechnen()
│  │  ├─ Beleg.php                   OCR-Felder, bankumsaetze() n:m
│  │  ├─ Kategorie.php  Kostenstelle.php  Lieferant.php
│  │  ├─ ZuordnungsRegel.php         lernfähig (treffer_anzahl)
│  │  ├─ Kontierung.php              polymorph (Bankumsatz/Beleg)
│  │  ├─ DatevExport.php  ImportProtokoll.php
│  │  └─ User.php                    implements FilamentUser
│  ├─ Filament/
│  │  ├─ Resources/<Entität>s/
│  │  │  ├─ <Entität>Resource.php
│  │  │  ├─ Schemas/<Entität>Form.php     Formular (Filament-Schema)
│  │  │  ├─ Tables/<Entität>sTable.php    Tabelle
│  │  │  ├─ RelationManagers/             z.B. BelegeRelationManager
│  │  │  └─ Pages/                        List/Create/Edit
│  │  └─ Widgets/                    Dashboard-Widgets (Phase 3)
│  ├─ Services/                      OCR, Matching, Import, FinTS, PDF (Phase 2+)
│  └─ Providers/Filament/AdminPanelProvider.php
├─ config/
│  ├─ pendelordner.php               OCR-, Matching-, Kontierungs-Einstellungen
│  └─ filesystems.php                Disk 'belege' (storage/app/belege/JJJJ/MM/TT)
├─ database/
│  ├─ migrations/                    16 Tabellen (2026_06_24_1000xx_*)
│  └─ seeders/
│     ├─ StammdatenSeeder.php        Betriebe, Kostenstellen, Kategorien
│     ├─ LieferantenSeeder.php       Lieferanten + Zuordnungsregeln
│     └─ DatabaseSeeder.php          Admin-User + Aufruf der Seeder
├─ docs/                             ER-Diagramm, Installation, Roadmap, Struktur
├─ tests/Feature/PanelSmokeTest.php  rendert alle Panel-Seiten
└─ storage/app/belege/              Belegarchiv (Dateien)
```

## Namenskonventionen

- **Tabellen** sind deutsch und pluralisiert (`bankumsaetze`, `belege`,
  `kostenstellen`); Models setzen `protected $table` entsprechend.
- **Enums** kapseln Status/Typen und liefern via Filament-Contracts direkt
  Label, Farbe und Icon für die UI.
- **Filament-Resource-Ordner** heißen nach der automatischen Pluralisierung
  (`Bankumsatzs`, `Kategories`) – rein namespace-intern, ohne Funktionsbezug.
- **Pivot `beleg_bankumsatz`** trägt den Teilbetrag (`betrag`) und ermöglicht
  damit „mehrere Belege je Umsatz“ sowie das Aufteilen eines Belegs.
