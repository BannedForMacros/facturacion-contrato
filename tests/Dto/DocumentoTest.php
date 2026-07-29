<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Tests\Dto;

use MacSoft\Facturacion\Contrato\Dto\Documento;
use MacSoft\Facturacion\Contrato\Enum\TipoDocumento;
use MacSoft\Facturacion\Contrato\Excepcion\ContratoInvalidoException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Un documento mal formado lo rechaza SUNAT, pero lo rechaza TARDE: cuando ya se
 * consumió un correlativo. Validar la longitud aquí cuesta una línea y ahorra una
 * nota de crédito.
 */
final class DocumentoTest extends TestCase
{
    #[Test]
    public function acepta_un_dni_de_ocho_digitos(): void
    {
        $documento = new Documento(TipoDocumento::DNI, '46812345');

        self::assertSame('46812345', $documento->numero);
        self::assertTrue($documento->tipo->identifica());
    }

    #[Test]
    public function rechaza_un_dni_de_siete_digitos(): void
    {
        $this->expectException(ContratoInvalidoException::class);
        $this->expectExceptionMessageMatches('/8 dígitos/');

        new Documento(TipoDocumento::DNI, '4681234');
    }

    #[Test]
    public function acepta_un_ruc_de_once_digitos(): void
    {
        self::assertSame('20601030013', (new Documento(TipoDocumento::RUC, '20601030013'))->numero);
    }

    #[Test]
    public function rechaza_un_ruc_con_letras(): void
    {
        $this->expectException(ContratoInvalidoException::class);
        $this->expectExceptionMessageMatches('/dígitos/');

        new Documento(TipoDocumento::RUC, '2060103001A');
    }

    #[Test]
    public function rechaza_un_ruc_de_longitud_equivocada(): void
    {
        $this->expectException(ContratoInvalidoException::class);

        new Documento(TipoDocumento::RUC, '206010300');
    }

    /** El cliente de mostrador no tiene número, y exigírselo bloquearía la caja. */
    #[Test]
    public function sin_documento_no_exige_numero(): void
    {
        $documento = Documento::sinDocumento();

        self::assertSame(TipoDocumento::SIN_DOCUMENTO, $documento->tipo);
        self::assertNull($documento->numero);
        self::assertFalse($documento->tipo->identifica());
        self::assertSame(['tipo' => 'SIN_DOCUMENTO'], $documento->aArray());
    }

    #[Test]
    public function los_demas_tipos_exigen_numero_pero_no_longitud_fija(): void
    {
        // El carné de extranjería y el pasaporte no tienen longitud normalizada:
        // exigir una rechazaría documentos válidos.
        self::assertSame('X-4451', (new Documento(TipoDocumento::CE, 'X-4451'))->numero);
        self::assertNull(TipoDocumento::PASAPORTE->longitud());

        $this->expectException(ContratoInvalidoException::class);

        new Documento(TipoDocumento::PASAPORTE, '   ');
    }

    #[Test]
    public function el_campo_del_error_senala_donde_corregir(): void
    {
        try {
            new Documento(TipoDocumento::DNI, '1');
            self::fail('Debería haber lanzado.');
        } catch (ContratoInvalidoException $e) {
            self::assertSame('cliente.documento.numero', $e->campo);
        }
    }

    /**
     * Un RUC en JSON llega como número más veces de las que gustaría.
     *
     * Debe seguir siendo una excepción del contrato (con su `campo`), nunca un
     * TypeError crudo que el consumidor no sabe atrapar.
     */
    #[Test]
    public function un_numero_que_llega_como_entero_se_normaliza(): void
    {
        $documento = Documento::desdeArray(['tipo' => 'RUC', 'numero' => 20601030013]);

        self::assertSame('20601030013', $documento->numero);
    }

    #[Test]
    public function un_numero_que_no_es_texto_ni_cifra_lanza_la_excepcion_del_contrato(): void
    {
        try {
            Documento::desdeArray(['tipo' => 'RUC', 'numero' => ['20601030013']]);
            self::fail('Debería haber lanzado.');
        } catch (ContratoInvalidoException $e) {
            self::assertSame('cliente.documento.numero', $e->campo);
        }
    }

    #[Test]
    public function un_tipo_desconocido_no_se_convierte_en_algo_plausible(): void
    {
        $this->expectException(ContratoInvalidoException::class);

        Documento::desdeArray(['tipo' => 'CARNET_MILITAR', 'numero' => '123']);
    }
}
