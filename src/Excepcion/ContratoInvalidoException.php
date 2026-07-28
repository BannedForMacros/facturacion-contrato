<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Excepcion;

use InvalidArgumentException;

/**
 * El payload no cumple el contrato.
 *
 * Se lanza al CONSTRUIR el DTO, no al enviarlo: cuanto antes falle, más barato es.
 * Detectarlo aquí evita descubrirlo en el rechazo de SUNAT, cuando ya se consumió
 * un correlativo y hay que dar explicaciones.
 *
 * Lleva `campo` para que el sistema origen pueda señalar exactamente qué corregir
 * en pantalla, en vez de mostrar un mensaje genérico.
 */
class ContratoInvalidoException extends InvalidArgumentException
{
    public function __construct(
        string $mensaje,
        public readonly ?string $campo = null,
    ) {
        parent::__construct($mensaje);
    }

    public static function campo(string $campo, string $mensaje): self
    {
        return new self($mensaje, $campo);
    }
}
