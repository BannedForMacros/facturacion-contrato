<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Dto;

use MacSoft\Facturacion\Contrato\Enum\TipoComprobante;

/**
 * El documento fiscal ya numerado.
 *
 * `serie`, `numero` y `qr` llegan de inmediato aunque el envío a SUNAT sea
 * asíncrono: es lo que permite imprimir el ticket en caja sin esperar. El `hash`
 * llega después, con el CDR, y no hace falta para el QR.
 */
final readonly class Comprobante
{
    public function __construct(
        public TipoComprobante $tipo,
        public string $serie,
        public int $numero,
        public string $numeroCompleto,
        public ?string $fechaEmision = null,
        public ?string $hash = null,
        public ?string $qr = null,
        public array $enlaces = [],
    ) {}

    public static function desdeArray(array $datos): self
    {
        $tipo = $datos['tipo'] ?? TipoComprobante::BOLETA->value;

        return new self(
            tipo: TipoComprobante::tryFrom((string) $tipo) ?? TipoComprobante::BOLETA,
            serie: (string) ($datos['serie'] ?? ''),
            numero: (int) ($datos['numero'] ?? 0),
            numeroCompleto: (string) ($datos['numero_completo'] ?? ''),
            fechaEmision: $datos['fecha_emision'] ?? null,
            hash: $datos['hash'] ?? null,
            qr: $datos['qr'] ?? null,
            enlaces: $datos['enlaces'] ?? [],
        );
    }

    public function aArray(): array
    {
        return array_filter([
            'tipo'            => $this->tipo->value,
            'serie'           => $this->serie,
            'numero'          => $this->numero,
            'numero_completo' => $this->numeroCompleto,
            'fecha_emision'   => $this->fechaEmision,
            'hash'            => $this->hash,
            'qr'              => $this->qr,
            'enlaces'         => $this->enlaces !== [] ? $this->enlaces : null,
        ], static fn ($v) => $v !== null);
    }
}
