<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Dto;

use DateTimeImmutable;
use MacSoft\Facturacion\Contrato\Excepcion\ContratoInvalidoException;

/**
 * Una guía de remisión del remitente, lista para emitir.
 *
 * Es el hecho logístico, no el documento fiscal: qué se mueve, por qué, desde dónde
 * y en qué vehículo. La serie, el correlativo, el XML y el envío por la API de
 * SUNAT son competencia del emisor.
 *
 * POR QUÉ NO CUELGA DE `Venta`: porque las dos cosas son independientes de verdad.
 * Se despacha sin factura (un traslado entre almacenes propios no tiene venta) y se
 * factura sin despachar (el cliente se lo lleva en el momento). Una sola venta puede
 * necesitar tres guías si se entrega en tres viajes, y una guía puede amparar cinco
 * facturas del mismo cliente en una sola salida de camión. Atarlas obligaría a
 * inventar una de las dos cada vez que falta.
 *
 * NO LLEVA IMPORTES, y es deliberado: una guía no es un documento de valor. Ver
 * `ItemGuia`.
 */
final readonly class Guia
{
    /**
     * @param ItemGuia[]               $items
     * @param ComprobanteRelacionado[] $comprobantes
     */
    public function __construct(
        public string $idempotencyKey,
        public string $referenciaExterna,
        public Traslado $traslado,
        public array $items,
        /** Quién recibe. Se omite cuando la mercadería se queda en casa. */
        public ?Cliente $destinatario = null,
        /** Quién compra, cuando no es quien recibe. Solo en venta con entrega a terceros. */
        public ?Cliente $comprador = null,
        public array $comprobantes = [],
        public ?string $serie = null,
        public ?DateTimeImmutable $fechaEmision = null,
        public ?string $observaciones = null,
    ) {
        if (trim($idempotencyKey) === '') {
            throw ContratoInvalidoException::campo(
                'idempotency_key',
                'La clave de idempotencia es obligatoria: sin ella un reintento de red emite dos guías.',
            );
        }

        if (trim($referenciaExterna) === '') {
            throw ContratoInvalidoException::campo(
                'referencia_externa',
                'La referencia externa es obligatoria y debe ser única: es lo que permite saber '
                    . 'de qué despacho salió cada guía meses después.',
            );
        }

        if ($items === []) {
            throw ContratoInvalidoException::campo('items', 'La guía necesita al menos una línea.');
        }

        foreach ($items as $i => $item) {
            if (! $item instanceof ItemGuia) {
                throw ContratoInvalidoException::campo("items.{$i}", 'Cada línea debe ser un ItemGuia.');
            }
        }

        foreach ($comprobantes as $i => $comprobante) {
            if (! $comprobante instanceof ComprobanteRelacionado) {
                throw ContratoInvalidoException::campo(
                    "comprobantes.{$i}",
                    'Cada comprobante relacionado debe ser un ComprobanteRelacionado.',
                );
            }
        }

        $this->exigirPartesSegunMotivo();
    }

    /**
     * Que las partes informadas se correspondan con el motivo.
     *
     * Igual que en `Traslado`, se comprueba que no falte Y que no sobre. Un
     * destinatario en un traslado entre locales propios significa casi siempre que el
     * sistema origen copió el cliente de la venta anterior, y SUNAT responde a eso
     * con el error 2554 —«el destinatario debe ser igual al remitente»— cuando ya no
     * sirve de nada.
     */
    private function exigirPartesSegunMotivo(): void
    {
        $motivo = $this->traslado->motivo;

        if ($motivo->destinatarioEsElRemitente()) {
            if ($this->destinatario !== null) {
                throw ContratoInvalidoException::campo(
                    'destinatario',
                    "Con el motivo «{$motivo->etiqueta()}» la mercadería no cambia de dueño: "
                        . 'el destinatario es la propia empresa y lo pone el emisor.',
                );
            }
        } elseif ($this->destinatario === null) {
            throw ContratoInvalidoException::campo(
                'destinatario',
                "Falta el destinatario: con el motivo «{$motivo->etiqueta()}» hay que decir quién recibe.",
            );
        }

        if ($motivo->exigeComprador()) {
            if ($this->comprador === null) {
                throw ContratoInvalidoException::campo(
                    'comprador',
                    'En una venta con entrega a terceros hay que informar también a quién se le factura.',
                );
            }
        } elseif ($this->comprador !== null) {
            throw ContratoInvalidoException::campo(
                'comprador',
                "Con el motivo «{$motivo->etiqueta()}» quien recibe es quien compra: sobra el comprador aparte.",
            );
        }
    }

    /**
     * Peso que suman las líneas, si todas lo traen.
     *
     * Sirve para proponerlo en pantalla y no para sustituir al peso del traslado: la
     * cifra que se declara es la que el usuario confirma, porque el bruto incluye
     * embalaje, parihuelas y lo que el sistema no sabe.
     */
    public function pesoSugerido(): ?float
    {
        $suma = 0.0;

        foreach ($this->items as $item) {
            if ($item->peso === null) {
                return null;
            }

            $suma += $item->peso * $item->cantidad;
        }

        return round($suma, 3);
    }

    public static function desdeArray(array $datos): self
    {
        if (! isset($datos['traslado']) || ! is_array($datos['traslado'])) {
            throw ContratoInvalidoException::campo('traslado', 'Falta el bloque de traslado.');
        }

        if (! isset($datos['items']) || ! is_array($datos['items']) || $datos['items'] === []) {
            throw ContratoInvalidoException::campo('items', 'La guía necesita al menos una línea.');
        }

        $fecha = null;

        if (! empty($datos['fecha_emision'])) {
            $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $datos['fecha_emision']) ?: null;

            if ($fecha === null) {
                throw ContratoInvalidoException::campo('fecha_emision', 'La fecha de emisión debe ser YYYY-MM-DD.');
            }
        }

        $comprobantes = $datos['comprobantes'] ?? [];

        if (! is_array($comprobantes)) {
            throw ContratoInvalidoException::campo(
                'comprobantes',
                'Los comprobantes relacionados son una lista.',
            );
        }

        return new self(
            idempotencyKey: (string) ($datos['idempotency_key'] ?? ''),
            referenciaExterna: (string) ($datos['referencia_externa'] ?? ''),
            traslado: Traslado::desdeArray($datos['traslado']),
            items: array_map(
                static function (mixed $i, int $n): ItemGuia {
                    if (! is_array($i)) {
                        throw ContratoInvalidoException::campo("items.{$n}", 'Cada línea debe ser un objeto.');
                    }

                    return ItemGuia::desdeArray($i);
                },
                array_values($datos['items']),
                array_keys(array_values($datos['items'])),
            ),
            destinatario: isset($datos['destinatario']) && is_array($datos['destinatario'])
                ? Cliente::desdeArray($datos['destinatario'])
                : null,
            comprador: isset($datos['comprador']) && is_array($datos['comprador'])
                ? Cliente::desdeArray($datos['comprador'])
                : null,
            comprobantes: array_map(
                static function (mixed $c, int $n): ComprobanteRelacionado {
                    if (! is_array($c)) {
                        throw ContratoInvalidoException::campo(
                            "comprobantes.{$n}",
                            'Cada comprobante relacionado debe ser un objeto.',
                        );
                    }

                    return ComprobanteRelacionado::desdeArray($c);
                },
                array_values($comprobantes),
                array_keys(array_values($comprobantes)),
            ),
            serie: $datos['serie'] ?? null,
            fechaEmision: $fecha,
            observaciones: $datos['observaciones'] ?? null,
        );
    }

    public function aArray(): array
    {
        return array_filter([
            'idempotency_key'    => $this->idempotencyKey,
            'referencia_externa' => $this->referenciaExterna,
            'traslado'           => $this->traslado->aArray(),
            'items'              => array_map(static fn (ItemGuia $i) => $i->aArray(), $this->items),
            'destinatario'       => $this->destinatario?->aArray(),
            'comprador'          => $this->comprador?->aArray(),
            'comprobantes'       => $this->comprobantes === []
                ? null
                : array_map(static fn (ComprobanteRelacionado $c) => $c->aArray(), $this->comprobantes),
            'serie'              => $this->serie,
            'fecha_emision'      => $this->fechaEmision?->format('Y-m-d'),
            'observaciones'      => $this->observaciones,
        ], static fn ($v) => $v !== null);
    }
}
