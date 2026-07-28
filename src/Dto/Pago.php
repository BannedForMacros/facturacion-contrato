<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Dto;

use DateTimeImmutable;
use MacSoft\Facturacion\Contrato\Enum\CondicionPago;
use MacSoft\Facturacion\Contrato\Excepcion\ContratoInvalidoException;

final readonly class Pago
{
    public function __construct(
        public CondicionPago $condicion = CondicionPago::CONTADO,
        public ?DateTimeImmutable $vence = null,
        public float $adelanto = 0.0,
    ) {
        if ($condicion->exigeVencimiento() && $vence === null) {
            throw ContratoInvalidoException::campo(
                'pago.vence',
                'Una venta a crédito necesita fecha de vencimiento.',
            );
        }

        if ($adelanto < 0) {
            throw ContratoInvalidoException::campo('pago.adelanto', 'El adelanto no puede ser negativo.');
        }
    }

    public static function contado(): self
    {
        return new self();
    }

    public static function credito(DateTimeImmutable $vence, float $adelanto = 0.0): self
    {
        return new self(CondicionPago::CREDITO, $vence, $adelanto);
    }

    public static function desdeArray(array $datos): self
    {
        $condicion = $datos['condicion'] ?? CondicionPago::CONTADO->value;

        if (! is_string($condicion) || CondicionPago::tryFrom($condicion) === null) {
            throw ContratoInvalidoException::campo(
                'pago.condicion',
                'Condición de pago no reconocida: admite "contado" o "credito".',
            );
        }

        $vence = null;

        if (! empty($datos['vence'])) {
            $vence = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $datos['vence']) ?: null;

            if ($vence === null) {
                throw ContratoInvalidoException::campo('pago.vence', 'La fecha de vencimiento debe ser YYYY-MM-DD.');
            }
        }

        return new self(CondicionPago::from($condicion), $vence, (float) ($datos['adelanto'] ?? 0));
    }

    public function aArray(): array
    {
        return array_filter([
            'condicion' => $this->condicion->value,
            'vence'     => $this->vence?->format('Y-m-d'),
            'adelanto'  => $this->adelanto > 0 ? $this->adelanto : null,
        ], static fn ($v) => $v !== null);
    }
}
