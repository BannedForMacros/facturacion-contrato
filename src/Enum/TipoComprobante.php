<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Enum;

/**
 * Qué documento se quiere emitir.
 *
 * `AUTO` es el valor por defecto a propósito: el sistema origen no debería tener que
 * decidirlo. La regla es estable y acierta prácticamente siempre — con RUC solo cabe
 * factura, y sin él solo cabe boleta —, así que se resuelve aquí una vez en lugar de
 * repetirla en cada adaptador. Quien necesite forzarlo, lo fuerza.
 */
enum TipoComprobante: string
{
    case AUTO = 'auto';
    case FACTURA = 'factura';
    case BOLETA = 'boleta';

    /**
     * Resuelve `AUTO` a partir del documento del adquirente.
     *
     * Si ya viene decidido, se respeta: la validación posterior dirá si la
     * combinación es legal (una factura sin RUC, por ejemplo).
     */
    public function resolver(TipoDocumento $documento): self
    {
        if ($this !== self::AUTO) {
            return $this;
        }

        return $documento->esEmpresa() ? self::FACTURA : self::BOLETA;
    }

    /** ¿Exige adquirente con RUC y dirección fiscal? */
    public function exigeRuc(): bool
    {
        return $this === self::FACTURA;
    }

    /**
     * ¿Está sujeto al umbral de identificación del adquirente?
     *
     * Solo la boleta: desde cierto importe SUNAT deja de admitir "cliente varios".
     * La factura ya identifica siempre por RUC.
     */
    public function sujetoAUmbral(): bool
    {
        return $this === self::BOLETA;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::AUTO    => 'Automático',
            self::FACTURA => 'Factura',
            self::BOLETA  => 'Boleta de venta',
        };
    }
}
