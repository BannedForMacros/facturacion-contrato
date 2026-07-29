<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Tests\Dto;

use MacSoft\Facturacion\Contrato\Dto\Cliente;
use MacSoft\Facturacion\Contrato\Dto\Documento;
use MacSoft\Facturacion\Contrato\Dto\Item;
use MacSoft\Facturacion\Contrato\Dto\Venta;
use MacSoft\Facturacion\Contrato\Enum\Impuesto;
use MacSoft\Facturacion\Contrato\Enum\TipoComprobante;
use MacSoft\Facturacion\Contrato\Enum\TipoDocumento;
use MacSoft\Facturacion\Contrato\Excepcion\ContratoInvalidoException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * La venta valida lo que NO depende de la configuración de la empresa: coherencia
 * interna y reglas legales invariantes. El umbral de boleta, la tasa vigente y la
 * tolerancia de redondeo los aplica el emisor, que es quien los conoce.
 */
final class VentaTest extends TestCase
{
    private static function clienteEmpresa(): Cliente
    {
        return new Cliente(
            new Documento(TipoDocumento::RUC, '20601030013'),
            'ACME SAC',
            'Av. Siempre Viva 742 - Lima',
        );
    }

    private static function item(float $cantidad = 10, float $precio = 2.50): Item
    {
        return new Item('Paracetamol 500mg caja x100', $cantidad, $precio, codigo: 'MED-001');
    }

    #[Test]
    public function rechaza_una_venta_sin_lineas(): void
    {
        $this->expectException(ContratoInvalidoException::class);

        new Venta('venta-1', 'T45-V0004', self::clienteEmpresa(), [], 25.0);
    }

    /** Sin clave de idempotencia, una red inestable emite dos comprobantes por una venta. */
    #[Test]
    public function rechaza_una_venta_sin_clave_de_idempotencia(): void
    {
        try {
            new Venta('  ', 'T45-V0004', self::clienteEmpresa(), [self::item()], 25.0);
            self::fail('Debería haber lanzado.');
        } catch (ContratoInvalidoException $e) {
            self::assertSame('idempotency_key', $e->campo);
        }
    }

    /**
     * Sin referencia externa no hay forma de responder, meses después, a "¿de qué
     * venta salió esta factura?" ante una revisión de SUNAT.
     */
    #[Test]
    public function rechaza_una_venta_sin_referencia_externa(): void
    {
        try {
            new Venta('venta-1', '', self::clienteEmpresa(), [self::item()], 25.0);
            self::fail('Debería haber lanzado.');
        } catch (ContratoInvalidoException $e) {
            self::assertSame('referencia_externa', $e->campo);
        }
    }

    /** SUNAT rechaza SIEMPRE una factura cuyo adquirente no tiene RUC. Se caza antes de numerar. */
    #[Test]
    public function una_factura_con_cliente_de_dni_no_se_construye(): void
    {
        $cliente = new Cliente(
            new Documento(TipoDocumento::DNI, '46812345'),
            'Juan Pérez',
            'Jr. Lima 100',
        );

        try {
            new Venta('venta-2', 'T45-V0005', $cliente, [self::item()], 25.0, TipoComprobante::FACTURA);
            self::fail('Debería haber lanzado.');
        } catch (ContratoInvalidoException $e) {
            self::assertSame('cliente', $e->campo);
            self::assertStringContainsString('RUC', $e->getMessage());
        }
    }

    #[Test]
    public function una_factura_sin_direccion_fiscal_no_se_construye(): void
    {
        $cliente = new Cliente(new Documento(TipoDocumento::RUC, '20601030013'), 'ACME SAC');

        try {
            new Venta('venta-3', 'T45-V0006', $cliente, [self::item()], 25.0);
            self::fail('Debería haber lanzado.');
        } catch (ContratoInvalidoException $e) {
            self::assertSame('cliente', $e->campo);
            self::assertStringContainsString('dirección', $e->getMessage());
        }
    }

    /** El caso más común del mostrador: cliente varios, boleta, sin documento. */
    #[Test]
    public function una_boleta_a_cliente_varios_se_construye(): void
    {
        $venta = new Venta(
            'venta-4',
            'T45-V0007',
            Cliente::generico(),
            [self::item()],
            25.0,
        );

        self::assertSame(TipoComprobante::BOLETA, $venta->tipoResuelto());
        self::assertSame('CLIENTE VARIOS', $venta->cliente->nombre);
    }

    #[Test]
    public function resuelve_el_tipo_segun_el_documento_del_adquirente(): void
    {
        $factura = new Venta('v-5', 'T45-V0008', self::clienteEmpresa(), [self::item()], 25.0);
        self::assertSame(TipoComprobante::FACTURA, $factura->tipoResuelto());

        // Forzar boleta a un cliente con RUC es legal: se respeta lo que pidió el origen.
        $boleta = new Venta(
            'v-6',
            'T45-V0009',
            self::clienteEmpresa(),
            [self::item()],
            25.0,
            TipoComprobante::BOLETA,
        );
        self::assertSame(TipoComprobante::BOLETA, $boleta->tipoResuelto());
    }

    /**
     * Lo gratuito se declara pero no se cobra.
     *
     * Si entrara en `importeItems()`, la comparación contra el `total` declarado
     * fallaría y la venta no se emitiría, con la caja ya cobrada.
     */
    #[Test]
    public function el_importe_de_las_lineas_excluye_lo_gratuito(): void
    {
        $venta = new Venta(
            'v-7',
            'T45-V0010',
            Cliente::generico(),
            [
                self::item(10, 2.50),                                                  // 25.00
                new Item('Muestra gratuita', 2, 1.00, impuesto: Impuesto::GRATUITO),   // no cuenta
                new Item('Jarabe 120ml', 1, 8.00, impuesto: Impuesto::EXONERADO),      //  8.00
            ],
            33.0,
        );

        self::assertSame(33.0, $venta->importeItems());
    }

    #[Test]
    public function el_importe_de_las_lineas_descuenta_los_descuentos_por_linea(): void
    {
        $venta = new Venta(
            'v-8',
            'T45-V0011',
            Cliente::generico(),
            [new Item('Paracetamol', 10, 2.50, descuento: 5.0)],
            20.0,
        );

        self::assertSame(20.0, $venta->importeItems());
    }

    #[Test]
    public function rechaza_un_total_no_positivo(): void
    {
        try {
            new Venta('v-9', 'T45-V0012', Cliente::generico(), [self::item()], 0.0);
            self::fail('Debería haber lanzado.');
        } catch (ContratoInvalidoException $e) {
            self::assertSame('total', $e->campo);
        }
    }

    #[Test]
    public function rechaza_un_descuento_global_negativo(): void
    {
        $this->expectException(ContratoInvalidoException::class);

        new Venta(
            'v-10',
            'T45-V0013',
            Cliente::generico(),
            [self::item()],
            25.0,
            descuentoGlobal: -1.0,
        );
    }

    #[Test]
    public function rechaza_una_lista_de_items_que_no_son_items(): void
    {
        $this->expectException(ContratoInvalidoException::class);

        /** @phpstan-ignore-next-line */
        new Venta('v-11', 'T45-V0014', Cliente::generico(), [['descripcion' => 'suelto']], 25.0);
    }

    /** Lo inválido sale SIEMPRE como excepción del contrato, nunca como TypeError. */
    #[Test]
    public function una_linea_que_no_es_un_objeto_lanza_la_excepcion_del_contrato(): void
    {
        try {
            Venta::desdeArray([
                'idempotency_key'    => 'v-13',
                'referencia_externa' => 'T45-V0016',
                'cliente'            => ['documento' => ['tipo' => 'SIN_DOCUMENTO'], 'nombre' => 'CLIENTE VARIOS'],
                'items'              => ['Paracetamol'],
                'total'              => 25.0,
            ]);
            self::fail('Debería haber lanzado.');
        } catch (ContratoInvalidoException $e) {
            self::assertSame('items.0', $e->campo);
        }
    }

    #[Test]
    public function los_defaults_de_la_cabecera_son_los_del_contrato(): void
    {
        $venta = new Venta('v-12', 'T45-V0015', Cliente::generico(), [self::item()], 25.0);

        self::assertSame(TipoComprobante::AUTO, $venta->tipo);
        self::assertSame('PEN', $venta->moneda);
        self::assertNull($venta->serie);
        self::assertNull($venta->fechaEmision);
        self::assertNull($venta->pago);
        self::assertSame(0.0, $venta->descuentoGlobal);
    }
}
