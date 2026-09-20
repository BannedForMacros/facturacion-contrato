<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Tests\Dto;

use MacSoft\Facturacion\Contrato\Dto\Guia;
use MacSoft\Facturacion\Contrato\Excepcion\ContratoInvalidoException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * La guía se prueba desde `desdeArray()` porque es como llega de verdad: un JSON
 * que manda el POS. Los casos de aquí son los que ocurren en un almacén cualquiera
 * —vender, mover entre locales, facturar a uno y entregar a otro— y cada uno pide
 * partes distintas.
 */
final class GuiaTest extends TestCase
{
    /**
     * Payload de una guía corriente, con los retoques que pida cada prueba.
     *
     * OJO con la mezcla: `traslado` se funde en profundidad —para cambiar solo el
     * motivo sin repetir el bloque entero— y el resto se reemplaza tal cual. Hacerlo
     * todo recursivo sería una trampa: pedir `items => []` dejaría el ítem de siempre
     * en su sitio y la prueba pasaría sin probar nada.
     *
     * @return array<string,mixed>
     */
    private static function payload(array $cambios = []): array
    {
        $base = [
            'idempotency_key'    => 'guia-despacho-77',
            'referencia_externa' => 'DESP-000077',
            'traslado'           => [
                'motivo'       => 'VENTA',
                'modalidad'    => 'PRIVADO',
                'fecha_inicio' => '2026-09-19',
                'peso_total'   => 250.0,
                'partida'      => ['ubigeo' => '140101', 'direccion' => 'Av. Bolognesi 1200'],
                'llegada'      => ['ubigeo' => '150101', 'direccion' => 'Jr. de la Unión 500'],
                'vehiculo'     => ['placa' => 'ABC-123'],
                'conductores'  => [[
                    'numero_documento' => '45678912',
                    'nombres'          => 'Luis',
                    'apellidos'        => 'Ramírez',
                    'licencia'         => 'Q45678912',
                ]],
            ],
            'items'        => [
                ['descripcion' => 'Cemento Pacasmayo 42.5 kg', 'cantidad' => 5, 'unidad' => 'BG', 'peso' => 42.5],
            ],
            'destinatario' => [
                'documento' => ['tipo' => 'RUC', 'numero' => '20612690759'],
                'nombre'    => 'POLLO LOCO CIX EIRL',
            ],
        ];

        if (isset($cambios['traslado'])) {
            $base['traslado'] = array_replace_recursive($base['traslado'], $cambios['traslado']);
            unset($cambios['traslado']);
        }

        return array_replace($base, $cambios);
    }

    #[Test]
    public function acepta_la_guia_de_una_venta(): void
    {
        $guia = Guia::desdeArray(self::payload());

        self::assertSame('POLLO LOCO CIX EIRL', $guia->destinatario?->nombre);
        self::assertSame(212.5, $guia->pesoSugerido());
    }

    /** Sin peso en alguna línea no se puede proponer nada, y es mejor no inventarlo. */
    #[Test]
    public function no_sugiere_peso_si_alguna_linea_no_lo_trae(): void
    {
        $guia = Guia::desdeArray(self::payload([
            'items' => [['descripcion' => 'Arena fina', 'cantidad' => 2, 'unidad' => 'MTQ']],
        ]));

        self::assertNull($guia->pesoSugerido());
    }

    #[Test]
    public function rechaza_una_venta_sin_destinatario(): void
    {
        $payload = self::payload();
        unset($payload['destinatario']);

        try {
            Guia::desdeArray($payload);
            self::fail('Debería haber lanzado.');
        } catch (ContratoInvalidoException $e) {
            self::assertSame('destinatario', $e->campo);
        }
    }

    /** Mover entre locales propios no lleva cliente: el destinatario es la empresa. */
    #[Test]
    public function acepta_el_traslado_entre_locales_propios_sin_destinatario(): void
    {
        $payload = self::payload([
            'traslado' => [
                'motivo'  => 'TRASLADO_ENTRE_ESTABLECIMIENTOS',
                'partida' => ['codigo_establecimiento' => '0000'],
                'llegada' => ['codigo_establecimiento' => '0002'],
            ],
        ]);
        unset($payload['destinatario']);

        $guia = Guia::desdeArray($payload);

        self::assertNull($guia->destinatario);
        self::assertSame('0002', $guia->traslado->llegada->codigoEstablecimiento);
    }

    /**
     * Un destinatario aquí suele ser el cliente de la venta anterior, copiado por
     * descuido. SUNAT lo devuelve como error 2554 cuando ya no sirve de nada.
     */
    #[Test]
    public function rechaza_un_destinatario_en_el_traslado_entre_locales_propios(): void
    {
        try {
            Guia::desdeArray(self::payload([
                'traslado' => [
                    'motivo'  => 'TRASLADO_ENTRE_ESTABLECIMIENTOS',
                    'partida' => ['codigo_establecimiento' => '0000'],
                    'llegada' => ['codigo_establecimiento' => '0002'],
                ],
            ]));
            self::fail('Debería haber lanzado.');
        } catch (ContratoInvalidoException $e) {
            self::assertSame('destinatario', $e->campo);
        }
    }

    #[Test]
    public function la_venta_con_entrega_a_terceros_exige_comprador_aparte(): void
    {
        $conTerceros = self::payload(['traslado' => ['motivo' => 'VENTA_ENTREGA_TERCEROS']]);

        try {
            Guia::desdeArray($conTerceros);
            self::fail('Debería haber lanzado.');
        } catch (ContratoInvalidoException $e) {
            self::assertSame('comprador', $e->campo);
        }

        $conTerceros['comprador'] = [
            'documento' => ['tipo' => 'RUC', 'numero' => '20614911051'],
            'nombre'    => 'MACSOFT EIRL',
        ];

        self::assertSame('MACSOFT EIRL', Guia::desdeArray($conTerceros)->comprador?->nombre);
    }

    #[Test]
    public function rechaza_un_comprador_cuando_quien_recibe_es_quien_compra(): void
    {
        try {
            Guia::desdeArray(self::payload([
                'comprador' => [
                    'documento' => ['tipo' => 'RUC', 'numero' => '20614911051'],
                    'nombre'    => 'MACSOFT EIRL',
                ],
            ]));
            self::fail('Debería haber lanzado.');
        } catch (ContratoInvalidoException $e) {
            self::assertSame('comprador', $e->campo);
        }
    }

    /** Una salida de camión puede amparar varias facturas del mismo cliente. */
    #[Test]
    public function admite_varios_comprobantes_relacionados(): void
    {
        $guia = Guia::desdeArray(self::payload([
            'comprobantes' => [
                ['tipo' => 'factura', 'serie' => 'F001', 'numero' => 4],
                ['tipo' => 'factura', 'serie' => 'F001', 'numero' => 5],
            ],
        ]));

        self::assertSame(
            ['F001-00000004', 'F001-00000005'],
            array_map(static fn ($c) => $c->numeroCompleto(), $guia->comprobantes),
        );
    }

    /**
     * El caso que la primera versión del contrato daba por bueno y SUNAT rechaza:
     * una compra dirigida a un tercero. La mercadería viene hacia la empresa.
     */
    #[Test]
    public function rechaza_una_compra_dirigida_a_un_tercero(): void
    {
        try {
            Guia::desdeArray(self::payload(['traslado' => ['motivo' => 'COMPRA', 'modalidad' => 'PRIVADO']]));
            self::fail('Debería haber lanzado.');
        } catch (ContratoInvalidoException $e) {
            self::assertSame('destinatario', $e->campo);
            self::assertStringContainsString('2554', $e->getMessage());
        }
    }

    /** La misma compra, sin destinatario, sí vale: la recibe la propia empresa. */
    #[Test]
    public function acepta_una_compra_sin_destinatario(): void
    {
        $payload = self::payload(['traslado' => ['motivo' => 'COMPRA']]);
        unset($payload['destinatario']);

        self::assertNull(Guia::desdeArray($payload)->destinatario);
    }

    /** El vendedor ambulante puede salir con o sin comprador conocido. */
    #[Test]
    public function el_emisor_itinerante_admite_las_dos_formas(): void
    {
        $conCliente = self::payload(['traslado' => ['motivo' => 'EMISOR_ITINERANTE']]);
        $sinCliente = $conCliente;
        unset($sinCliente['destinatario']);

        self::assertNotNull(Guia::desdeArray($conCliente)->destinatario);
        self::assertNull(Guia::desdeArray($sinCliente)->destinatario);
    }

    #[Test]
    public function rechaza_una_guia_sin_lineas(): void
    {
        try {
            Guia::desdeArray(self::payload(['items' => []]));
            self::fail('Debería haber lanzado.');
        } catch (ContratoInvalidoException $e) {
            self::assertSame('items', $e->campo);
        }
    }

    /** Sin clave de idempotencia, un reintento de red emite dos guías. */
    #[Test]
    public function rechaza_una_guia_sin_clave_de_idempotencia(): void
    {
        try {
            Guia::desdeArray(self::payload(['idempotency_key' => '   ']));
            self::fail('Debería haber lanzado.');
        } catch (ContratoInvalidoException $e) {
            self::assertSame('idempotency_key', $e->campo);
        }
    }

    #[Test]
    public function sobrevive_a_la_ida_y_vuelta(): void
    {
        $guia = Guia::desdeArray(self::payload([
            'comprobantes'  => [['tipo' => 'factura', 'serie' => 'F001', 'numero' => 4]],
            'observaciones' => 'Entrega en obra, preguntar por el maestro.',
        ]));

        self::assertSame($guia->aArray(), Guia::desdeArray($guia->aArray())->aArray());
    }
}
