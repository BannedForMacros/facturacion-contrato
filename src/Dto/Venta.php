<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Dto;

use DateTimeImmutable;
use MacSoft\Facturacion\Contrato\Enum\TipoComprobante;
use MacSoft\Facturacion\Contrato\Excepcion\ContratoInvalidoException;

/**
 * Una venta lista para emitir.
 *
 * Es el hecho comercial, no el documento fiscal: qué se vendió, a quién y por
 * cuánto. Cómo se traduce eso a bases imponibles, catálogos y correlativos es
 * competencia del emisor.
 *
 * Las validaciones de aquí son las que NO dependen de la configuración de la
 * empresa (coherencia interna y reglas legales invariantes). Las que sí dependen
 * —el umbral de boleta identificada, la tasa vigente, la tolerancia de redondeo—
 * las aplica el emisor, que es quien conoce esa configuración.
 */
final readonly class Venta
{
    /** @param Item[] $items */
    public function __construct(
        public string $idempotencyKey,
        public string $referenciaExterna,
        public Cliente $cliente,
        public array $items,
        public float $total,
        public TipoComprobante $tipo = TipoComprobante::AUTO,
        public ?DateTimeImmutable $fechaEmision = null,
        public string $moneda = 'PEN',
        public ?string $serie = null,
        public float $descuentoGlobal = 0.0,
        public ?Pago $pago = null,
        public ?string $observaciones = null,
    ) {
        if (trim($idempotencyKey) === '') {
            throw ContratoInvalidoException::campo(
                'idempotency_key',
                'La clave de idempotencia es obligatoria: sin ella un reintento de red emite dos comprobantes.',
            );
        }

        if (trim($referenciaExterna) === '') {
            throw ContratoInvalidoException::campo(
                'referencia_externa',
                'La referencia externa es obligatoria y debe ser única: es lo que permite saber '
                    . 'de qué venta salió cada comprobante meses después.',
            );
        }

        if ($items === []) {
            throw ContratoInvalidoException::campo('items', 'La venta necesita al menos una línea.');
        }

        foreach ($items as $i => $item) {
            if (! $item instanceof Item) {
                throw ContratoInvalidoException::campo("items.{$i}", 'Cada línea debe ser un Item.');
            }
        }

        if ($total <= 0) {
            throw ContratoInvalidoException::campo(
                'total',
                'El total declarado debe ser mayor que cero.',
            );
        }

        if ($descuentoGlobal < 0) {
            throw ContratoInvalidoException::campo(
                'descuento_global',
                'El descuento global no puede ser negativo.',
            );
        }

        // Reglas legales que no dependen de configuración: una factura sin RUC o sin
        // dirección la rechaza SUNAT siempre. Cazarlo aquí evita quemar un correlativo.
        $resuelto = $tipo->resolver($cliente->documento->tipo);

        if ($resuelto->exigeRuc() && ! $cliente->aptoParaFactura()) {
            $falta = $cliente->documento->tipo->esEmpresa() ? 'la dirección fiscal' : 'un RUC';

            throw ContratoInvalidoException::campo(
                'cliente',
                "Una factura requiere un cliente con RUC y dirección; falta {$falta}.",
            );
        }
    }

    /** Tipo definitivo, con `AUTO` ya resuelto según el documento del adquirente. */
    public function tipoResuelto(): TipoComprobante
    {
        return $this->tipo->resolver($this->cliente->documento->tipo);
    }

    /** Suma bruta de las líneas que el cliente paga. Lo gratuito no cuenta. */
    public function importeItems(): float
    {
        $suma = 0.0;

        foreach ($this->items as $item) {
            if ($item->impuesto->sumaAlTotal()) {
                $suma += $item->importe();
            }
        }

        return round($suma, 2);
    }

    public static function desdeArray(array $datos): self
    {
        if (! isset($datos['items']) || ! is_array($datos['items']) || $datos['items'] === []) {
            throw ContratoInvalidoException::campo('items', 'La venta necesita al menos una línea.');
        }

        $tipo = $datos['tipo'] ?? TipoComprobante::AUTO->value;

        if (! is_string($tipo) || TipoComprobante::tryFrom($tipo) === null) {
            throw ContratoInvalidoException::campo(
                'tipo',
                'Tipo de comprobante no reconocido: admite "auto", "factura" o "boleta".',
            );
        }

        $fecha = null;

        if (! empty($datos['fecha_emision'])) {
            $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $datos['fecha_emision']) ?: null;

            if ($fecha === null) {
                throw ContratoInvalidoException::campo('fecha_emision', 'La fecha de emisión debe ser YYYY-MM-DD.');
            }
        }

        return new self(
            idempotencyKey: (string) ($datos['idempotency_key'] ?? ''),
            referenciaExterna: (string) ($datos['referencia_externa'] ?? ''),
            cliente: Cliente::desdeArray($datos['cliente'] ?? []),
            // Se comprueba antes de mapear: un item que no es un objeto daría un
            // TypeError crudo, y el contrato promete que lo inválido sale siempre como
            // ContratoInvalidoException con su campo.
            items: array_map(
                static function (mixed $i, int $n): Item {
                    if (! is_array($i)) {
                        throw ContratoInvalidoException::campo("items.{$n}", 'Cada línea debe ser un objeto.');
                    }

                    return Item::desdeArray($i);
                },
                array_values($datos['items']),
                array_keys(array_values($datos['items'])),
            ),
            total: (float) ($datos['total'] ?? 0),
            tipo: TipoComprobante::from($tipo),
            fechaEmision: $fecha,
            moneda: strtoupper((string) ($datos['moneda'] ?? 'PEN')),
            serie: $datos['serie'] ?? null,
            descuentoGlobal: (float) ($datos['descuento_global'] ?? 0),
            pago: isset($datos['pago']) && is_array($datos['pago']) ? Pago::desdeArray($datos['pago']) : null,
            observaciones: $datos['observaciones'] ?? null,
        );
    }

    public function aArray(): array
    {
        return array_filter([
            'idempotency_key'    => $this->idempotencyKey,
            'referencia_externa' => $this->referenciaExterna,
            'cliente'            => $this->cliente->aArray(),
            'items'              => array_map(static fn (Item $i) => $i->aArray(), $this->items),
            'total'              => $this->total,
            'tipo'               => $this->tipo->value,
            'fecha_emision'      => $this->fechaEmision?->format('Y-m-d'),
            'moneda'             => $this->moneda,
            'serie'              => $this->serie,
            'descuento_global'   => $this->descuentoGlobal > 0 ? $this->descuentoGlobal : null,
            'pago'               => $this->pago?->aArray(),
            'observaciones'      => $this->observaciones,
        ], static fn ($v) => $v !== null);
    }
}
