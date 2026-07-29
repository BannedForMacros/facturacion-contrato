<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Dto;

/** Desglose fiscal que devuelve el emisor tras calcular la venta. */
final readonly class Totales
{
    public function __construct(
        public float $gravado = 0.0,
        public float $exonerado = 0.0,
        public float $inafecto = 0.0,
        public float $gratuito = 0.0,
        public float $descuento = 0.0,
        public float $impuesto = 0.0,
        public float $total = 0.0,
    ) {}

    public static function desdeArray(array $datos): self
    {
        return new self(
            gravado: (float) ($datos['gravado'] ?? 0),
            exonerado: (float) ($datos['exonerado'] ?? 0),
            inafecto: (float) ($datos['inafecto'] ?? 0),
            gratuito: (float) ($datos['gratuito'] ?? 0),
            descuento: (float) ($datos['descuento'] ?? 0),
            impuesto: (float) ($datos['impuesto'] ?? 0),
            total: (float) ($datos['total'] ?? 0),
        );
    }

    public function aArray(): array
    {
        return [
            'gravado'   => $this->gravado,
            'exonerado' => $this->exonerado,
            'inafecto'  => $this->inafecto,
            'gratuito'  => $this->gratuito,
            'descuento' => $this->descuento,
            'impuesto'  => $this->impuesto,
            'total'     => $this->total,
        ];
    }
}
