<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Tests;

use DateTimeImmutable;
use MacSoft\Facturacion\Contrato\Dto\Cliente;
use MacSoft\Facturacion\Contrato\Dto\Documento;
use MacSoft\Facturacion\Contrato\Dto\Item;
use MacSoft\Facturacion\Contrato\Dto\Pago;
use MacSoft\Facturacion\Contrato\Dto\Respuesta;
use MacSoft\Facturacion\Contrato\Dto\Venta;
use MacSoft\Facturacion\Contrato\Enum\EstadoComprobante;
use MacSoft\Facturacion\Contrato\Enum\Impuesto;
use MacSoft\Facturacion\Contrato\Enum\TipoComprobante;
use MacSoft\Facturacion\Contrato\Enum\TipoDocumento;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * La ida y vuelta es lo que hace fiable al paquete.
 *
 * Los DTOs viajan como JSON entre dos sistemas, y el fallo típico no es una
 * excepción: es un campo que se pierde por el camino sin que nadie se entere —un
 * `descuento_global` que llega como 0, un `precio_incluye_impuesto` que se cae al
 * default— y aparece semanas después como un descuadre de céntimos.
 *
 * `aArray()` omite los opcionales ausentes a propósito (§0: "omitir es preferible"),
 * así que la vuelta tiene que reconstruir exactamente el mismo objeto SIN esas
 * claves. Eso es justamente lo que se prueba aquí.
 */
final class SerializacionTest extends TestCase
{
    #[Test]
    public function una_venta_completa_sobrevive_a_la_ida_y_vuelta(): void
    {
        $venta = new Venta(
            idempotencyKey: 'venta-8123',
            referenciaExterna: 'T45-V0004',
            cliente: new Cliente(
                new Documento(TipoDocumento::RUC, '20601030013'),
                'ACME SAC',
                'Av. Siempre Viva 742 - Lima',
                'contacto@acme.pe',
            ),
            items: [
                new Item(
                    descripcion: 'Paracetamol 500mg caja x100',
                    cantidad: 10,
                    precioUnitario: 2.50,
                    codigo: 'MED-001',
                    unidad: 'caja',
                    impuesto: Impuesto::EXONERADO,
                    precioIncluyeImpuesto: false,
                    descuento: 1.50,
                ),
                new Item('Muestra gratuita', 1, 0.0, impuesto: Impuesto::GRATUITO),
            ],
            total: 23.50,
            tipo: TipoComprobante::FACTURA,
            fechaEmision: new DateTimeImmutable('2026-07-28 00:00:00'),
            moneda: 'PEN',
            serie: 'F002',
            descuentoGlobal: 2.00,
            pago: Pago::credito(new DateTimeImmutable('2026-08-27 00:00:00'), 50.0),
            observaciones: 'Entrega en almacén central',
        );

        $vuelta = Venta::desdeArray($venta->aArray());

        self::assertEquals($venta, $vuelta);
        self::assertSame($venta->aArray(), $vuelta->aArray());
    }

    /**
     * Lo mínimo que admite el contrato: cinco campos.
     *
     * Aquí se comprueba lo contrario que arriba — que los opcionales AUSENTES sigan
     * ausentes y no se materialicen como ceros y cadenas vacías en el JSON.
     */
    #[Test]
    public function una_venta_minima_sobrevive_a_la_ida_y_vuelta(): void
    {
        $venta = new Venta(
            'venta-9000',
            'T45-V0005',
            Cliente::generico(),
            [new Item('Paracetamol 500mg', 1, 2.50)],
            2.50,
        );

        $array = $venta->aArray();
        $vuelta = Venta::desdeArray($array);

        self::assertEquals($venta, $vuelta);
        self::assertSame($array, $vuelta->aArray());

        foreach (['fecha_emision', 'serie', 'pago', 'observaciones', 'descuento_global'] as $ausente) {
            self::assertArrayNotHasKey($ausente, $array, "«{$ausente}» no debería viajar si nadie lo puso.");
        }

        // Y el cliente genérico viaja sin número de documento.
        self::assertSame(['tipo' => 'SIN_DOCUMENTO'], $array['cliente']['documento']);
    }

    #[Test]
    public function el_json_del_contrato_usa_snake_case(): void
    {
        $venta = new Venta(
            'venta-9001',
            'T45-V0006',
            Cliente::generico(),
            [new Item('Paracetamol 500mg', 1, 2.50)],
            2.50,
        );

        $json = json_decode(json_encode($venta->aArray(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('idempotency_key', $json);
        self::assertArrayHasKey('referencia_externa', $json);
        self::assertArrayHasKey('precio_incluye_impuesto', $json['items'][0]);
        self::assertArrayHasKey('precio_unitario', $json['items'][0]);

        // Nada de camelCase colándose desde las propiedades PHP.
        self::assertArrayNotHasKey('idempotencyKey', $json);
        self::assertArrayNotHasKey('referenciaExterna', $json);
    }

    /** La respuesta también viaja de vuelta: el POS la guarda y la vuelve a leer. */
    #[Test]
    public function una_respuesta_completa_sobrevive_a_la_ida_y_vuelta(): void
    {
        $respuesta = Respuesta::desdeArray([
            'id'          => 812,
            'estado'      => 'enviando',
            'modo'        => 'produccion',
            'comprobante' => [
                'tipo'            => 'factura',
                'serie'           => 'F002',
                'numero'          => 123,
                'numero_completo' => 'F002-00000123',
                'fecha_emision'   => '2026-07-28',
                'hash'            => null,
                'qr'              => '20614911051|01|F002|00000123|11.44|75.00|2026-07-28|6|20601030013',
                'enlaces'         => ['pdf' => 'https://facturamac.test/api/v1/ventas/812/pdf'],
            ],
            'totales' => [
                'gravado' => 63.56, 'exonerado' => 0.0, 'inafecto' => 0.0, 'gratuito' => 0.0,
                'descuento' => 0.0, 'impuesto' => 11.44, 'total' => 75.0,
            ],
            'avisos' => [
                ['codigo' => 'unidad_no_mapeada', 'mensaje' => "La unidad 'bidón' no está mapeada; se usó NIU.", 'item' => 2],
            ],
            'referencia_externa' => 'T45-V0004',
        ]);

        self::assertEquals($respuesta, Respuesta::desdeArray($respuesta->aArray()));

        self::assertSame(EstadoComprobante::ENVIANDO, $respuesta->estado);
        self::assertTrue($respuesta->esProduccion());
        self::assertTrue($respuesta->tieneAvisos());
        // `serie`, `numero` y `qr` llegan de inmediato: es lo que imprime el ticket.
        self::assertSame('F002-00000123', $respuesta->comprobante?->numeroCompleto);
        self::assertNull($respuesta->comprobante?->hash);
    }

    /**
     * §4.1 del contrato pone `enlaces` en la raíz de la respuesta; el DTO los guarda
     * con el comprobante. Leer solo uno de los dos sitios hace desaparecer en silencio
     * el enlace al PDF, que es lo que la caja necesita para reimprimir.
     */
    #[Test]
    public function los_enlaces_se_leen_tanto_en_la_raiz_como_dentro_del_comprobante(): void
    {
        $enlaces = ['pdf' => 'https://facturamac.test/api/v1/ventas/812/pdf'];

        $enLaRaiz = Respuesta::desdeArray([
            'id'          => 812,
            'estado'      => 'aceptado',
            'modo'        => 'produccion',
            'comprobante' => ['tipo' => 'boleta', 'serie' => 'B002', 'numero' => 7, 'numero_completo' => 'B002-00000007'],
            'enlaces'     => $enlaces,
        ]);

        self::assertSame($enlaces, $enLaRaiz->comprobante?->enlaces);
        self::assertSame($enlaces, $enLaRaiz->aArray()['enlaces']);
        self::assertEquals($enLaRaiz, Respuesta::desdeArray($enLaRaiz->aArray()));
    }

    /**
     * Emisión desactivada NO es un error: es la configuración de la empresa.
     *
     * El sistema origen registra su venta y no reintenta. Tratarlo como fallo llenaría
     * la cola de reintentos que nunca van a funcionar.
     */
    #[Test]
    public function la_emision_desactivada_se_deserializa_como_respuesta_valida(): void
    {
        $respuesta = Respuesta::desdeArray([
            'emitido' => false,
            'modo'    => 'desactivado',
            'motivo'  => 'La emisión electrónica está desactivada para esta empresa.',
        ]);

        self::assertFalse($respuesta->emitido);
        self::assertNull($respuesta->comprobante);
        self::assertFalse($respuesta->esProduccion());
        self::assertEquals($respuesta, Respuesta::desdeArray($respuesta->aArray()));
    }
}
