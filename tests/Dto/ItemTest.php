<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Tests\Dto;

use MacSoft\Facturacion\Contrato\Dto\Item;
use MacSoft\Facturacion\Contrato\Enum\Impuesto;
use MacSoft\Facturacion\Contrato\Excepcion\ContratoInvalidoException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ItemTest extends TestCase
{
    #[Test]
    public function rechaza_una_descripcion_vacia(): void
    {
        $this->expectException(ContratoInvalidoException::class);

        new Item('   ', 1, 10.0);
    }

    #[Test]
    public function rechaza_cantidad_cero_o_negativa(): void
    {
        foreach ([0.0, -1.0] as $cantidad) {
            try {
                new Item('Paracetamol 500mg', $cantidad, 2.50);
                self::fail("Aceptó una cantidad de {$cantidad}.");
            } catch (ContratoInvalidoException $e) {
                self::assertSame('items.cantidad', $e->campo);
            }
        }
    }

    /** Un descuento que se come la línea deja un importe negativo, y SUNAT lo rechaza. */
    #[Test]
    public function rechaza_un_descuento_mayor_que_el_importe_de_la_linea(): void
    {
        $this->expectException(ContratoInvalidoException::class);

        new Item('Paracetamol 500mg', 2, 2.50, descuento: 5.01);
    }

    #[Test]
    public function admite_un_descuento_igual_al_importe(): void
    {
        // Regalar una línea es legítimo (promoción 2x1 aplicada como descuento).
        self::assertSame(0.0, (new Item('Paracetamol 500mg', 2, 2.50, descuento: 5.00))->importe());
    }

    #[Test]
    public function calcula_el_importe_bruto_con_y_sin_descuento(): void
    {
        self::assertSame(25.0, (new Item('Paracetamol 500mg', 10, 2.50))->importe());
        self::assertSame(22.5, (new Item('Paracetamol 500mg', 10, 2.50, descuento: 2.50))->importe());
    }

    /**
     * Solo tres campos obligatorios: qué, cuánto y a qué precio.
     *
     * Cada obligatorio de más es una barrera para conectar el siguiente sistema, así
     * que los valores por defecto son los del POS de mostrador.
     */
    #[Test]
    public function los_defaults_son_los_del_mostrador(): void
    {
        $item = new Item('Paracetamol 500mg', 1, 2.50);

        self::assertSame('UND', $item->unidad);
        self::assertSame(Impuesto::GRAVADO, $item->impuesto);
        self::assertTrue($item->precioIncluyeImpuesto);
        self::assertSame(0.0, $item->descuento);
        self::assertNull($item->codigo);
    }

    #[Test]
    public function rechaza_un_precio_negativo(): void
    {
        $this->expectException(ContratoInvalidoException::class);

        new Item('Paracetamol 500mg', 1, -0.01);
    }

    #[Test]
    public function admite_precio_cero(): void
    {
        // Una bonificación va a precio cero y sí se declara ante SUNAT.
        $item = new Item('Muestra gratuita', 1, 0.0, impuesto: Impuesto::GRATUITO);

        self::assertSame(0.0, $item->importe());
        self::assertFalse($item->impuesto->sumaAlTotal());
    }

    #[Test]
    public function rechaza_una_descripcion_mas_larga_de_lo_que_admite_sunat(): void
    {
        $this->expectException(ContratoInvalidoException::class);

        new Item(str_repeat('a', 501), 1, 1.0);
    }

    /** `impuesto` y `precio_incluye_impuesto` son ortogonales: se leen por separado. */
    #[Test]
    public function el_impuesto_y_el_precio_bruto_son_preguntas_distintas(): void
    {
        $item = Item::desdeArray([
            'descripcion'             => 'Paracetamol 500mg',
            'cantidad'                => 3,
            'precio_unitario'         => 2.50,
            'impuesto'                => 'exonerado',
            'precio_incluye_impuesto' => false,
        ]);

        self::assertSame(Impuesto::EXONERADO, $item->impuesto);
        self::assertFalse($item->precioIncluyeImpuesto);
    }

    #[Test]
    public function un_impuesto_desconocido_no_cae_en_gravado_por_defecto(): void
    {
        // Caer a `gravado` en silencio facturaría IGV sobre un medicamento exonerado.
        $this->expectException(ContratoInvalidoException::class);

        Item::desdeArray([
            'descripcion'     => 'Paracetamol 500mg',
            'cantidad'        => 1,
            'precio_unitario' => 2.50,
            'impuesto'        => 'exento',
        ]);
    }
}
