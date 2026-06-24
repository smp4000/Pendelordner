# ER-Diagramm – Digitaler Pendelordner

Entity-Relationship-Modell der Datenbank. Auf GitHub wird das Mermaid-Diagramm
direkt gerendert.

```mermaid
erDiagram
    BETRIEBE ||--o{ BANKKONTEN : hat
    BETRIEBE ||--o{ KOSTENSTELLEN : hat
    BETRIEBE ||--o{ BANKUMSAETZE : zugeordnet
    BETRIEBE ||--o{ BELEGE : zugeordnet
    BETRIEBE ||--o{ DATEV_EXPORTE : fuer

    FINTS_ZUGAENGE ||--o{ BANKKONTEN : versorgt
    BANKKONTEN ||--o{ BANKUMSAETZE : enthaelt
    BANKKONTEN ||--o{ IMPORT_PROTOKOLLE : protokolliert
    IMPORT_PROTOKOLLE ||--o{ BANKUMSAETZE : importiert

    KATEGORIEN ||--o{ KATEGORIEN : parent
    KATEGORIEN ||--o{ BANKUMSAETZE : klassifiziert
    KATEGORIEN ||--o{ BELEGE : klassifiziert
    KATEGORIEN ||--o{ LIEFERANTEN : standard
    KATEGORIEN ||--o{ ZUORDNUNGS_REGELN : ziel

    KOSTENSTELLEN ||--o{ BANKUMSAETZE : bucht
    KOSTENSTELLEN ||--o{ BELEGE : bucht
    KOSTENSTELLEN ||--o{ KONTIERUNGEN : kost1

    LIEFERANTEN ||--o{ BANKUMSAETZE : zahlungsempfaenger
    LIEFERANTEN ||--o{ BELEGE : aussteller
    LIEFERANTEN ||--o{ ZUORDNUNGS_REGELN : betrifft

    BANKUMSAETZE ||--o{ BELEG_BANKUMSATZ : "n:m (Teilbetrag)"
    BELEGE ||--o{ BELEG_BANKUMSATZ : "n:m (Teilbetrag)"

    BANKUMSAETZE ||--o{ BANKUMSATZ_KOSTENSTELLE : verteilung
    KOSTENSTELLEN ||--o{ BANKUMSATZ_KOSTENSTELLE : verteilung
    BELEGE ||--o{ BELEG_KOSTENSTELLE : verteilung
    KOSTENSTELLEN ||--o{ BELEG_KOSTENSTELLE : verteilung

    BANKUMSAETZE ||--o{ KONTIERUNGEN : "polymorph"
    BELEGE ||--o{ KONTIERUNGEN : "polymorph"
    DATEV_EXPORTE ||--o{ KONTIERUNGEN : buendelt

    BETRIEBE {
        id bigint PK
        name string
        typ enum "tankstelle|werkstatt|sachverstaendigenbuero|shop|sonstige"
        farbe string
        aktiv bool
    }
    FINTS_ZUGAENGE {
        id bigint PK
        bezeichnung string
        bank_code string "BLZ"
        fints_url string
        benutzerkennung string
        pin text "encrypted"
        tan_verfahren string
    }
    BANKKONTEN {
        id bigint PK
        betrieb_id bigint FK
        fints_zugang_id bigint FK
        bezeichnung string
        iban string
        bic string
        saldo decimal
        fints_aktiv bool
    }
    IMPORT_PROTOKOLLE {
        id bigint PK
        bankkonto_id bigint FK
        quelle enum "fints|mt940|camt|csv|manuell"
        anzahl_neu int
        anzahl_dubletten int
        status string
    }
    BANKUMSAETZE {
        id bigint PK
        bankkonto_id bigint FK
        betrieb_id bigint FK
        kategorie_id bigint FK
        kostenstelle_id bigint FK
        lieferant_id bigint FK
        buchungsdatum date
        valutadatum date
        empfaenger string
        verwendungszweck text
        betrag decimal
        status enum "offen|teilweise|vollstaendig|geprueft"
        dedup_hash string "UNIQUE je Konto"
    }
    BELEGE {
        id bigint PK
        betrieb_id bigint FK
        lieferant_id bigint FK
        kategorie_id bigint FK
        kostenstelle_id bigint FK
        typ enum "rechnungseingang|rechnungsausgang|kasse|sonstige"
        rechnungsnummer string
        rechnungsdatum date
        betrag_brutto decimal
        steuerbetrag decimal
        datei_pfad string
        ocr_text longtext
        ocr_status enum
        status enum
    }
    BELEG_BANKUMSATZ {
        id bigint PK
        bankumsatz_id bigint FK
        beleg_id bigint FK
        betrag decimal "zugeordneter Anteil"
        zuordnungs_art enum
        trefferquote decimal
    }
    ZUORDNUNGS_REGELN {
        id bigint PK
        muster string "z.B. HBW"
        muster_typ enum "empfaenger|verwendungszweck|iban"
        lieferant_id bigint FK
        kategorie_id bigint FK
        kostenstelle_id bigint FK
        treffer_anzahl int "Lernzaehler"
        prioritaet int
    }
    KONTIERUNGEN {
        id bigint PK
        kontierbar_type string "polymorph"
        kontierbar_id bigint
        kontenrahmen enum "skr03|skr04"
        konto string
        gegenkonto string
        steuerschluessel string
        kostenstelle_id bigint FK
        datev_export_id bigint FK
    }
    DATEV_EXPORTE {
        id bigint PK
        betrieb_id bigint FK
        bezeichnung string
        von_datum date
        bis_datum date
        kontenrahmen enum
        berater_nummer string
        mandant_nummer string
    }
    KATEGORIEN {
        id bigint PK
        parent_id bigint FK
        name string
        skr03_konto string
        skr04_konto string
        steuerschluessel string
    }
    KOSTENSTELLEN {
        id bigint PK
        betrieb_id bigint FK
        nummer string "KOST1"
        name string
    }
    LIEFERANTEN {
        id bigint PK
        name string
        standard_kategorie_id bigint FK
        standard_kostenstelle_id bigint FK
        iban string
        kreditor_nummer string
    }
```

## Beziehungen in Kürze

| Beziehung | Typ | Bedeutung |
|-----------|-----|-----------|
| Bankumsatz ↔ Beleg | n:m (`beleg_bankumsatz`) | Ein Umsatz kann mehrere Belege enthalten; ein Beleg kann auf mehrere Umsätze aufgeteilt werden. Pivot-Feld `betrag` hält den Teilbetrag (Modul 5). |
| FinTS-Zugang → Bankkonten | 1:n | Ein Online-Banking-Login versorgt mehrere Konten. |
| Zuordnungsregel → Lieferant/Kategorie/Kostenstelle | n:1 | Lernfähige Auto-Zuordnung (Modul 4). |
| Kontierung → Bankumsatz/Beleg | polymorph | SKR03/04-Buchungsvorbereitung (Modul 13). |
| Bankumsatz/Beleg ↔ Kostenstelle | n:m (Pivot) | Vorbereitete Mehrfach-Kostenstellen-Verteilung (Modul 9). |
