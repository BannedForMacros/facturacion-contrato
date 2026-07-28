<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Enum;

enum CondicionPago: string
{
    case CONTADO = 'contado';
    case CREDITO = 'credito';

    /** El crédito exige fecha de vencimiento: SUNAT la necesita para la cuota. */
    public function exigeVencimiento(): bool
    {
        return $this === self::CREDITO;
    }

    public function etiqueta(): string
    {
        return $this === self::CONTADO ? 'Contado' : 'Crédito';
    }
}
