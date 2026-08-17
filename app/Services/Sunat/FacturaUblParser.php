<?php

namespace App\Services\Sunat;

use DOMDocument;
use DOMXPath;
use Illuminate\Validation\ValidationException;

class FacturaUblParser
{
    public function parse(string $xml): array
    {
        $document = new DOMDocument;

        libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml);

        if (! $loaded) {
            throw ValidationException::withMessages([
                'xml' => 'El archivo no contiene un XML valido.',
            ]);
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

        $id = $this->text($xpath, '/*[local-name()="Invoice" or local-name()="CreditNote" or local-name()="DebitNote"]/cbc:ID') ?? '';
        [$serie, $numero] = $this->separarSerieNumero($id);

        return [
            'tipo_comprobante' => $this->tipoComprobante($xpath),
            'serie' => $serie,
            'numero' => $numero,
            'fecha_emision' => $this->text($xpath, '/*[local-name()="Invoice" or local-name()="CreditNote" or local-name()="DebitNote"]/cbc:IssueDate'),
            'fecha_vencimiento' => $this->text($xpath, '/*[local-name()="Invoice" or local-name()="CreditNote" or local-name()="DebitNote"]/cbc:DueDate'),
            'moneda_codigo' => $this->text($xpath, '/*[local-name()="Invoice" or local-name()="CreditNote" or local-name()="DebitNote"]/cbc:DocumentCurrencyCode'),
            'emisor_ruc' => $this->text($xpath, '//*[local-name()="AccountingSupplierParty"]//*[local-name()="PartyIdentification"]/cbc:ID'),
            'emisor_nombre' => $this->text($xpath, '//*[local-name()="AccountingSupplierParty"]//*[local-name()="PartyLegalEntity"]/cbc:RegistrationName')
                ?? $this->text($xpath, '//*[local-name()="AccountingSupplierParty"]//*[local-name()="PartyName"]/cbc:Name'),
            'receptor_ruc' => $this->text($xpath, '//*[local-name()="AccountingCustomerParty"]//*[local-name()="PartyIdentification"]/cbc:ID'),
            'receptor_nombre' => $this->text($xpath, '//*[local-name()="AccountingCustomerParty"]//*[local-name()="PartyLegalEntity"]/cbc:RegistrationName')
                ?? $this->text($xpath, '//*[local-name()="AccountingCustomerParty"]//*[local-name()="PartyName"]/cbc:Name'),
            'subtotal' => (float) ($this->text($xpath, '//*[local-name()="LegalMonetaryTotal"]/cbc:LineExtensionAmount') ?? 0),
            'igv' => (float) ($this->text($xpath, '//*[local-name()="TaxTotal"]/cbc:TaxAmount') ?? 0),
            'total' => (float) ($this->text($xpath, '//*[local-name()="LegalMonetaryTotal"]/cbc:PayableAmount') ?? 0),
            'items' => $this->items($xpath),
        ];
    }

    private function tipoComprobante(DOMXPath $xpath): string
    {
        $root = $xpath->query('/*')?->item(0);

        return $this->text($xpath, '/*[local-name()="Invoice"]/cbc:InvoiceTypeCode')
            ?? (strtolower((string) $root?->localName) ?: 'factura');
    }

    private function items(DOMXPath $xpath): array
    {
        $nodes = $xpath->query('//*[local-name()="InvoiceLine" or local-name()="CreditNoteLine" or local-name()="DebitNoteLine"]');
        $items = [];

        foreach ($nodes ?: [] as $node) {
            $cantidad = $this->textRelative($xpath, $node, './/*[local-name()="InvoicedQuantity" or local-name()="CreditedQuantity" or local-name()="DebitedQuantity"]');
            $subtotal = $this->textRelative($xpath, $node, './/cbc:LineExtensionAmount');
            $precio = $this->textRelative($xpath, $node, './/*[local-name()="Price"]/cbc:PriceAmount');
            $igv = $this->textRelative($xpath, $node, './/*[local-name()="TaxTotal"]/cbc:TaxAmount');

            $items[] = [
                'descripcion' => $this->textRelative($xpath, $node, './/*[local-name()="Item"]/cbc:Description') ?? 'Item sin descripcion',
                'cantidad' => (float) ($cantidad ?? 1),
                'valor_unitario' => (float) ($precio ?? 0),
                'subtotal' => (float) ($subtotal ?? 0),
                'igv' => (float) ($igv ?? 0),
                'total' => round((float) ($subtotal ?? 0) + (float) ($igv ?? 0), 2),
            ];
        }

        return $items;
    }

    private function text(DOMXPath $xpath, string $query): ?string
    {
        $node = $xpath->query($query)?->item(0);

        return $node ? trim($node->textContent) : null;
    }

    private function textRelative(DOMXPath $xpath, mixed $context, string $query): ?string
    {
        $node = $xpath->query($query, $context)?->item(0);

        return $node ? trim($node->textContent) : null;
    }

    private function separarSerieNumero(string $id): array
    {
        if (str_contains($id, '-')) {
            [$serie, $numero] = explode('-', $id, 2);

            return [$serie, $numero];
        }

        return [$id, ''];
    }
}
