<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Enum;

/**
 * Por qué se emite una nota de crédito.
 *
 * Sin códigos de catálogo: la traducción al catálogo 09 de SUNAT vive en el emisor.
 * `DEVOLUCION` cubre tanto la total como la parcial; cuál de las dos es se deduce de
 * si la nota lleva líneas o no, no de un motivo distinto.
 */
enum MotivoNotaCredito: string
{
    case ANULACION = 'anulacion';
    case ERROR_RUC = 'error_ruc';
    case ERROR_DESCRIPCION = 'error_descripcion';
    case DESCUENTO = 'descuento';
    case DEVOLUCION = 'devolucion';
    case ERROR_MONTO = 'error_monto';

    /**
     * ¿Deja el comprobante original sin efecto por completo?
     *
     * Solo cuando además no se detallan líneas. Una devolución parcial acredita una
     * parte y el original sigue vigente por el resto.
     */
    public function anulaTotalmente(): bool
    {
        return $this === self::ANULACION;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::ANULACION         => 'Anulación de la operación',
            self::ERROR_RUC         => 'Error en el RUC',
            self::ERROR_DESCRIPCION => 'Error en la descripción',
            self::DESCUENTO         => 'Descuento',
            self::DEVOLUCION        => 'Devolución',
            self::ERROR_MONTO       => 'Error en el monto',
        };
    }
}
