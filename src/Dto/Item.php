<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Dto;

use MacSoft\Facturacion\Contrato\Enum\Impuesto;
use MacSoft\Facturacion\Contrato\Excepcion\ContratoInvalidoException;

/**
 * Una línea de la venta.
 *
 * Solo tres datos son obligatorios: qué, cuánto y a qué precio. Todo lo demás tiene
 * un valor por defecto, porque cada campo obligatorio de más es una barrera para
 * conectar el siguiente sistema.
 *
 * OJO con los dos campos de impuesto, que responden a preguntas distintas:
 *   - `impuesto`               → ¿cómo tributa este producto?
 *   - `precioIncluyeImpuesto`  → ¿el precio que mando ya lo trae dentro?
 * Son ortogonales. Mezclarlos produce una clase entera de errores de importe.
 *
 * Este DTO NO calcula bases ni impuestos: eso es conocimiento fiscal y vive en el
 * emisor. Aquí solo se transporta el hecho comercial.
 */
final readonly class Item
{
    public function __construct(
        public string $descripcion,
        public float $cantidad,
        public float $precioUnitario,
        public ?string $codigo = null,
        public string $unidad = 'UND',
        public Impuesto $impuesto = Impuesto::GRAVADO,
        public bool $precioIncluyeImpuesto = true,
        public float $descuento = 0.0,
    ) {
        if (trim($descripcion) === '') {
            throw ContratoInvalidoException::campo('items.descripcion', 'Cada línea necesita una descripción.');
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

        if ($precioUnitario < 0) {
            throw ContratoInvalidoException::campo(
                'items.precio_unitario',
                "El precio no puede ser negativo en «{$descripcion}».",
            );
        }

        if ($descuento < 0) {
            throw ContratoInvalidoException::campo(
                'items.descuento',
                "El descuento no puede ser negativo en «{$descripcion}».",
            );
        }

        // Un descuento por encima del importe de la línea deja un importe negativo,
        // que SUNAT rechaza. Se caza aquí y no en el rechazo del CDR.
        if ($descuento > $precioUnitario * $cantidad) {
            throw ContratoInvalidoException::campo(
                'items.descuento',
                "El descuento supera el importe de la línea «{$descripcion}».",
            );
        }
    }

    /** Importe bruto de la línea, tal como se cobró. Sin interpretación fiscal. */
    public function importe(): float
    {
        return round($this->precioUnitario * $this->cantidad - $this->descuento, 2);
    }

    public static function desdeArray(array $datos): self
    {
        $impuesto = $datos['impuesto'] ?? Impuesto::GRAVADO->value;

        if (! is_string($impuesto) || Impuesto::tryFrom($impuesto) === null) {
            throw ContratoInvalidoException::campo(
                'items.impuesto',
                'Tipo de impuesto no reconocido. Valores admitidos: '
                    . implode(', ', array_column(Impuesto::cases(), 'value')) . '.',
            );
        }

        return new self(
            descripcion: (string) ($datos['descripcion'] ?? ''),
            cantidad: (float) ($datos['cantidad'] ?? 0),
            precioUnitario: (float) ($datos['precio_unitario'] ?? 0),
            codigo: $datos['codigo'] ?? null,
            unidad: (string) ($datos['unidad'] ?? 'UND'),
            impuesto: Impuesto::from($impuesto),
            precioIncluyeImpuesto: (bool) ($datos['precio_incluye_impuesto'] ?? true),
            descuento: (float) ($datos['descuento'] ?? 0),
        );
    }

    public function aArray(): array
    {
        return array_filter([
            'descripcion'             => $this->descripcion,
            'cantidad'                => $this->cantidad,
            'precio_unitario'         => $this->precioUnitario,
            'codigo'                  => $this->codigo,
            'unidad'                  => $this->unidad,
            'impuesto'                => $this->impuesto->value,
            'precio_incluye_impuesto' => $this->precioIncluyeImpuesto,
            'descuento'               => $this->descuento,
        ], static fn ($v) => $v !== null);
    }
}
