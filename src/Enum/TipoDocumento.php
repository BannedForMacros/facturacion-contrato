<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Enum;

/**
 * Documento de identidad del adquirente.
 *
 * Se nombran, no se numeran: que RUC sea el `6` del catálogo 06 de SUNAT es un
 * detalle interno del emisor. Además un código equivocado es un error silencioso,
 * mientras que un nombre equivocado lo caza la validación.
 */
enum TipoDocumento: string
{
    case DNI = 'DNI';
    case RUC = 'RUC';
    case CE = 'CE';
    case PASAPORTE = 'PASAPORTE';

    /** Cliente genérico de mostrador. Solo válido en boleta y bajo el umbral legal. */
    case SIN_DOCUMENTO = 'SIN_DOCUMENTO';

    /** ¿Hay que enviar un número de documento? */
    public function requiereNumero(): bool
    {
        return $this !== self::SIN_DOCUMENTO;
    }

    /**
     * ¿Identifica a una empresa?
     *
     * Solo con RUC se puede emitir factura: SUNAT rechaza cualquier factura cuyo
     * adquirente no lo tenga.
     */
    public function esEmpresa(): bool
    {
        return $this === self::RUC;
    }

    /** ¿Identifica al adquirente ante SUNAT? Relevante para el umbral de boleta. */
    public function identifica(): bool
    {
        return $this !== self::SIN_DOCUMENTO;
    }

    /** Longitud esperada del número, o null si no está fijada. */
    public function longitud(): ?int
    {
        return match ($this) {
            self::DNI => 8,
            self::RUC => 11,
            default   => null,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::DNI           => 'DNI',
            self::RUC           => 'RUC',
            self::CE            => 'Carné de extranjería',
            self::PASAPORTE     => 'Pasaporte',
            self::SIN_DOCUMENTO => 'Sin documento',
        };
    }
}
