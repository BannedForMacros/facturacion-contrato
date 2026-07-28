<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Dto;

use MacSoft\Facturacion\Contrato\Enum\TipoDocumento;
use MacSoft\Facturacion\Contrato\Excepcion\ContratoInvalidoException;

/** Documento de identidad del adquirente. */
final readonly class Documento
{
    public function __construct(
        public TipoDocumento $tipo,
        public ?string $numero = null,
    ) {
        if (! $tipo->requiereNumero()) {
            return;
        }

        $limpio = trim((string) $numero);

        if ($limpio === '') {
            throw ContratoInvalidoException::campo(
                'cliente.documento.numero',
                "El número de {$tipo->etiqueta()} es obligatorio.",
            );
        }

        if (! ctype_digit($limpio) && ($tipo === TipoDocumento::DNI || $tipo === TipoDocumento::RUC)) {
            throw ContratoInvalidoException::campo(
                'cliente.documento.numero',
                "El {$tipo->etiqueta()} solo admite dígitos.",
            );
        }

        $esperada = $tipo->longitud();

        if ($esperada !== null && strlen($limpio) !== $esperada) {
            throw ContratoInvalidoException::campo(
                'cliente.documento.numero',
                "El {$tipo->etiqueta()} debe tener {$esperada} dígitos; llegaron " . strlen($limpio) . '.',
            );
        }
    }

    /** Cliente genérico de mostrador. */
    public static function sinDocumento(): self
    {
        return new self(TipoDocumento::SIN_DOCUMENTO);
    }

    public static function desdeArray(array $datos): self
    {
        $tipo = $datos['tipo'] ?? null;

        if (! is_string($tipo) || TipoDocumento::tryFrom($tipo) === null) {
            throw ContratoInvalidoException::campo(
                'cliente.documento.tipo',
                'Tipo de documento no reconocido. Valores admitidos: '
                    . implode(', ', array_column(TipoDocumento::cases(), 'value')) . '.',
            );
        }

        return new self(TipoDocumento::from($tipo), $datos['numero'] ?? null);
    }

    public function aArray(): array
    {
        return array_filter([
            'tipo'   => $this->tipo->value,
            'numero' => $this->numero,
        ], static fn ($v) => $v !== null);
    }
}
