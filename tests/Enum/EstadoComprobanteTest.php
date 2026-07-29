<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Tests\Enum;

use MacSoft\Facturacion\Contrato\Enum\EstadoComprobante;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Estos tests son la red que impide que se repitan los dos bugs fiscales que
 * motivaron el paquete:
 *
 *   1. Se podía anular localmente una venta cuyo comprobante SUNAT ya conocía.
 *   2. Se pedía nota de crédito sobre boletas en `pendiente_resumen`, y como toda
 *      boleta vive ahí hasta las 23:55, ninguna devolución llegaba a emitirse.
 *
 * No comprueban implementación, comprueban DECISIONES. Si alguien cambia una de
 * estas respuestas, tiene que venir aquí y justificarlo por escrito.
 */
final class EstadoComprobanteTest extends TestCase
{
    /**
     * La tabla completa de la decisión con consecuencias fiscales.
     *
     * Está escrita a mano y por extenso a propósito: es la fuente contra la que se
     * contrasta el enum, no una copia de él.
     *
     * @return array<string, array{EstadoComprobante, bool}>
     */
    public static function tablaDeAnulacion(): array
    {
        return [
            'enviando bloquea'            => [EstadoComprobante::ENVIANDO, true],
            'enviado bloquea'             => [EstadoComprobante::ENVIADO, true],
            'pendiente_resumen bloquea'   => [EstadoComprobante::PENDIENTE_RESUMEN, true],
            'en_resumen bloquea'          => [EstadoComprobante::EN_RESUMEN, true],
            'pendiente_anulacion bloquea' => [EstadoComprobante::PENDIENTE_ANULACION, true],
            'aceptado bloquea'            => [EstadoComprobante::ACEPTADO, true],
            'anulado bloquea'             => [EstadoComprobante::ANULADO, true],

            'pendiente no bloquea'   => [EstadoComprobante::PENDIENTE, false],
            'rechazado no bloquea'   => [EstadoComprobante::RECHAZADO, false],
            'error_envio no bloquea' => [EstadoComprobante::ERROR_ENVIO, false],
            'simulado no bloquea'    => [EstadoComprobante::SIMULADO, false],
        ];
    }

    #[Test]
    #[DataProvider('tablaDeAnulacion')]
    public function decide_si_bloquea_la_anulacion_en_origen(EstadoComprobante $estado, bool $bloquea): void
    {
        self::assertSame($bloquea, $estado->bloqueaAnulacion(), $estado->value);
    }

    /**
     * EL TEST QUE IMPIDE QUE VUELVA A PASAR.
     *
     * El bug original no fue una respuesta equivocada: fue una lista INCOMPLETA. A
     * `ventoryPOS` le faltaban tres estados y el `match` los dejaba caer en el
     * comportamiento permisivo.
     *
     * Aquí, añadir un caso al enum sin venir a la tabla de arriba rompe la suite. La
     * regla al hacerlo está en el docblock del enum: ante la duda, `true`.
     */
    #[Test]
    public function ningun_estado_puede_existir_sin_una_decision_explicita_sobre_la_anulacion(): void
    {
        $decididos = array_map(
            static fn (array $fila) => $fila[0]->value,
            array_values(self::tablaDeAnulacion()),
        );

        $existentes = array_column(EstadoComprobante::cases(), 'value');

        sort($decididos);
        sort($existentes);

        self::assertSame(
            $existentes,
            $decididos,
            'Hay un estado nuevo sin decidir si bloquea la anulación (o uno decidido que ya no existe). '
                . 'Añádelo a tablaDeAnulacion(): ante la duda, bloquea.',
        );
    }

    #[Test]
    public function solo_se_acredita_lo_que_sunat_ya_conoce(): void
    {
        $admiten = array_values(array_filter(
            EstadoComprobante::cases(),
            static fn (EstadoComprobante $e) => $e->admiteNotaCredito(),
        ));

        self::assertSame(
            [EstadoComprobante::ENVIADO, EstadoComprobante::ACEPTADO],
            $admiten,
            'Una nota de crédito sobre algo que SUNAT no ha visto no tiene original al que referirse.',
        );
    }

    /** El caso de la boleta antes de las 23:55: hay que esperar, no fallar. */
    #[Test]
    public function la_boleta_pendiente_de_resumen_debe_esperar_no_fallar(): void
    {
        self::assertTrue(EstadoComprobante::PENDIENTE_RESUMEN->debeEsperar());
        self::assertFalse(EstadoComprobante::PENDIENTE_RESUMEN->admiteNotaCredito());
    }

    #[Test]
    public function los_estados_transitorios_esperan_y_los_terminales_no(): void
    {
        foreach ([EstadoComprobante::PENDIENTE, EstadoComprobante::ENVIANDO,
            EstadoComprobante::PENDIENTE_RESUMEN, EstadoComprobante::EN_RESUMEN,
            EstadoComprobante::PENDIENTE_ANULACION, EstadoComprobante::ERROR_ENVIO] as $estado) {
            self::assertTrue($estado->debeEsperar(), "{$estado->value} debería esperar");
        }

        // Esperar por algo terminal es esperar para siempre.
        foreach ([EstadoComprobante::ACEPTADO, EstadoComprobante::RECHAZADO,
            EstadoComprobante::ANULADO, EstadoComprobante::SIMULADO] as $estado) {
            self::assertFalse($estado->debeEsperar(), "{$estado->value} no debería esperar");
            self::assertTrue($estado->esTerminal());
        }
    }

    #[Test]
    public function solo_reintenta_el_envio_lo_que_puede_salir_bien_al_segundo_intento(): void
    {
        self::assertTrue(EstadoComprobante::PENDIENTE->esReintentable());
        self::assertTrue(EstadoComprobante::ERROR_ENVIO->esReintentable());
        self::assertTrue(EstadoComprobante::RECHAZADO->esReintentable());

        // Reenviar algo ya aceptado duplicaría el comprobante ante SUNAT.
        self::assertFalse(EstadoComprobante::ACEPTADO->esReintentable());
        self::assertFalse(EstadoComprobante::ENVIANDO->esReintentable());
        self::assertFalse(EstadoComprobante::ANULADO->esReintentable());
    }

    #[Test]
    public function todo_estado_tiene_etiqueta_y_color_para_la_interfaz(): void
    {
        foreach (EstadoComprobante::cases() as $estado) {
            self::assertNotSame('', $estado->etiqueta(), $estado->value);
            self::assertNotSame('', $estado->color(), $estado->value);
        }
    }

    /** Un estado que admite nota de crédito no puede además pedir que se espere. */
    #[Test]
    public function esperar_y_admitir_nota_de_credito_se_excluyen(): void
    {
        foreach (EstadoComprobante::cases() as $estado) {
            self::assertFalse(
                $estado->admiteNotaCredito() && $estado->debeEsperar(),
                "{$estado->value} dice a la vez 'acredita' y 'espera'",
            );
        }
    }
}
