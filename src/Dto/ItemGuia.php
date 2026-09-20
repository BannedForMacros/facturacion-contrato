<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Dto;

use MacSoft\Facturacion\Contrato\Excepcion\ContratoInvalidoException;

/**
 * Una línea de la guía: qué se mueve y cuánto.
 *
 * NO ES `Item` Y NO DEBE REUTILIZARLO. Una guía no lleva precios, ni impuestos, ni
 * descuentos: no es un documento de valor, es un documento de movimiento. Meterle
 * un precio sería invitar a que alguien lo pinte en la representación impresa, y
 * una guía con importes es exactamente lo que no debe viajar en la cabina de un
 * camión —la mercadería va con su guía, y lo que valga es asunto de la factura.
 *
 * El peso opcional por línea existe para una sola cosa: proponer el peso total del
 * traslado sin que nadie lo sume a mano. La cifra que manda es siempre la del
 * traslado, que el usuario puede corregir.
 */
final readonly class ItemGuia
{
    public function __construct(
        public string $descripcion,
        public float $cantidad,
        public string $unidad = 'NIU',
        public ?string $codigo = null,
        /** Peso de esta línea, si se conoce. Solo sirve para proponer el total. */
        public ?float $peso = null,
    ) {
        if (trim($descripcion) === '') {
            throw ContratoInvalidoException::campo(
                'items.descripcion',
                'Cada línea de la guía necesita una descripción.',
            );
        }

        if (mb_strlen($descripcion) > 500) {
            throw ContratoInvalidoException::campo(
                'items.descripcion',
                'La descripción no puede superar los 500 caracteres.',
            );
        }

        if ($cantidad <= 0) {
            throw ContratoInvalidoException::campo(
                'items.cantidad',
                "La cantidad debe ser mayor que cero (llegó {$cantidad}) en «{$descripcion}».",
            );
        }

        if (trim($unidad) === '') {
            throw ContratoInvalidoException::campo(
                'items.unidad',
                "Falta la unidad de medida en «{$descripcion}».",
            );
        }

        if ($peso !== null && $peso < 0) {
            throw ContratoInvalidoException::campo(
                'items.peso',
                "El peso no puede ser negativo en «{$descripcion}».",
            );
        }
    }

    public static function desdeArray(array $datos): self
    {
        return new self(
            descripcion: (string) ($datos['descripcion'] ?? ''),
            cantidad: (float) ($datos['cantidad'] ?? 0),
            unidad: (string) ($datos['unidad'] ?? 'NIU'),
            codigo: $datos['codigo'] ?? null,
            peso: isset($datos['peso']) ? (float) $datos['peso'] : null,
        );
    }

    public function aArray(): array
    {
        return array_filter([
            'descripcion' => $this->descripcion,
            'cantidad'    => $this->cantidad,
            'unidad'      => $this->unidad,
            'codigo'      => $this->codigo,
            'peso'        => $this->peso,
        ], static fn ($v) => $v !== null);
    }
}
