<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Tests\Dto;

use MacSoft\Facturacion\Contrato\Dto\RespuestaGuia;
use MacSoft\Facturacion\Contrato\Enum\EstadoGuia;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `puedeTrasladar` viaja CALCULADO y no se deduce del estado en el otro lado.
 *
 * Es el mismo error que se pagó dos veces con los comprobantes —dos sistemas con
 * listas de estados distintas— y aquí no se repite.
 */
final class RespuestaGuiaTest extends TestCase
{
    /** @param array<string,mixed> $cambios */
    private static function payload(array $cambios = []): array
    {
        return array_replace([
            'id'              => 7,
            'estado'          => 'aceptada',
            'serie'           => 'T001',
            'numero'          => 4,
            'numero_completo' => 'T001-00000004',
            'puede_trasladar' => true,
            'aviso'           => 'La mercadería puede salir.',
        ], $cambios);
    }

    #[Test]
    public function una_guia_aceptada_autoriza_el_traslado(): void
    {
        $r = RespuestaGuia::desdeArray(self::payload());

        self::assertSame(EstadoGuia::ACEPTADA, $r->estado);
        self::assertTrue($r->puedeTrasladar);
        self::assertTrue($r->terminal());
        self::assertFalse($r->esperaRespuesta());
    }

    #[Test]
    public function una_guia_esperando_no_autoriza_nada_y_hay_que_volver(): void
    {
        $r = RespuestaGuia::desdeArray(self::payload([
            'estado'          => 'enviada',
            'puede_trasladar' => false,
            'aviso'           => 'SUNAT aún no responde.',
        ]));

        self::assertFalse($r->puedeTrasladar);
        self::assertTrue($r->esperaRespuesta());
        self::assertFalse($r->terminal());
    }

    /**
     * Si el emisor no mandara el campo, se responde con el enum —la misma fuente— y
     * nunca con un «sí» inventado.
     */
    #[Test]
    public function sin_el_campo_se_responde_con_el_enum_y_nunca_que_si(): void
    {
        $payload = self::payload(['estado' => 'rechazada']);
        unset($payload['puede_trasladar'], $payload['aviso']);

        $r = RespuestaGuia::desdeArray($payload);

        self::assertFalse($r->puedeTrasladar);
        self::assertNotSame('', $r->aviso);
    }

    /** Un estado que este contrato no conozca no puede autorizar un traslado. */
    #[Test]
    public function un_estado_desconocido_no_autoriza_el_traslado(): void
    {
        $payload = self::payload(['estado' => 'inventado']);
        unset($payload['puede_trasladar']);

        self::assertFalse(RespuestaGuia::desdeArray($payload)->puedeTrasladar);
    }

    #[Test]
    public function sobrevive_a_la_ida_y_vuelta(): void
    {
        $original = RespuestaGuia::desdeArray(self::payload([
            'referencia_externa' => 'DESP-77',
            'sunat'              => ['codigo' => '0', 'descripcion' => 'ACEPTADA'],
            'enlaces'            => ['pdf' => 'https://x/pdf'],
        ]));

        self::assertSame($original->aArray(), RespuestaGuia::desdeArray($original->aArray())->aArray());
        self::assertSame('0', $original->sunatCodigo);
    }
}
