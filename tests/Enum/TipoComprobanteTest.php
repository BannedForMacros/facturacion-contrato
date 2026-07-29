<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Tests\Enum;

use MacSoft\Facturacion\Contrato\Enum\TipoComprobante;
use MacSoft\Facturacion\Contrato\Enum\TipoDocumento;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `AUTO` existe para que el sistema origen no tenga que decidir el tipo de
 * comprobante. Si la regla se resolviera en cada adaptador, cada adaptador tendría
 * su propia versión ligeramente distinta.
 */
final class TipoComprobanteTest extends TestCase
{
    #[Test]
    public function con_ruc_solo_cabe_factura(): void
    {
        self::assertSame(
            TipoComprobante::FACTURA,
            TipoComprobante::AUTO->resolver(TipoDocumento::RUC),
        );
    }

    #[Test]
    public function sin_ruc_solo_cabe_boleta(): void
    {
        self::assertSame(TipoComprobante::BOLETA, TipoComprobante::AUTO->resolver(TipoDocumento::DNI));
        self::assertSame(TipoComprobante::BOLETA, TipoComprobante::AUTO->resolver(TipoDocumento::CE));
        self::assertSame(TipoComprobante::BOLETA, TipoComprobante::AUTO->resolver(TipoDocumento::PASAPORTE));
        self::assertSame(TipoComprobante::BOLETA, TipoComprobante::AUTO->resolver(TipoDocumento::SIN_DOCUMENTO));
    }

    /**
     * Un tipo ya decidido NO se toca, ni siquiera para "arreglarlo".
     *
     * Si alguien pide factura a un cliente con DNI, la respuesta correcta es un error
     * de validación (`cliente_sin_ruc`), no convertirla en boleta a sus espaldas: el
     * cliente pidió una factura y hay que decirle por qué no la tiene.
     */
    #[Test]
    public function un_tipo_explicito_se_respeta_aunque_no_encaje(): void
    {
        self::assertSame(
            TipoComprobante::FACTURA,
            TipoComprobante::FACTURA->resolver(TipoDocumento::DNI),
        );

        self::assertSame(
            TipoComprobante::BOLETA,
            TipoComprobante::BOLETA->resolver(TipoDocumento::RUC),
        );
    }

    #[Test]
    public function solo_la_factura_exige_ruc(): void
    {
        self::assertTrue(TipoComprobante::FACTURA->exigeRuc());
        self::assertFalse(TipoComprobante::BOLETA->exigeRuc());
        self::assertFalse(TipoComprobante::AUTO->exigeRuc());
    }

    /** El umbral de identificación es cosa de la boleta: la factura ya identifica por RUC. */
    #[Test]
    public function solo_la_boleta_esta_sujeta_al_umbral(): void
    {
        self::assertTrue(TipoComprobante::BOLETA->sujetoAUmbral());
        self::assertFalse(TipoComprobante::FACTURA->sujetoAUmbral());
    }

    #[Test]
    public function resolver_nunca_devuelve_auto(): void
    {
        foreach (TipoDocumento::cases() as $documento) {
            self::assertNotSame(
                TipoComprobante::AUTO,
                TipoComprobante::AUTO->resolver($documento),
                "AUTO se quedó sin resolver para {$documento->value}",
            );
        }
    }
}
