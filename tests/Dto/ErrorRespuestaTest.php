<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Tests\Dto;

use MacSoft\Facturacion\Contrato\Dto\ErrorRespuesta;
use MacSoft\Facturacion\Contrato\Enum\CodigoError;
use MacSoft\Facturacion\Contrato\Excepcion\ContratoInvalidoException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ErrorRespuestaTest extends TestCase
{
    #[Test]
    public function deserializa_el_error_de_ejemplo_del_contrato(): void
    {
        $error = ErrorRespuesta::desdeArray([
            'error'        => 'cliente_sin_ruc',
            'mensaje'      => 'Una factura requiere un cliente con RUC.',
            'campo'        => 'cliente.documento.tipo',
            'solucion'     => 'Registra el RUC del cliente o emite una boleta.',
            'reintentable' => false,
        ]);

        self::assertSame(CodigoError::CLIENTE_SIN_RUC, $error->error);
        self::assertSame('cliente.documento.tipo', $error->campo);
        self::assertFalse($error->esReintentable());
        self::assertSame(422, $error->httpStatus());
    }

    /** El emisor manda: conoce la situación concreta y puede saber algo que el catálogo no. */
    #[Test]
    public function el_valor_de_la_respuesta_pesa_mas_que_el_catalogo(): void
    {
        $error = ErrorRespuesta::desdeArray([
            'error'        => 'rechazado_por_sunat',
            'mensaje'      => 'SUNAT devolvió un error temporal de su lado.',
            'reintentable' => true,
        ]);

        self::assertFalse($error->error->esReintentable());
        self::assertTrue($error->esReintentable());
    }

    /**
     * Sin el campo, se cae al catálogo — nunca a `false`.
     *
     * Dar por definitivo un `sunat_no_disponible` pierde la venta sin que nadie se
     * entere de que se perdió.
     */
    #[Test]
    public function si_no_llega_el_campo_manda_el_catalogo(): void
    {
        $caida = ErrorRespuesta::desdeArray([
            'error'   => 'sunat_no_disponible',
            'mensaje' => 'No se pudo contactar con SUNAT.',
        ]);

        self::assertNull($caida->reintentable);
        self::assertTrue($caida->esReintentable());

        $dato = ErrorRespuesta::desdeArray(['error' => 'cliente_sin_ruc', 'mensaje' => 'Falta el RUC.']);
        self::assertFalse($dato->esReintentable());
    }

    /**
     * Un código que este paquete no conoce todavía NO puede tumbar al consumidor: el
     * catálogo es aditivo y el emisor puede ir por delante de la dependencia.
     */
    #[Test]
    public function un_codigo_desconocido_degrada_en_vez_de_romper(): void
    {
        $error = ErrorRespuesta::desdeArray([
            'error'        => 'certificado_vencido',
            'mensaje'      => 'El certificado digital caducó.',
            'reintentable' => false,
        ]);

        self::assertSame(CodigoError::ERROR_INTERNO, $error->error);
        self::assertSame('El certificado digital caducó.', $error->mensaje);
        // Y la decisión de la cola sigue siendo la correcta porque manda la respuesta.
        self::assertFalse($error->esReintentable());
    }

    #[Test]
    public function un_error_sin_mensaje_no_se_construye(): void
    {
        $this->expectException(ContratoInvalidoException::class);

        new ErrorRespuesta(CodigoError::DATOS_INVALIDOS, '  ');
    }

    #[Test]
    public function la_ida_y_vuelta_conserva_el_error(): void
    {
        $error = new ErrorRespuesta(
            CodigoError::TOTALES_NO_CUADRAN,
            'El total declarado (75.00) no coincide con el calculado (74.20).',
            'total',
            'Revisa los descuentos por línea antes de reenviar.',
            false,
        );

        self::assertEquals($error, ErrorRespuesta::desdeArray($error->aArray()));
    }
}
