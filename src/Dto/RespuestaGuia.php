<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Dto;

use MacSoft\Facturacion\Contrato\Enum\EstadoGuia;

/**
 * Lo que el emisor devuelve al pedirle una guía.
 *
 * ─── EL CAMPO QUE IMPORTA ES `puedeTrasladar` ──────────────────────────────────
 *
 * Y viaja calculado, no deducido. El sistema origen NO tiene que mirar el estado y
 * decidir por su cuenta si el camión puede salir: esa pregunta ya la responde
 * `EstadoGuia::permiteTraslado()`, y mandarla resuelta es lo que impide que el POS
 * pinte «lista» una guía que el emisor todavía tiene en el aire.
 *
 * Es el mismo error que se pagó dos veces con los comprobantes —dos sistemas con
 * listas de estados distintas— y aquí no se repite: la lista no se copia, se
 * pregunta, y la respuesta viene dada.
 *
 * ─── `aviso` ES PARA ENSEÑARLO TAL CUAL ────────────────────────────────────────
 *
 * Dice en una frase qué pasa y qué se puede hacer. Quien despacha no tiene por qué
 * saber qué es un ticket de SUNAT.
 */
final readonly class RespuestaGuia
{
    public function __construct(
        public int $id,
        public EstadoGuia $estado,
        public string $serie,
        public int $numero,
        public string $numeroCompleto,
        /** ¿La mercadería puede salir amparada por esta guía? */
        public bool $puedeTrasladar,
        /** Qué decirle a quien está a punto de cargar. */
        public string $aviso,
        public ?string $fechaEmision = null,
        public ?string $referenciaExterna = null,
        /** Código y descripción de SUNAT, cuando ya contestó. */
        public ?string $sunatCodigo = null,
        public ?string $sunatDescripcion = null,
        /** ¿Esta petición devolvió una guía que ya existía? */
        public bool $reutilizada = false,
        /** Enlaces al PDF, al XML y al CDR. */
        public array $enlaces = [],
    ) {
    }

    /** ¿Hay que volver a preguntar por ella? */
    public function esperaRespuesta(): bool
    {
        return $this->estado->esperaRespuesta();
    }

    /** ¿Se acabó el recorrido? Si es `true`, dejar de consultar. */
    public function terminal(): bool
    {
        return $this->estado->terminal();
    }

    public static function desdeArray(array $datos): self
    {
        $estado = EstadoGuia::tryFrom((string) ($datos['estado'] ?? '')) ?? EstadoGuia::PENDIENTE;

        return new self(
            id: (int) ($datos['id'] ?? 0),
            estado: $estado,
            serie: (string) ($datos['serie'] ?? ''),
            numero: (int) ($datos['numero'] ?? 0),
            numeroCompleto: (string) ($datos['numero_completo'] ?? ''),
            // Se prefiere lo que dijo el emisor; si faltara, se responde con el enum,
            // que es la misma fuente. Nunca se inventa un «sí».
            puedeTrasladar: (bool) ($datos['puede_trasladar'] ?? $estado->permiteTraslado()),
            aviso: (string) ($datos['aviso'] ?? $estado->aviso()),
            fechaEmision: $datos['fecha_emision'] ?? null,
            referenciaExterna: $datos['referencia_externa'] ?? null,
            sunatCodigo: isset($datos['sunat']['codigo']) ? (string) $datos['sunat']['codigo'] : null,
            sunatDescripcion: $datos['sunat']['descripcion'] ?? null,
            reutilizada: (bool) ($datos['reutilizada'] ?? false),
            enlaces: is_array($datos['enlaces'] ?? null) ? $datos['enlaces'] : [],
        );
    }

    public function aArray(): array
    {
        return array_filter([
            'id'                 => $this->id,
            'estado'             => $this->estado->value,
            'estado_etiqueta'    => $this->estado->etiqueta(),
            'serie'              => $this->serie,
            'numero'             => $this->numero,
            'numero_completo'    => $this->numeroCompleto,
            'puede_trasladar'    => $this->puedeTrasladar,
            'aviso'              => $this->aviso,
            'fecha_emision'      => $this->fechaEmision,
            'referencia_externa' => $this->referenciaExterna,
            'sunat' => array_filter([
                'codigo'      => $this->sunatCodigo,
                'descripcion' => $this->sunatDescripcion,
            ], static fn ($v) => $v !== null) ?: null,
            'reutilizada' => $this->reutilizada ?: null,
            'enlaces'     => $this->enlaces !== [] ? $this->enlaces : null,
        ], static fn ($v) => $v !== null);
    }
}
