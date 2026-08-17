<?php

use App\Models\Comprobante;
use App\Models\Moneda;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('preview xml parsea factura ubl y sugiere compra por company ruc receptor', function () {
    test()->seed(RoleSeeder::class);
    config(['app.company_ruc' => '20600000001']);
    $usd = Moneda::create(['codigo' => 'USD', 'simbolo' => '$']);

    $contabilidad = User::factory()->create();
    $contabilidad->assignRole('contabilidad');

    Sanctum::actingAs($contabilidad);

    $file = UploadedFile::fake()->createWithContent(
        'factura.xml',
        xmlFacturaSunatFase9('20123456789', '20600000001', 'USD')
    );

    $this->postJson('/api/contabilidad/comprobantes/preview-xml', [
        'xml' => $file,
    ])
        ->assertOk()
        ->assertJsonPath('tipo_operacion_sugerida', Comprobante::TIPO_OPERACION_COMPRA)
        ->assertJsonPath('serie', 'F001')
        ->assertJsonPath('numero', '123')
        ->assertJsonPath('emisor_ruc', '20123456789')
        ->assertJsonPath('receptor_ruc', '20600000001')
        ->assertJsonPath('moneda_codigo', 'USD')
        ->assertJsonPath('moneda_id', $usd->id)
        ->assertJsonPath('items.0.descripcion', 'Laptop de prueba')
        ->assertJsonPath('duplicado.existe', false);

    expect(Comprobante::query()->count())->toBe(0);
});

test('preview xml sugiere venta cuando company ruc es emisor y detecta duplicado', function () {
    test()->seed(RoleSeeder::class);
    config(['app.company_ruc' => '20600000001']);

    Comprobante::create([
        'tipo_operacion' => Comprobante::TIPO_OPERACION_VENTA,
        'emisor_ruc' => '20600000001',
        'receptor_ruc' => '20123456789',
        'tipo_comprobante' => '01',
        'serie' => 'F001',
        'numero' => '123',
        'total' => 118,
        'estado' => Comprobante::ESTADO_REGISTRADO,
    ]);

    $contabilidad = User::factory()->create();
    $contabilidad->assignRole('contabilidad');

    Sanctum::actingAs($contabilidad);

    $file = UploadedFile::fake()->createWithContent(
        'factura.xml',
        xmlFacturaSunatFase9('20600000001', '20123456789')
    );

    $this->postJson('/api/contabilidad/comprobantes/preview-xml', [
        'xml' => $file,
    ])
        ->assertOk()
        ->assertJsonPath('tipo_operacion_sugerida', Comprobante::TIPO_OPERACION_VENTA)
        ->assertJsonPath('duplicado.existe', true)
        ->assertJsonPath('duplicado.por_serie_numero', true);
});

test('ventas no puede previsualizar xml contable', function () {
    test()->seed(RoleSeeder::class);

    $ventas = User::factory()->create();
    $ventas->assignRole('ventas');

    Sanctum::actingAs($ventas);

    $file = UploadedFile::fake()->createWithContent(
        'factura.xml',
        xmlFacturaSunatFase9('20600000001', '20123456789')
    );

    $this->postJson('/api/contabilidad/comprobantes/preview-xml', [
        'xml' => $file,
    ])->assertForbidden();
});

function xmlFacturaSunatFase9(string $emisorRuc, string $receptorRuc, string $moneda = 'PEN'): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
    <cbc:ID>F001-123</cbc:ID>
    <cbc:IssueDate>2026-08-13</cbc:IssueDate>
    <cbc:DueDate>2026-09-13</cbc:DueDate>
    <cbc:InvoiceTypeCode>01</cbc:InvoiceTypeCode>
    <cbc:DocumentCurrencyCode>{$moneda}</cbc:DocumentCurrencyCode>
    <cac:AccountingSupplierParty>
        <cac:Party>
            <cac:PartyIdentification>
                <cbc:ID>{$emisorRuc}</cbc:ID>
            </cac:PartyIdentification>
            <cac:PartyLegalEntity>
                <cbc:RegistrationName>Proveedor XML</cbc:RegistrationName>
            </cac:PartyLegalEntity>
        </cac:Party>
    </cac:AccountingSupplierParty>
    <cac:AccountingCustomerParty>
        <cac:Party>
            <cac:PartyIdentification>
                <cbc:ID>{$receptorRuc}</cbc:ID>
            </cac:PartyIdentification>
            <cac:PartyLegalEntity>
                <cbc:RegistrationName>Cliente XML</cbc:RegistrationName>
            </cac:PartyLegalEntity>
        </cac:Party>
    </cac:AccountingCustomerParty>
    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="{$moneda}">18.00</cbc:TaxAmount>
    </cac:TaxTotal>
    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="{$moneda}">100.00</cbc:LineExtensionAmount>
        <cbc:PayableAmount currencyID="{$moneda}">118.00</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
    <cac:InvoiceLine>
        <cbc:InvoicedQuantity unitCode="NIU">1</cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount currencyID="{$moneda}">100.00</cbc:LineExtensionAmount>
        <cac:PricingReference>
            <cac:AlternativeConditionPrice>
                <cbc:PriceAmount currencyID="{$moneda}">118.00</cbc:PriceAmount>
            </cac:AlternativeConditionPrice>
        </cac:PricingReference>
        <cac:TaxTotal>
            <cbc:TaxAmount currencyID="{$moneda}">18.00</cbc:TaxAmount>
        </cac:TaxTotal>
        <cac:Item>
            <cbc:Description>Laptop de prueba</cbc:Description>
        </cac:Item>
        <cac:Price>
            <cbc:PriceAmount currencyID="{$moneda}">100.00</cbc:PriceAmount>
        </cac:Price>
    </cac:InvoiceLine>
</Invoice>
XML;
}
