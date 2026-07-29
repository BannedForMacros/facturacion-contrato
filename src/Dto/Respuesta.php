<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Dto;

use MacSoft\Facturacion\Contrato\Enum\EstadoComprobante;

/**
 * Lo que devuelve el emisor al recibir una venta.
 *
 * `emitido = false` NO es un error: es la respuesta cuando la empresa tiene la
 * emisión desactivada. El sistema origen registra su venta con normalidad y no
 * reintenta. Tratarlo como fallo llenaría la cola de reintentos inútiles.
 */
final readonly class Respuesta
{
    /** @param Aviso[] $avisos */
    public function __construct(
        public bool $emitido,
        public string $modo,
        public ?int $id = null,
        public ?EstadoComprobante $estado = null,
        public ?Comprobante $comprobante = null,
        public ?Totales $totales = null,
        public array $avisos = [],
        public ?string $referenciaExterna = null,
        public ?string $motivo = null,
        /**
         * Qué respondió SUNAT. Sin esto, un rechazo llega como un estado
         * `rechazado` a secas y el motivo se pierde: la cajera ve el fallo y
         * nadie sabe si fue un RUC inexistente o una serie no autorizada.
         */
        public ?ResultadoSunat $sunat = null,
        /** true si la petición se absorbió por idempotencia y NO se emitió nada nuevo. */
        public bool $reutilizado = false,
    ) {}

    /** ¿El emisor está apuntando a producción? Determina el aviso en pantalla. */
    public function esProduccion(): bool
    {
        return $this->modo === 'produccion';
    }

    public function tieneAvisos(): bool
    {
        return $this->avisos !== [];
    }

    public static function desdeArray(array $datos): self
    {
        $estado = isset($datos['estado']) && is_string($datos['estado'])
            ? EstadoComprobante::tryFrom($datos['estado'])
            : null;

        $comprobante = null;

        if (isset($datos['comprobante']) && is_array($datos['comprobante'])) {
            $bruto = $datos['comprobante'];

            // §4.1 del contrato pone `enlaces` en la RAÍZ de la respuesta, mientras que
            // aquí viven con el comprobante, que es a lo que pertenecen. Se aceptan en
            // los dos sitios: leer solo uno hace desaparecer en silencio los enlaces al
            // PDF y al XML, y ese es justo el dato que la caja necesita para reimprimir.
            $bruto['enlaces'] ??= $datos['enlaces'] ?? [];

            $comprobante = Comprobante::desdeArray($bruto);
        }

        return new self(
            emitido: (bool) ($datos['emitido'] ?? true),
            modo: (string) ($datos['modo'] ?? 'produccion'),
            id: isset($datos['id']) ? (int) $datos['id'] : null,
            estado: $estado,
            comprobante: $comprobante,
            totales: isset($datos['totales']) && is_array($datos['totales'])
                ? Totales::desdeArray($datos['totales'])
                : null,
            avisos: array_map(
                static fn (array $a) => Aviso::desdeArray($a),
                array_values($datos['avisos'] ?? []),
            ),
            referenciaExterna: $datos['referencia_externa'] ?? null,
            motivo: $datos['motivo'] ?? null,
            sunat: isset($datos['sunat']) && is_array($datos['sunat'])
                ? ResultadoSunat::desdeArray($datos['sunat'])
                : null,
            reutilizado: (bool) ($datos['reutilizado'] ?? false),
        );
    }

    public function aArray(): array
    {
        return array_filter([
            'emitido'            => $this->emitido,
            'modo'               => $this->modo,
            'id'                 => $this->id,
            'estado'             => $this->estado?->value,
            'comprobante'        => $this->comprobante?->aArray(),
            // También en la raíz, que es donde §4.1 los describe y donde los busca quien
            // lea el contrato en vez de esta clase.
            'enlaces'            => $this->comprobante?->enlaces ?: null,
            'totales'            => $this->totales?->aArray(),
            'avisos'             => array_map(static fn (Aviso $a) => $a->aArray(), $this->avisos),
            'referencia_externa' => $this->referenciaExterna,
            'motivo'             => $this->motivo,
            'sunat'              => $this->sunat && ! $this->sunat->estaVacio() ? $this->sunat->aArray() : null,
            'reutilizado'        => $this->reutilizado,
        ], static fn ($v) => $v !== null);
    }
}
