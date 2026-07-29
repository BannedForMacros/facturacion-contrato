<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Dto;

use MacSoft\Facturacion\Contrato\Enum\MotivoNotaCredito;
use MacSoft\Facturacion\Contrato\Excepcion\ContratoInvalidoException;

/**
 * Petición de nota de crédito sobre una venta ya emitida.
 *
 * LA REGLA QUE JUSTIFICA ESTA CLASE:
 *
 *   sin `items`  ⇒ nota TOTAL     — se acreditan los importes del original tal cual.
 *   con `items`  ⇒ nota PARCIAL   — y entonces `total` pasa a ser OBLIGATORIO.
 *
 * Nace de un bug real: una devolución parcial acreditaba la factura ENTERA porque el
 * emisor descartaba en silencio los importes enviados. Nadie lo notó hasta cuadrar el
 * mes, y para entonces había notas de crédito emitidas de más ante SUNAT.
 *
 * El fallo tenía dos mitades y aquí se cierran las dos:
 *   1. Que "con líneas" y "sin líneas" signifiquen cosas distintas queda explícito en
 *      `esParcial()`, no implícito en un `if` del emisor.
 *   2. Exigir `total` cuando hay líneas da un segundo dato con el que contrastar. Si
 *      el emisor calcula otra cosa, no emite; sin ese dato, una discrepancia se emite
 *      silenciosamente y solo se arregla con otra nota de crédito.
 *
 * Igual que en `Venta`, aquí NO se calculan impuestos ni se comprueba contra el
 * comprobante original: eso exige conocer el original y la configuración de la
 * empresa, y es competencia del emisor (`nota_credito_excede_original`).
 */
final readonly class NotaCredito
{
    /** @param Item[] $items Vacío = anulación total. Con líneas = devolución parcial. */
    public function __construct(
        public string $idempotencyKey,
        public MotivoNotaCredito $motivo,
        public ?string $observaciones = null,
        public array $items = [],
        public ?float $total = null,
    ) {
        if (trim($idempotencyKey) === '') {
            throw ContratoInvalidoException::campo(
                'idempotency_key',
                'La clave de idempotencia es obligatoria: sin ella un reintento de red '
                    . 'acredita dos veces la misma devolución.',
            );
        }

        foreach ($items as $i => $item) {
            if (! $item instanceof Item) {
                throw ContratoInvalidoException::campo("items.{$i}", 'Cada línea debe ser un Item.');
            }
        }

        // El corazón del bug: si se detallan líneas, la nota es parcial y hace falta
        // el importe declarado para poder contrastarlo. Sin él, el emisor no tiene
        // forma de distinguir "acredita esto" de "acredita el original entero".
        if ($items !== [] && $total === null) {
            throw ContratoInvalidoException::campo(
                'total',
                'Una nota de crédito parcial (con items) debe declarar su `total`: es la '
                    . 'verificación que impide acreditar la factura entera por error.',
            );
        }

        if ($total !== null && $total <= 0) {
            throw ContratoInvalidoException::campo(
                'total',
                'El total de la nota de crédito debe ser mayor que cero.',
            );
        }
    }

    /** Anulación total: sin líneas, se copia el original. */
    public static function total(string $idempotencyKey, MotivoNotaCredito $motivo, ?string $observaciones = null): self
    {
        return new self($idempotencyKey, $motivo, $observaciones);
    }

    /**
     * Devolución parcial: se detalla qué se devuelve y por cuánto.
     *
     * @param Item[] $items
     */
    public static function parcial(
        string $idempotencyKey,
        MotivoNotaCredito $motivo,
        array $items,
        float $total,
        ?string $observaciones = null,
    ): self {
        if ($items === []) {
            throw ContratoInvalidoException::campo(
                'items',
                'Una nota parcial necesita al menos una línea; si quieres anular todo, usa `total()`.',
            );
        }

        return new self($idempotencyKey, $motivo, $observaciones, $items, $total);
    }

    /** ¿Deja el comprobante original sin efecto por completo? */
    public function esTotal(): bool
    {
        return $this->items === [];
    }

    /** ¿Acredita solo una parte? Lo decide la presencia de líneas, no el motivo. */
    public function esParcial(): bool
    {
        return $this->items !== [];
    }

    /** Suma bruta de las líneas acreditadas. Lo gratuito no se cobró, así que no se devuelve. */
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
        $motivo = $datos['motivo'] ?? null;

        if (! is_string($motivo) || MotivoNotaCredito::tryFrom($motivo) === null) {
            throw ContratoInvalidoException::campo(
                'motivo',
                'Motivo de nota de crédito no reconocido. Valores admitidos: '
                    . implode(', ', array_column(MotivoNotaCredito::cases(), 'value')) . '.',
            );
        }

        // Ojo: `items` ausente y `items` vacío significan lo mismo — nota total —, pero
        // `items` presente con líneas cambia por completo el sentido de la petición.
        $items = $datos['items'] ?? [];

        if (! is_array($items)) {
            throw ContratoInvalidoException::campo('items', 'Los items deben ser una lista.');
        }

        return new self(
            idempotencyKey: (string) ($datos['idempotency_key'] ?? ''),
            motivo: MotivoNotaCredito::from($motivo),
            observaciones: $datos['observaciones'] ?? null,
            items: array_map(
                static function (mixed $i, int $n): Item {
                    if (! is_array($i)) {
                        throw ContratoInvalidoException::campo("items.{$n}", 'Cada línea debe ser un objeto.');
                    }

                    return Item::desdeArray($i);
                },
                array_values($items),
                array_keys(array_values($items)),
            ),
            total: isset($datos['total']) ? (float) $datos['total'] : null,
        );
    }

    public function aArray(): array
    {
        return array_filter([
            'idempotency_key' => $this->idempotencyKey,
            'motivo'          => $this->motivo->value,
            'observaciones'   => $this->observaciones,
            // Se omite en la nota total: mandar `"items": []` es ambiguo de leer en un
            // log, y el contrato dice "omitir para anular todo".
            'items'           => $this->items !== []
                ? array_map(static fn (Item $i) => $i->aArray(), $this->items)
                : null,
            'total'           => $this->total,
        ], static fn ($v) => $v !== null);
    }
}
