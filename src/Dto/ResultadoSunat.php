<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Dto;

/**
 * Lo que respondió SUNAT sobre un comprobante.
 *
 * POR QUÉ EXISTE: sin este bloque, un rechazo llega al sistema de origen como un
 * simple estado `rechazado`, sin el porqué. La cajera ve "rechazado" y nadie sabe
 * si fue un RUC inexistente, una serie no autorizada o un problema del
 * certificado — hay que entrar a la base del emisor a buscarlo.
 *
 * El código es el del CDR (`0` = aceptado; `2xxx` = rechazo; `4xxx` = observación
 * que SÍ acepta el comprobante). Se transporta como texto porque los códigos de
 * SUNAT llevan ceros a la izquierda.
 *
 * Misma forma que el bloque `sunat` de los webhooks (§10 del contrato), para que
 * el consumidor no tenga que interpretar dos estructuras distintas según por dónde
 * le llegue la noticia.
 */
final readonly class ResultadoSunat
{
    public function __construct(
        public ?string $codigo = null,
        public ?string $descripcion = null,
        /** Observaciones del CDR: el comprobante se aceptó, pero con reparos. */
        public array $notas = [],
    ) {}

    /** ¿SUNAT lo aceptó? Solo el código 0 lo es; el 4xxx acepta con observaciones. */
    public function aceptado(): bool
    {
        if ($this->codigo === null || $this->codigo === '') {
            return false;
        }

        return $this->codigo === '0' || str_starts_with($this->codigo, '4');
    }

    /** ¿Trae reparos aunque se haya aceptado? */
    public function tieneObservaciones(): bool
    {
        return $this->notas !== [] || ($this->codigo !== null && str_starts_with($this->codigo, '4'));
    }

    public function estaVacio(): bool
    {
        return $this->codigo === null && $this->descripcion === null && $this->notas === [];
    }

    public static function desdeArray(array $datos): self
    {
        return new self(
            codigo: isset($datos['codigo']) ? (string) $datos['codigo'] : null,
            descripcion: $datos['descripcion'] ?? null,
            notas: array_values($datos['notas'] ?? []),
        );
    }

    public function aArray(): array
    {
        return array_filter([
            'codigo'      => $this->codigo,
            'descripcion' => $this->descripcion,
            'notas'       => $this->notas !== [] ? $this->notas : null,
        ], static fn ($v) => $v !== null);
    }
}
