# ER-Diagramm – Digitaler Pendelordner

Entity-Relationship-Modell der Datenbank. Tabellen-/Spaltennamen sind englisch
(Laravel-Standard), die Oberfläche und Kommentare sind deutsch. Auf GitHub wird
das Mermaid-Diagramm direkt gerendert.

```mermaid
erDiagram
    BUSINESSES ||--o{ BANK_ACCOUNTS : has
    BUSINESSES ||--o{ COST_CENTERS : has
    BUSINESSES ||--o{ BANK_TRANSACTIONS : tagged
    BUSINESSES ||--o{ RECEIPTS : tagged
    BUSINESSES ||--o{ DATEV_EXPORTS : for

    FINTS_CONNECTIONS ||--o{ BANK_ACCOUNTS : supplies
    BANK_ACCOUNTS ||--o{ BANK_TRANSACTIONS : contains
    BANK_ACCOUNTS ||--o{ IMPORT_LOGS : logs
    IMPORT_LOGS ||--o{ BANK_TRANSACTIONS : imports

    CATEGORIES ||--o{ CATEGORIES : parent
    CATEGORIES ||--o{ BANK_TRANSACTIONS : classifies
    CATEGORIES ||--o{ RECEIPTS : classifies
    CATEGORIES ||--o{ SUPPLIERS : default
    CATEGORIES ||--o{ MATCHING_RULES : target

    COST_CENTERS ||--o{ BANK_TRANSACTIONS : books
    COST_CENTERS ||--o{ RECEIPTS : books
    COST_CENTERS ||--o{ ACCOUNT_ASSIGNMENTS : kost1

    SUPPLIERS ||--o{ BANK_TRANSACTIONS : payee
    SUPPLIERS ||--o{ RECEIPTS : issuer
    SUPPLIERS ||--o{ MATCHING_RULES : about

    BANK_TRANSACTIONS ||--o{ BANK_TRANSACTION_RECEIPT : "n:m (partial amount)"
    RECEIPTS ||--o{ BANK_TRANSACTION_RECEIPT : "n:m (partial amount)"

    BANK_TRANSACTIONS ||--o{ BANK_TRANSACTION_COST_CENTER : split
    COST_CENTERS ||--o{ BANK_TRANSACTION_COST_CENTER : split
    RECEIPTS ||--o{ COST_CENTER_RECEIPT : split
    COST_CENTERS ||--o{ COST_CENTER_RECEIPT : split

    BANK_TRANSACTIONS ||--o{ ACCOUNT_ASSIGNMENTS : "polymorph"
    RECEIPTS ||--o{ ACCOUNT_ASSIGNMENTS : "polymorph"
    DATEV_EXPORTS ||--o{ ACCOUNT_ASSIGNMENTS : bundles

    BUSINESSES {
        id bigint PK
        name string
        type enum "gas_station|workshop|expert_office|shop|other"
        color string
        active bool
    }
    FINTS_CONNECTIONS {
        id bigint PK
        label string
        bank_code string "BLZ"
        fints_url string
        username string
        pin text "encrypted"
        tan_method string
    }
    BANK_ACCOUNTS {
        id bigint PK
        business_id bigint FK
        fints_connection_id bigint FK
        label string
        iban string
        bic string
        balance decimal
        fints_enabled bool
    }
    IMPORT_LOGS {
        id bigint PK
        bank_account_id bigint FK
        source enum "fints|mt940|camt|csv|manual"
        new_count int
        duplicate_count int
        status string
    }
    BANK_TRANSACTIONS {
        id bigint PK
        bank_account_id bigint FK
        business_id bigint FK
        category_id bigint FK
        cost_center_id bigint FK
        supplier_id bigint FK
        booking_date date
        value_date date
        counterparty string
        purpose text
        amount decimal
        status enum "open|partially|fully|reviewed"
        dedup_hash string "UNIQUE per account"
    }
    RECEIPTS {
        id bigint PK
        business_id bigint FK
        supplier_id bigint FK
        category_id bigint FK
        cost_center_id bigint FK
        type enum "incoming_invoice|outgoing_invoice|cash|other"
        invoice_number string
        invoice_date date
        gross_amount decimal
        tax_amount decimal
        file_path string
        ocr_text longtext
        ocr_status enum
        status enum
    }
    BANK_TRANSACTION_RECEIPT {
        id bigint PK
        bank_transaction_id bigint FK
        receipt_id bigint FK
        amount decimal "allocated share"
        match_type enum
        match_score decimal
    }
    MATCHING_RULES {
        id bigint PK
        pattern string "e.g. HBW"
        pattern_type enum "counterparty|purpose|iban"
        supplier_id bigint FK
        category_id bigint FK
        cost_center_id bigint FK
        hit_count int "learning counter"
        priority int
    }
    ACCOUNT_ASSIGNMENTS {
        id bigint PK
        assignable_type string "polymorph"
        assignable_id bigint
        chart_of_accounts enum "skr03|skr04"
        account string
        contra_account string
        tax_key string
        cost_center_id bigint FK
        datev_export_id bigint FK
    }
    DATEV_EXPORTS {
        id bigint PK
        business_id bigint FK
        label string
        from_date date
        to_date date
        chart_of_accounts enum
        consultant_number string
        client_number string
    }
    CATEGORIES {
        id bigint PK
        parent_id bigint FK
        name string
        skr03_account string
        skr04_account string
        tax_key string
    }
    COST_CENTERS {
        id bigint PK
        business_id bigint FK
        number string "KOST1"
        name string
    }
    SUPPLIERS {
        id bigint PK
        name string
        default_category_id bigint FK
        default_cost_center_id bigint FK
        iban string
        creditor_number string
    }
```

## Beziehungen in Kürze

| Beziehung | Typ | Bedeutung |
|-----------|-----|-----------|
| `bank_transactions` ↔ `receipts` | n:m (`bank_transaction_receipt`) | Ein Umsatz kann mehrere Belege enthalten; ein Beleg kann auf mehrere Umsätze aufgeteilt werden. Pivot-Feld `amount` hält den Teilbetrag (Modul 5). |
| `fints_connections` → `bank_accounts` | 1:n | Ein Online-Banking-Login versorgt mehrere Konten. |
| `matching_rules` → supplier/category/cost_center | n:1 | Lernfähige Auto-Zuordnung (Modul 4). |
| `account_assignments` → transaction/receipt | polymorph | SKR03/04-Buchungsvorbereitung (Modul 13). |
| transaction/receipt ↔ `cost_centers` | n:m (Pivot) | Vorbereitete Mehrfach-Kostenstellen-Verteilung (Modul 9). |
