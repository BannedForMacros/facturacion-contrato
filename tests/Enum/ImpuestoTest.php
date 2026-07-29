<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Tests\Enum;

use MacSoft\Facturacion\Contrato\Enum\Impuesto;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImpuestoTest extends TestCase
{
    /** Exonerado e inafecto NO generan impuesto: confundirlos infla el IGV declarado. */
    #[Test]
    public function solo_lo_gravado_genera_impuesto(): void
    {
        self::assertTrue(Impuesto::GRAVADO->generaImpuesto());

        foreach ([Impuesto::EXONERADO, Impuesto::INAFECTO, Impuesto::EXPORTACION, Impuesto::GRATUITO] as $i) {
            self::assertFalse($i->generaImpuesto(), $i->value);
        }
    }

    /**
     * Lo gratuito se declara ante SUNAT pero no se cobra.
     *
     * Sumarlo al total es la forma más fácil de que la verificación cruzada de §3.4
     * falle sin motivo y la venta no se emita.
     */
    #[Test]
    public function todo_suma_al_total_salvo_lo_gratuito(): void
    {
        self::assertFalse(Impuesto::GRATUITO->sumaAlTotal());

        foreach ([Impuesto::GRAVADO, Impuesto::EXONERADO, Impuesto::INAFECTO, Impuesto::EXPORTACION] as $i) {
            self::assertTrue($i->sumaAlTotal(), $i->value);
        }
    }

    /** El contrato habla por nombres; el catálogo 07 (10/20/30/40/21) vive en el emisor. */
    #[Test]
    public function el_vocabulario_del_contrato_no_lleva_codigos_de_sunat(): void
    {
        self::assertSame(
            ['gravado', 'exonerado', 'inafecto', 'exportacion', 'gratuito'],
            array_column(Impuesto::cases(), 'value'),
        );
    }

    #[Test]
    public function todo_impuesto_tiene_etiqueta(): void
    {
        foreach (Impuesto::cases() as $i) {
            self::assertNotSame('', $i->etiqueta(), $i->value);
        }
    }
}
