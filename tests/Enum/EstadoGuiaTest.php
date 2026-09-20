<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Tests\Enum;

use MacSoft\Facturacion\Contrato\Enum\EstadoGuia;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `permiteTraslado()` es la pregunta cara de este enum: si se responde que sí de
 * más, sale un camión amparado por una guía que SUNAT no ha aceptado. Por eso se
 * prueba estado por estado y no por muestreo — un caso nuevo sin cubrir aquí es
 * exactamente el que se escaparía.
 */
final class EstadoGuiaTest extends TestCase
{
    #[Test]
    public function solo_una_guia_aceptada_ampara_el_traslado(): void
    {
        foreach (EstadoGuia::cases() as $estado) {
            self::assertSame(
                $estado === EstadoGuia::ACEPTADA,
                $estado->permiteTraslado(),
                "«{$estado->value}» no puede amparar un traslado.",
            );
        }
    }

    /** La simulación sirve para probar la pantalla, no para sacar mercadería. */
    #[Test]
    public function la_simulacion_no_ampara_nada(): void
    {
        self::assertFalse(EstadoGuia::SIMULADA->permiteTraslado());
        self::assertFalse(EstadoGuia::SIMULADA->informada());
    }

    #[Test]
    public function los_estados_informados_son_los_que_consumieron_numero(): void
    {
        $informados = array_values(array_filter(
            EstadoGuia::cases(),
            static fn (EstadoGuia $e) => $e->informada(),
        ));

        self::assertSame([
            EstadoGuia::ENVIANDO,
            EstadoGuia::ENVIADA,
            EstadoGuia::ACEPTADA,
            EstadoGuia::RECHAZADA,
            EstadoGuia::ANULADA,
        ], $informados);
    }

    #[Test]
    public function solo_se_reintenta_lo_que_nunca_llego_a_sunat(): void
    {
        foreach (EstadoGuia::cases() as $estado) {
            if ($estado->reintentable()) {
                self::assertFalse(
                    $estado->informada(),
                    "«{$estado->value}» no puede reintentarse: SUNAT ya lo conoce.",
                );
            }
        }
    }

    /** Un estado terminal no espera respuesta, y uno que la espera no es terminal. */
    #[Test]
    public function terminal_y_esperando_respuesta_se_excluyen(): void
    {
        foreach (EstadoGuia::cases() as $estado) {
            self::assertFalse(
                $estado->terminal() && $estado->esperaRespuesta(),
                "«{$estado->value}» no puede estar acabado y esperando a la vez.",
            );
        }
    }

    /** Solo se da de baja lo que existe ante SUNAT. */
    #[Test]
    public function solo_se_da_de_baja_una_guia_aceptada(): void
    {
        foreach (EstadoGuia::cases() as $estado) {
            self::assertSame($estado === EstadoGuia::ACEPTADA, $estado->permiteBaja());
        }
    }

    #[Test]
    public function todos_los_estados_saben_explicarse(): void
    {
        foreach (EstadoGuia::cases() as $estado) {
            self::assertNotSame('', trim($estado->etiqueta()));
            self::assertNotSame('', trim($estado->aviso()));
        }
    }
}
