# Projektstruktur

Tabellen-, Spalten- und Code-Bezeichner sind **englisch** (Laravel-Standard),
die **Oberfläche** ist deutsch (Filament-Labels), **Kommentare** sind deutsch.

```
Pendelordner/
├─ app/
│  ├─ Enums/                         Status-/Typ-Enums (Filament-Labels/Farben/Icons, deutsch)
│  │  ├─ BusinessType.php
│  │  ├─ TransactionStatus.php       Rot=open, Gelb=partially, Grün=fully/reviewed
│  │  ├─ ReceiptType.php  ReceiptStatus.php  OcrStatus.php
│  │  ├─ ImportSource.php
│  │  └─ ChartOfAccounts.php         SKR03 / SKR04 / Other
│  ├─ Models/                        Eloquent-Models (englische Tabellen)
│  │  ├─ Business.php  BankAccount.php  FintsConnection.php
│  │  ├─ BankTransaction.php         zentral: receipts() n:m, recalculateStatus()
│  │  ├─ Receipt.php                 OCR-Felder, bankTransactions() n:m
│  │  ├─ Category.php  CostCenter.php  Supplier.php
│  │  ├─ MatchingRule.php            lernfähig (hit_count)
│  │  ├─ AccountAssignment.php       polymorph (BankTransaction/Receipt)
│  │  ├─ DatevExport.php  ImportLog.php
│  │  └─ User.php                    implements FilamentUser
│  ├─ Filament/
│  │  ├─ Resources/<Model>s/
│  │  │  ├─ <Model>Resource.php
│  │  │  ├─ Schemas/<Model>Form.php       Formular (Filament-Schema)
│  │  │  ├─ Tables/<Model>sTable.php      Tabelle
│  │  │  ├─ RelationManagers/             z.B. ReceiptsRelationManager
│  │  │  └─ Pages/                        List/Create/Edit
│  │  └─ Widgets/                    Dashboard-Widgets (Phase 3)
│  ├─ Services/                      OCR, Matching, Import, FinTS, PDF (Phase 2+)
│  └─ Providers/Filament/AdminPanelProvider.php
├─ config/
│  ├─ pendelordner.php               OCR-, Matching-, Kontierungs-Einstellungen
│  └─ filesystems.php                Disk 'belege' (storage/app/belege/JJJJ/MM/TT)
├─ database/
│  ├─ migrations/                    16 Tabellen (2026_06_24_1001xx_*)
│  └─ seeders/
│     ├─ MasterDataSeeder.php        Businesses, CostCenters, Categories
│     ├─ SupplierSeeder.php          Suppliers + MatchingRules
│     └─ DatabaseSeeder.php          Admin-User + Aufruf der Seeder
├─ docs/                             ER-Diagramm, Installation, Roadmap, Struktur
├─ server.php                        Router für `php artisan serve`
├─ tests/Feature/PanelSmokeTest.php  rendert alle Panel-Seiten
└─ storage/app/belege/              Belegarchiv (Dateien)
```

## Namens- und Sprachkonventionen

- **Schema/Code: Englisch.** Tabellen pluralisiert (`bank_transactions`,
  `receipts`, `cost_centers`), Spalten snake_case (`booking_date`, `amount`,
  `counterparty`, `purpose`).
- **Oberfläche: Deutsch.** Alle sichtbaren Texte über Filament-Labels
  (`->label('Buchungsdatum')`) und Enum-`getLabel()`.
- **Kommentare: Deutsch**, mit Fachbegriff-Erläuterung (z. B. `counterparty`
  = Empfänger/Auftraggeber, `purpose` = Verwendungszweck).
- **Enums** kapseln Status/Typen und liefern via Filament-Contracts direkt
  Label, Farbe und Icon für die UI.
- **Pivot `bank_transaction_receipt`** trägt den Teilbetrag (`amount`) und
  ermöglicht „mehrere Belege je Umsatz“ sowie das Aufteilen eines Belegs.

### Glossar (Deutsch ↔ Code)

| Deutsch | Tabelle / Model | wichtige Spalten |
|---------|-----------------|------------------|
| Betrieb | businesses / Business | type, short_name |
| Bankkonto | bank_accounts / BankAccount | label, iban, balance |
| FinTS-Zugang | fints_connections / FintsConnection | bank_code, username, pin |
| Bankumsatz | bank_transactions / BankTransaction | booking_date, counterparty, amount, purpose |
| Beleg | receipts / Receipt | invoice_number, gross_amount, ocr_text |
| Lieferant | suppliers / Supplier | creditor_number, default_category_id |
| Kategorie | categories / Category | skr03_account, tax_key |
| Kostenstelle | cost_centers / CostCenter | number (KOST1) |
| Zuordnungsregel | matching_rules / MatchingRule | pattern, hit_count |
| Kontierung | account_assignments / AccountAssignment | account, contra_account, tax_key |
| Import-Protokoll | import_logs / ImportLog | source, new_count, duplicate_count |
