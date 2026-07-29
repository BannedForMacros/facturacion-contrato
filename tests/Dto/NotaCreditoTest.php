<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Tests\Dto;

use MacSoft\Facturacion\Contrato\Dto\Item;
use MacSoft\Facturacion\Contrato\Dto\NotaCredito;
use MacSoft\Facturacion\Contrato\Enum\Impuesto;
use MacSoft\Facturacion\Contrato\Enum\MotivoNotaCredito;
use MacSoft\Facturacion\Contrato\Excepcion\ContratoInvalidoException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * El bug que estos tests vigilan: una devolución parcial acreditaba la factura
 * ENTERA porque el emisor descartaba en silencio los importes enviados.
 *
 * La defensa es doble — que "con líneas" signifique parcial de forma explícita, y
 * que una parcial no pueda existir sin un `total` con el que contrastar.
 */
final class NotaCreditoTest extends TestCase
{
    private static function linea(): Item
    {
        return new Item('Paracetamol 500mg', 2, 2.50, codigo: 'MED-001');
    }

    #[Test]
    public function sin_items_la_nota_es_total(): void
    {
        $nota = new NotaCredito('devolucion-451', MotivoNotaCredito::ANULACION);

        self::assertTrue($nota->esTotal());
        self::assertFalse($nota->esParcial());
        self::assertNull($nota->total);

        // Y no se manda `"items": []`: el contrato dice omitir para anular todo.
        self::assertArrayNotHasKey('items', $nota->aArray());
    }

    #[Test]
    public function con_items_la_nota_es_parcial(): void
    {
        $nota = new NotaCredito(
            'devolucion-452',
            MotivoNotaCredito::DEVOLUCION,
            'Devolución parcial — 2 de 10 cajas',
            [self::linea()],
            5.00,
        );

        self::assertTrue($nota->esParcial());
        self::assertFalse($nota->esTotal());
        self::assertSame(5.00, $nota->total);
        self::assertSame(5.00, $nota->importeItems());
    }

    /** EL TEST QUE CIERRA EL BUG: una parcial sin `total` no llega a construirse. */
    #[Test]
    public function con_items_y_sin_total_no_se_construye(): void
    {
        try {
            new NotaCredito('devolucion-453', MotivoNotaCredito::DEVOLUCION, null, [self::linea()]);
            self::fail('Una nota parcial sin total debería ser imposible de construir.');
        } catch (ContratoInvalidoException $e) {
            self::assertSame('total', $e->campo);
            self::assertStringContainsString('parcial', $e->getMessage());
        }
    }

    #[Test]
    public function la_misma_regla_se_aplica_al_deserializar(): void
    {
        $this->expectException(ContratoInvalidoException::class);

        NotaCredito::desdeArray([
            'idempotency_key' => 'devolucion-454',
            'motivo'          => 'devolucion',
            'items'           => [
                ['descripcion' => 'Paracetamol 500mg', 'cantidad' => 2, 'precio_unitario' => 2.50],
            ],
        ]);
    }

    #[Test]
    public function rechaza_una_nota_sin_clave_de_idempotencia(): void
    {
        try {
            new NotaCredito('', MotivoNotaCredito::ANULACION);
            self::fail('Debería haber lanzado.');
        } catch (ContratoInvalidoException $e) {
            self::assertSame('idempotency_key', $e->campo);
        }
    }

    #[Test]
    public function rechaza_un_total_no_positivo(): void
    {
        $this->expectException(ContratoInvalidoException::class);

        new NotaCredito('devolucion-455', MotivoNotaCredito::DEVOLUCION, null, [self::linea()], 0.0);
    }

    #[Test]
    public function rechaza_un_motivo_desconocido(): void
    {
        $this->expectException(ContratoInvalidoException::class);

        NotaCredito::desdeArray(['idempotency_key' => 'x', 'motivo' => 'me_equivoque']);
    }

    /**
     * Que sea total o parcial lo decide la presencia de líneas, NO el motivo.
     *
     * Una devolución puede ser de las dos formas; ligarlo al motivo obligaría a
     * inventar `devolucion_total` y `devolucion_parcial` y a mantenerlos en dos
     * repositorios.
     */
    #[Test]
    public function el_motivo_no_decide_si_es_total_o_parcial(): void
    {
        $total = new NotaCredito('nc-1', MotivoNotaCredito::DEVOLUCION);
        $parcial = new NotaCredito('nc-2', MotivoNotaCredito::DEVOLUCION, null, [self::linea()], 5.00);

        self::assertTrue($total->esTotal());
        self::assertTrue($parcial->esParcial());
    }

    #[Test]
    public function los_constructores_con_nombre_expresan_la_intencion(): void
    {
        self::assertTrue(NotaCredito::total('nc-3', MotivoNotaCredito::ANULACION)->esTotal());
        self::assertTrue(
            NotaCredito::parcial('nc-4', MotivoNotaCredito::DEVOLUCION, [self::linea()], 5.00)->esParcial(),
        );

        $this->expectException(ContratoInvalidoException::class);
        NotaCredito::parcial('nc-5', MotivoNotaCredito::DEVOLUCION, [], 5.00);
    }

    /** Una línea gratuita no se cobró, así que tampoco se devuelve dinero por ella. */
    #[Test]
    public function el_importe_de_las_lineas_excluye_lo_gratuito(): void
    {
        $nota = new NotaCredito(
            'nc-6',
            MotivoNotaCredito::DEVOLUCION,
            null,
            [self::linea(), new Item('Muestra gratuita', 1, 1.0, impuesto: Impuesto::GRATUITO)],
            5.00,
        );

        self::assertSame(5.00, $nota->importeItems());
    }

    #[Test]
    public function la_ida_y_vuelta_conserva_la_nota_parcial(): void
    {
        $nota = new NotaCredito(
            'devolucion-451',
            MotivoNotaCredito::DEVOLUCION,
            'Devolución parcial — 2 de 10 cajas',
            [self::linea()],
            5.00,
        );

        $vuelta = NotaCredito::desdeArray($nota->aArray());

        self::assertEquals($nota, $vuelta);
        self::assertSame($nota->aArray(), $vuelta->aArray());
    }

    #[Test]
    public function la_ida_y_vuelta_conserva_la_nota_total(): void
    {
        $nota = NotaCredito::total('anulacion-99', MotivoNotaCredito::ANULACION);

        self::assertEquals($nota, NotaCredito::desdeArray($nota->aArray()));
    }
}
