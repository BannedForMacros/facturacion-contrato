<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Dto;

use MacSoft\Facturacion\Contrato\Enum\TipoComprobante;
use MacSoft\Facturacion\Contrato\Excepcion\ContratoInvalidoException;

/**
 * El comprobante que respalda el traslado.
 *
 * No es obligatorio —se puede despachar antes de facturar, y un traslado entre
 * locales propios no tiene comprobante ninguno—, pero cuando existe conviene que
 * viaje: es lo que permite reconstruir meses después qué guía amparó qué venta.
 *
 * Admite varios porque el caso es real: una sola salida de camión puede llevar
 * cinco facturas del mismo cliente.
 *
 * OJO — ESTO NO ES `Comprobante`. Aquél es el documento que el emisor devuelve ya
 * numerado; éste es una referencia a uno que ya existe y del que aquí solo se
 * conoce cómo se llama.
 */
final readonly class ComprobanteRelacionado
{
    public function __construct(
        public TipoComprobante $tipo,
        public string $serie,
        public int $numero,
    ) {
        if ($tipo === TipoComprobante::AUTO) {
            throw ContratoInvalidoException::campo(
                'comprobantes.tipo',
                'Un comprobante que ya existe tiene tipo conocido: «factura» o «boleta», nunca «auto».',
            );
        }

        if (trim($serie) === '') {
            throw ContratoInvalidoException::campo(
                'comprobantes.serie',
                'Falta la serie del comprobante relacionado.',
            );
        }

        if ($numero <= 0) {
            throw ContratoInvalidoException::campo(
                'comprobantes.numero',
                'El número del comprobante relacionado debe ser mayor que cero.',
            );
        }
    }

    /** Como se lee en el documento: F001-00000123. */
    public function numeroCompleto(): string
    {
        return strtoupper(trim($this->serie)) . '-' . str_pad((string) $this->numero, 8, '0', STR_PAD_LEFT);
    }

    public static function desdeArray(array $datos): self
    {
        $tipo = $datos['tipo'] ?? null;

        if (! is_string($tipo) || TipoComprobante::tryFrom($tipo) === null) {
            throw ContratoInvalidoException::campo(
                'comprobantes.tipo',
                'Tipo de comprobante relacionado no reconocido: admite «factura» o «boleta».',
            );
        }

        return new self(
            tipo: TipoComprobante::from($tipo),
            serie: strtoupper(trim((string) ($datos['serie'] ?? ''))),
            numero: (int) ($datos['numero'] ?? 0),
        );
    }

    public function aArray(): array
    {
        return [
            'tipo'   => $this->tipo->value,
            'serie'  => $this->serie,
            'numero' => $this->numero,
        ];
    }
}
