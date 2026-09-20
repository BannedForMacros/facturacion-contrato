<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Tests\Enum;

use MacSoft\Facturacion\Contrato\Enum\MotivoTraslado;
use MacSoft\Facturacion\Contrato\Enum\ReglaDestinatario;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ESTA TABLA NO SE DEDUJO, SE MIDIÓ.
 *
 * Cada motivo se envió dos veces al validador de SUNAT —una con un tercero como
 * destinatario y otra con la propia empresa— y lo que hay aquí es el resultado
 * literal de esas respuestas. La primera versión, escrita leyendo la documentación,
 * tenía dos motivos mal (compra y recojo de bienes transformados) y los dos habrían
 * salido como rechazo 2554 con la guía ya numerada.
 *
 * Por eso las pruebas van por RESPUESTA DE SUNAT y no por «lo que parece lógico»:
 * quien cambie una de estas respuestas tiene que volver a medirla.
 */
final class MotivoTrasladoTest extends TestCase
{
    /**
     * Motivos en los que la mercadería VIENE hacia la empresa. Nombrar a un tercero
     * devuelve el error 2554.
     */
    #[Test]
    public function la_mercaderia_que_vuelve_a_casa_no_admite_un_tercero(): void
    {
        foreach ([
            MotivoTraslado::COMPRA,
            MotivoTraslado::TRASLADO_ENTRE_ESTABLECIMIENTOS,
            MotivoTraslado::RECOJO_BIENES_TRANSFORMADOS,
        ] as $motivo) {
            self::assertSame(
                ReglaDestinatario::PROPIA_EMPRESA,
                $motivo->reglaDestinatario(),
                "«{$motivo->value}» trae mercadería hacia la empresa: el destinatario es ella.",
            );
            self::assertTrue($motivo->reglaDestinatario()->prohibeTercero());
        }
    }

    /**
     * Motivos en los que la mercadería SALE hacia alguien. Poner a la propia empresa
     * devuelve el error 2555.
     */
    #[Test]
    public function la_mercaderia_que_sale_necesita_saber_hacia_quien(): void
    {
        foreach ([
            MotivoTraslado::VENTA,
            MotivoTraslado::VENTA_ENTREGA_TERCEROS,
            MotivoTraslado::VENTA_SUJETA_CONFIRMACION,
            MotivoTraslado::CONSIGNACION,
            MotivoTraslado::DEVOLUCION,
            MotivoTraslado::TRASLADO_PARA_TRANSFORMACION,
            MotivoTraslado::EXPORTACION,
        ] as $motivo) {
            self::assertSame(
                ReglaDestinatario::TERCERO,
                $motivo->reglaDestinatario(),
                "«{$motivo->value}» saca mercadería de la empresa: hace falta el destinatario.",
            );
            self::assertTrue($motivo->reglaDestinatario()->exigeTercero());
        }
    }

    /** El vendedor ambulante puede llevar mercadería sin comprador todavía, o con uno. */
    #[Test]
    public function hay_motivos_que_admiten_las_dos_formas(): void
    {
        foreach ([MotivoTraslado::EMISOR_ITINERANTE, MotivoTraslado::OTROS] as $motivo) {
            $regla = $motivo->reglaDestinatario();

            self::assertSame(ReglaDestinatario::CUALQUIERA, $regla);
            self::assertFalse($regla->exigeTercero());
            self::assertFalse($regla->prohibeTercero());
        }
    }

    /**
     * El código de establecimiento solo cabe en el extremo que es de la empresa,
     * porque en el XML viaja con su RUC. Ponerlo en el otro es el error 3411.
     */
    #[Test]
    public function el_codigo_de_local_solo_va_en_el_extremo_propio(): void
    {
        // Sale de mi almacén hacia el cliente: mío es el origen.
        self::assertTrue(MotivoTraslado::VENTA->admiteEstablecimientoPartida());
        self::assertFalse(MotivoTraslado::VENTA->admiteEstablecimientoLlegada());

        // Viene de casa del proveedor hacia mi almacén: mío es el destino.
        self::assertFalse(MotivoTraslado::COMPRA->admiteEstablecimientoPartida());
        self::assertTrue(MotivoTraslado::COMPRA->admiteEstablecimientoLlegada());

        // Entre mis locales los dos son míos, y además son obligatorios.
        $entreLocales = MotivoTraslado::TRASLADO_ENTRE_ESTABLECIMIENTOS;
        self::assertTrue($entreLocales->admiteEstablecimientoPartida());
        self::assertTrue($entreLocales->admiteEstablecimientoLlegada());
        self::assertTrue($entreLocales->exigeCodigoEstablecimiento());
    }

    /** Ningún otro motivo exige los códigos: solo el traslado entre locales propios. */
    #[Test]
    public function solo_el_traslado_entre_locales_exige_los_codigos(): void
    {
        foreach (MotivoTraslado::cases() as $motivo) {
            self::assertSame(
                $motivo === MotivoTraslado::TRASLADO_ENTRE_ESTABLECIMIENTOS,
                $motivo->exigeCodigoEstablecimiento(),
            );
        }
    }

    /**
     * Importación y zona primaria piden datos aduaneros que todavía no se recogen.
     * Ofrecerlos en un desplegable sería una trampa: se rechazarían siempre.
     */
    #[Test]
    public function los_motivos_aduaneros_no_se_ofrecen_todavia(): void
    {
        self::assertFalse(MotivoTraslado::IMPORTACION->soportado());
        self::assertFalse(MotivoTraslado::ZONA_PRIMARIA->soportado());

        $soportados = MotivoTraslado::soportados();

        self::assertNotContains(MotivoTraslado::IMPORTACION, $soportados);
        self::assertNotContains(MotivoTraslado::ZONA_PRIMARIA, $soportados);
        self::assertContains(MotivoTraslado::VENTA, $soportados);
        self::assertCount(count(MotivoTraslado::cases()) - 2, $soportados);
    }

    /** Solo «otros» obliga a explicarse; es el comodín y ese es su precio. */
    #[Test]
    public function solo_otros_exige_descripcion(): void
    {
        foreach (MotivoTraslado::cases() as $motivo) {
            self::assertSame($motivo === MotivoTraslado::OTROS, $motivo->exigeDescripcion());
        }
    }

    /** Facturar a uno y entregar a otro es el único caso con dos partes distintas. */
    #[Test]
    public function solo_la_entrega_a_terceros_lleva_comprador_aparte(): void
    {
        foreach (MotivoTraslado::cases() as $motivo) {
            self::assertSame(
                $motivo === MotivoTraslado::VENTA_ENTREGA_TERCEROS,
                $motivo->exigeComprador(),
            );
        }
    }

    #[Test]
    public function todos_los_motivos_saben_como_se_llaman(): void
    {
        foreach (MotivoTraslado::cases() as $motivo) {
            self::assertNotSame('', trim($motivo->etiqueta()));
            self::assertNotSame('', trim($motivo->reglaDestinatario()->ayuda()));
        }
    }
}
