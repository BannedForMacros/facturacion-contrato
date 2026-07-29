<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Dto;

use MacSoft\Facturacion\Contrato\Enum\CodigoError;
use MacSoft\Facturacion\Contrato\Excepcion\ContratoInvalidoException;

/**
 * Un error del emisor, con cuatro destinatarios distintos en un solo objeto.
 *
 *   - `error`        → para programar contra él. Nunca cambia de significado.
 *   - `mensaje`      → para la pantalla de la cajera.
 *   - `campo`        → para señalar exactamente qué corregir.
 *   - `reintentable` → para que la cola sepa si insistir sirve de algo.
 *
 * Existe porque un error reducido a un texto obliga al consumidor a decidir por
 * substring («¿contiene 'SUNAT'? pues reintento»), y esa heurística falla el día que
 * alguien mejora la redacción del mensaje.
 */
final readonly class ErrorRespuesta
{
    public function __construct(
        public CodigoError $error,
        public string $mensaje,
        public ?string $campo = null,
        public ?string $solucion = null,
        /**
         * Lo que dijo el emisor. `null` si no lo dijo: entonces manda el catálogo.
         * Nunca es lo mismo que `false` — ver `esReintentable()`.
         */
        public ?bool $reintentable = null,
    ) {
        if (trim($mensaje) === '') {
            throw ContratoInvalidoException::campo(
                'mensaje',
                'Un error sin mensaje no se puede mostrar a nadie.',
            );
        }
    }

    /**
     * ¿La cola debe volver a intentarlo?
     *
     * Manda el valor que llegó en la respuesta: el emisor conoce la situación
     * concreta y puede saber algo que el catálogo no (por ejemplo, que un rechazo
     * fue por una incidencia puntual). Si no vino, se cae al catálogo, que es la
     * fuente de verdad por código.
     *
     * Lo que NUNCA se hace es asumir `false` ante la ausencia del campo: dar por
     * definitivo un `sunat_no_disponible` pierde la venta sin que nadie se entere.
     */
    public function esReintentable(): bool
    {
        return $this->reintentable ?? $this->error->esReintentable();
    }

    /** Código HTTP asociado, útil cuando se fabrica el error en el propio origen. */
    public function httpStatus(): int
    {
        return $this->error->httpStatus();
    }

    public static function desdeArray(array $datos): self
    {
        $codigo = $datos['error'] ?? null;

        // Un código que este paquete todavía no conoce NO puede tumbar al consumidor:
        // el catálogo es aditivo y el emisor puede ir por delante de la dependencia.
        // Se degrada a `error_interno`, y como el `reintentable` de la respuesta manda
        // sobre el del catálogo, la decisión de la cola sigue siendo la correcta.
        $error = is_string($codigo) ? CodigoError::tryFrom($codigo) : null;

        return new self(
            error: $error ?? CodigoError::ERROR_INTERNO,
            mensaje: (string) ($datos['mensaje'] ?? 'El emisor devolvió un error sin descripción.'),
            campo: $datos['campo'] ?? null,
            solucion: $datos['solucion'] ?? null,
            reintentable: isset($datos['reintentable']) ? (bool) $datos['reintentable'] : null,
        );
    }

    public function aArray(): array
    {
        return array_filter([
            'error'        => $this->error->value,
            'mensaje'      => $this->mensaje,
            'campo'        => $this->campo,
            'solucion'     => $this->solucion,
            'reintentable' => $this->esReintentable(),
        ], static fn ($v) => $v !== null);
    }
}
