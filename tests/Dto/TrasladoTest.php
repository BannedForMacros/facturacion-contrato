<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Tests\Dto;

use DateTimeImmutable;
use MacSoft\Facturacion\Contrato\Dto\Conductor;
use MacSoft\Facturacion\Contrato\Dto\Traslado;
use MacSoft\Facturacion\Contrato\Dto\Transportista;
use MacSoft\Facturacion\Contrato\Dto\Ubicacion;
use MacSoft\Facturacion\Contrato\Dto\Vehiculo;
use MacSoft\Facturacion\Contrato\Enum\ModalidadTraslado;
use MacSoft\Facturacion\Contrato\Enum\MotivoTraslado;
use MacSoft\Facturacion\Contrato\Enum\TipoDocumento;
use MacSoft\Facturacion\Contrato\Excepcion\ContratoInvalidoException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * El bloque de transporte es donde conviven la camioneta propia y el flete
 * contratado, y donde más caro sale equivocarse: un rechazo de SUNAT por datos
 * incoherentes llega cuando el vehículo ya salió.
 */
final class TrasladoTest extends TestCase
{
    private static function partida(?string $codigo = null): Ubicacion
    {
        return new Ubicacion('140101', 'Av. Bolognesi 1200 - Chiclayo', $codigo);
    }

    private static function llegada(?string $codigo = null): Ubicacion
    {
        return new Ubicacion('150101', 'Jr. de la Unión 500 - Lima', $codigo);
    }

    private static function conductor(bool $principal = true, string $doc = '45678912'): Conductor
    {
        return new Conductor(TipoDocumento::DNI, $doc, 'Luis', 'Ramírez', 'Q45678912', $principal);
    }

    /** @param array<string,mixed> $cambios */
    private static function privado(array $cambios = []): Traslado
    {
        return new Traslado(...array_merge([
            'motivo'       => MotivoTraslado::VENTA,
            'modalidad'    => ModalidadTraslado::PRIVADO,
            'fechaInicio'  => new DateTimeImmutable('2026-09-19'),
            'partida'      => self::partida(),
            'llegada'      => self::llegada(),
            'pesoTotal'    => 250.0,
            'vehiculo'     => new Vehiculo('ABC-123'),
            'conductores'  => [self::conductor()],
        ], $cambios));
    }

    #[Test]
    public function acepta_el_traslado_en_vehiculo_propio(): void
    {
        $traslado = self::privado();

        self::assertTrue($traslado->declaraVehiculo());
        self::assertSame('ABC123', $traslado->vehiculo?->placaNormalizada());
        self::assertSame('Luis Ramírez', $traslado->conductorPrincipal()?->nombreCompleto());
    }

    #[Test]
    public function acepta_el_flete_contratado_sin_placa_ni_chofer(): void
    {
        $traslado = self::privado([
            'modalidad'     => ModalidadTraslado::PUBLICO,
            'transportista' => Transportista::conRuc('20612690759', 'TRANSPORTES DEL NORTE SAC'),
            'vehiculo'      => null,
            'conductores'   => [],
        ]);

        self::assertFalse($traslado->declaraVehiculo());
        self::assertSame('TRANSPORTES DEL NORTE SAC', $traslado->transportista?->razonSocial);
    }

    /**
     * El error que de verdad se comete: el sistema origen arrastra los datos de la
     * guía anterior y manda las dos modalidades a la vez.
     */
    #[Test]
    public function rechaza_placa_propia_y_transportista_a_la_vez(): void
    {
        try {
            self::privado(['transportista' => Transportista::conRuc('20612690759', 'TRANSPORTES SAC')]);
            self::fail('Debería haber lanzado.');
        } catch (ContratoInvalidoException $e) {
            self::assertSame('traslado.transportista', $e->campo);
        }
    }

    #[Test]
    public function rechaza_el_flete_contratado_con_placa_propia(): void
    {
        try {
            self::privado([
                'modalidad'     => ModalidadTraslado::PUBLICO,
                'transportista' => Transportista::conRuc('20612690759', 'TRANSPORTES SAC'),
                'conductores'   => [],
            ]);
            self::fail('Debería haber lanzado.');
        } catch (ContratoInvalidoException $e) {
            self::assertSame('traslado.vehiculo', $e->campo);
        }
    }

    #[Test]
    public function rechaza_el_transporte_propio_sin_placa(): void
    {
        try {
            self::privado(['vehiculo' => null]);
            self::fail('Debería haber lanzado.');
        } catch (ContratoInvalidoException $e) {
            self::assertSame('traslado.vehiculo', $e->campo);
        }
    }

    #[Test]
    public function rechaza_el_transporte_propio_sin_conductor(): void
    {
        try {
            self::privado(['conductores' => []]);
            self::fail('Debería haber lanzado.');
        } catch (ContratoInvalidoException $e) {
            self::assertSame('traslado.conductores', $e->campo);
        }
    }

    /** SUNAT espera uno al frente. Dos principales es tan ambiguo como ninguno. */
    #[Test]
    public function exige_exactamente_un_conductor_principal(): void
    {
        foreach ([
            [self::conductor(true), self::conductor(true, '10203040')],
            [self::conductor(false)],
        ] as $conductores) {
            try {
                self::privado(['conductores' => $conductores]);
                self::fail('Debería haber lanzado.');
            } catch (ContratoInvalidoException $e) {
                self::assertSame('traslado.conductores', $e->campo);
            }
        }
    }

    /** El reparto en moto: SUNAT exime de declarar vehículo y conductor. */
    #[Test]
    public function acepta_el_vehiculo_menor_sin_placa_ni_chofer(): void
    {
        $traslado = self::privado([
            'vehiculoMenor' => true,
            'vehiculo'      => null,
            'conductores'   => [],
        ]);

        self::assertFalse($traslado->declaraVehiculo());
        self::assertNull($traslado->conductorPrincipal());
    }

    #[Test]
    public function rechaza_el_vehiculo_menor_con_placa(): void
    {
        try {
            self::privado(['vehiculoMenor' => true, 'conductores' => []]);
            self::fail('Debería haber lanzado.');
        } catch (ContratoInvalidoException $e) {
            self::assertSame('traslado.vehiculo_menor', $e->campo);
        }
    }

    #[Test]
    public function el_motivo_otros_obliga_a_explicarse(): void
    {
        try {
            self::privado(['motivo' => MotivoTraslado::OTROS]);
            self::fail('Debería haber lanzado.');
        } catch (ContratoInvalidoException $e) {
            self::assertSame('traslado.descripcion_motivo', $e->campo);
        }

        self::assertInstanceOf(Traslado::class, self::privado([
            'motivo'            => MotivoTraslado::OTROS,
            'descripcionMotivo' => 'Traslado a feria de exposición',
        ]));
    }

    /** Sin código de establecimiento, un traslado entre locales propios se rechaza. */
    #[Test]
    public function el_traslado_entre_locales_propios_exige_los_dos_codigos(): void
    {
        try {
            self::privado([
                'motivo'  => MotivoTraslado::TRASLADO_ENTRE_ESTABLECIMIENTOS,
                'partida' => self::partida('0000'),
            ]);
            self::fail('Debería haber lanzado.');
        } catch (ContratoInvalidoException $e) {
            self::assertSame('traslado.llegada.codigo_establecimiento', $e->campo);
        }

        self::assertInstanceOf(Traslado::class, self::privado([
            'motivo'  => MotivoTraslado::TRASLADO_ENTRE_ESTABLECIMIENTOS,
            'partida' => self::partida('0000'),
            'llegada' => self::llegada('0002'),
        ]));
    }

    /**
     * El código de establecimiento lleva el RUC de la empresa en el XML, así que solo
     * cabe en el extremo que es suyo. SUNAT lo rechaza con el 3411, diciendo además
     * en cuál de los dos te equivocaste.
     */
    #[Test]
    public function rechaza_el_codigo_de_local_en_el_extremo_ajeno(): void
    {
        // Una venta llega a casa del cliente: ese extremo no es un local propio.
        try {
            self::privado(['llegada' => self::llegada('0002')]);
            self::fail('Debería haber lanzado.');
        } catch (ContratoInvalidoException $e) {
            self::assertSame('traslado.llegada.codigo_establecimiento', $e->campo);
        }

        // Una compra sale de casa del proveedor: tampoco.
        try {
            self::privado([
                'motivo'  => MotivoTraslado::COMPRA,
                'partida' => self::partida('0000'),
            ]);
            self::fail('Debería haber lanzado.');
        } catch (ContratoInvalidoException $e) {
            self::assertSame('traslado.partida.codigo_establecimiento', $e->campo);
        }
    }

    /** Y sí lo admite en el extremo propio, que es el sentido de la regla. */
    #[Test]
    public function admite_el_codigo_de_local_en_el_extremo_propio(): void
    {
        self::assertSame(
            '0000',
            self::privado(['partida' => self::partida('0000')])->partida->codigoEstablecimiento,
        );

        self::assertSame(
            '0002',
            self::privado([
                'motivo'  => MotivoTraslado::COMPRA,
                'llegada' => self::llegada('0002'),
            ])->llegada->codigoEstablecimiento,
        );
    }

    #[Test]
    public function exige_un_peso_bruto_positivo(): void
    {
        try {
            self::privado(['pesoTotal' => 0.0]);
            self::fail('Debería haber lanzado.');
        } catch (ContratoInvalidoException $e) {
            self::assertSame('traslado.peso_total', $e->campo);
        }
    }

    #[Test]
    public function solo_admite_kilogramos_o_toneladas(): void
    {
        try {
            self::privado(['unidadPeso' => 'LBR']);
            self::fail('Debería haber lanzado.');
        } catch (ContratoInvalidoException $e) {
            self::assertSame('traslado.unidad_peso', $e->campo);
        }
    }

    #[Test]
    public function sobrevive_a_la_ida_y_vuelta(): void
    {
        $original = self::privado()->aArray();

        self::assertSame($original, Traslado::desdeArray($original)->aArray());
    }
}
