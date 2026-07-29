<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Dto;

/**
 * Algo que el sistema origen debe saber, pero que NO impidió emitir.
 *
 * Existe porque sin este canal los ajustes silenciosos son invisibles: un céntimo
 * de redondeo o una unidad que cayó al valor por defecto no rompen nada hoy, pero
 * reaparecen semanas después como un descuadre que nadie sabe explicar.
 */
final readonly class Aviso
{
    public function __construct(
        public string $codigo,
        public string $mensaje,
        public ?int $item = null,
    ) {}

    public static function desdeArray(array $datos): self
    {
        return new self(
            (string) ($datos['codigo'] ?? 'aviso'),
            (string) ($datos['mensaje'] ?? ''),
            isset($datos['item']) ? (int) $datos['item'] : null,
        );
    }

    public function aArray(): array
    {
        return array_filter([
            'codigo'  => $this->codigo,
            'mensaje' => $this->mensaje,
            'item'    => $this->item,
        ], static fn ($v) => $v !== null);
    }
}
