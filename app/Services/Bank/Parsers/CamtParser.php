<?php

namespace App\Services\Bank\Parsers;

use SimpleXMLElement;

/**
 * Parser für CAMT.053-Kontoauszüge (ISO 20022 XML, Modul 1).
 *
 * Liest die <Ntry>-Buchungen mit Betrag, Soll/Haben-Kennung (CdtDbtInd),
 * Buchungs-/Valutadatum sowie – aus den Transaktionsdetails – Verwendungszweck,
 * Name und IBAN der Gegenseite. Die Namespace-Version (…053.001.xx) wird
 * automatisch erkannt.
 */
class CamtParser
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $content): array
    {
        $xml = new SimpleXMLElement($content);
        $ns = $xml->getDocNamespaces();
        $uri = $ns[''] ?? reset($ns) ?: 'urn:iso:std:iso:20022:tech:xsd:camt.053.001.02';
        $xml->registerXPathNamespace('c', $uri);

        $entries = $xml->xpath('//c:Ntry') ?: [];
        $transactions = [];

        foreach ($entries as $entry) {
            $entry->registerXPathNamespace('c', $uri);

            $indicator = (string) $this->first($entry, 'c:CdtDbtInd');
            $sign = $indicator === 'DBIT' ? -1 : 1;
            $amount = (float) $this->first($entry, 'c:Amt');
            $currency = (string) $this->firstAttr($entry, 'c:Amt', 'Ccy');

            $bookingDate = $this->first($entry, 'c:BookgDt/c:Dt') ?? $this->first($entry, 'c:BookgDt/c:DtTm');
            $valueDate = $this->first($entry, 'c:ValDt/c:Dt') ?? $this->first($entry, 'c:ValDt/c:DtTm');

            // Verwendungszweck (alle Ustrd zusammenfassen)
            $purposes = $entry->xpath('.//c:RmtInf/c:Ustrd') ?: [];
            $purpose = trim(implode(' ', array_map(fn ($u) => (string) $u, $purposes)));

            // Gegenseite: bei Lastschrift/Überweisung (DBIT) der Kreditor, sonst der Debitor
            $partyPath = $indicator === 'DBIT' ? 'Cdtr' : 'Dbtr';
            $name = $this->first($entry, ".//c:RltdPties/c:{$partyPath}/c:Nm");
            $iban = $this->first($entry, ".//c:RltdPties/c:{$partyPath}Acct/c:Id/c:IBAN");
            $bic = $this->first($entry, ".//c:RltdAgts/c:{$partyPath}Agt/c:FinInstnId/c:BICFI")
                ?? $this->first($entry, ".//c:RltdAgts/c:{$partyPath}Agt/c:FinInstnId/c:BIC");

            $reference = $this->first($entry, 'c:AcctSvcrRef')
                ?? $this->first($entry, './/c:Refs/c:EndToEndId');

            $bookingText = $this->first($entry, './/c:BkTxCd/c:Prtry/c:Cd');

            $transactions[] = [
                'booking_date' => $bookingDate ? substr((string) $bookingDate, 0, 10) : null,
                'value_date' => $valueDate ? substr((string) $valueDate, 0, 10) : null,
                'amount' => $sign * $amount,
                'currency' => $currency ?: null,
                'counterparty' => $name ? (string) $name : null,
                'counterparty_iban' => $iban ? (string) $iban : null,
                'counterparty_bic' => $bic ? (string) $bic : null,
                'purpose' => $purpose ?: null,
                'bank_reference' => $reference ? (string) $reference : null,
                'booking_text' => $bookingText ? (string) $bookingText : null,
            ];
        }

        return $transactions;
    }

    private function first(SimpleXMLElement $node, string $xpath): ?string
    {
        $result = $node->xpath($xpath);

        return $result && isset($result[0]) ? (string) $result[0] : null;
    }

    private function firstAttr(SimpleXMLElement $node, string $xpath, string $attr): ?string
    {
        $result = $node->xpath($xpath);
        if (! $result || ! isset($result[0])) {
            return null;
        }

        return (string) ($result[0]->attributes()[$attr] ?? '');
    }
}
