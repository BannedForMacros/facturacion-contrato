<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Dto;

use DateTimeImmutable;
use MacSoft\Facturacion\Contrato\Enum\ModalidadTraslado;
use MacSoft\Facturacion\Contrato\Enum\MotivoTraslado;
use MacSoft\Facturacion\Contrato\Excepcion\ContratoInvalidoException;

/**
 * El viaje: por qué, cuándo, desde dónde, hasta dónde y en qué.
 *
 * ES EL SITIO DONDE SE DECIDE ENTRE CAMIONETA PROPIA Y FLETE CONTRATADO, y por eso
 * la regla más importante de esta clase es que las dos cosas NO PUEDEN IR JUNTAS.
 * Una guía con placa propia y RUC de transportista a la vez la rechaza SUNAT, y el
 * rechazo llega cuando el camión ya salió. Se caza aquí, al construir, que es
 * gratis.
 *
 * El formulario enseña un bloque u otro según la modalidad; este DTO es el que se
 * asegura de que lo que llegó se corresponde con lo que se dijo.
 */
final readonly class Traslado
{
    /** Unidades en que SUNAT admite el peso de la carga. */
    public const UNIDADES_PESO = ['KGM', 'TNE'];

    /** @param Conductor[] $conductores */
    public function __construct(
        public MotivoTraslado $motivo,
        public ModalidadTraslado $modalidad,
        public DateTimeImmutable $fechaInicio,
        public Ubicacion $partida,
        public Ubicacion $llegada,
        public float $pesoTotal,
        public string $unidadPeso = 'KGM',
        public ?int $numeroBultos = null,
        public ?string $descripcionMotivo = null,
        public ?Transportista $transportista = null,
        public ?Vehiculo $vehiculo = null,
        public array $conductores = [],
        /**
         * Reparto en moto o en auto particular (categorías M1 y L).
         *
         * SUNAT exime de declarar vehículo y conductor en ese caso, y conviene
         * aprovecharlo: obligar a dar de alta la moto del repartidor y su licencia
         * para cada envío de barrio es fricción pura.
         */
        public bool $vehiculoMenor = false,
    ) {
        if ($motivo->exigeDescripcion() && trim((string) $descripcionMotivo) === '') {
            throw ContratoInvalidoException::campo(
                'traslado.descripcion_motivo',
                'Con el motivo «otros» hay que explicar por escrito de qué traslado se trata.',
            );
        }

        if ($pesoTotal <= 0) {
            throw ContratoInvalidoException::campo(
                'traslado.peso_total',
                'El peso bruto total es obligatorio y debe ser mayor que cero.',
            );
        }

        if (! in_array($unidadPeso, self::UNIDADES_PESO, true)) {
            throw ContratoInvalidoException::campo(
                'traslado.unidad_peso',
                'El peso se expresa en KGM (kilogramos) o TNE (toneladas).',
            );
        }

        if ($numeroBultos !== null && $numeroBultos < 0) {
            throw ContratoInvalidoException::campo(
                'traslado.numero_bultos',
                'El número de bultos no puede ser negativo.',
            );
        }

        foreach ($conductores as $i => $conductor) {
            if (! $conductor instanceof Conductor) {
                throw ContratoInvalidoException::campo(
                    "traslado.conductores.{$i}",
                    'Cada conductor debe ser un Conductor.',
                );
            }
        }

        $this->exigirCoherenciaDelTransporte();
    }

    /**
     * Que lo enviado case con la modalidad declarada.
     *
     * Las dos ramas son excluyentes a propósito: no se trata solo de que falte lo
     * necesario, sino de que NO SOBRE lo de la otra. Un payload con transportista y
     * placa a la vez suele significar que el sistema origen arrastró datos de una
     * guía anterior, y eso hay que pararlo antes de firmarlo.
     */
    private function exigirCoherenciaDelTransporte(): void
    {
        if ($this->modalidad === ModalidadTraslado::PUBLICO) {
            if ($this->transportista === null) {
                throw ContratoInvalidoException::campo(
                    'traslado.transportista',
                    'En transporte público hay que informar al transportista contratado.',
                );
            }

            if ($this->vehiculo !== null || $this->conductores !== []) {
                throw ContratoInvalidoException::campo(
                    'traslado.vehiculo',
                    'En transporte público no se informan placa ni conductor: son datos del '
                        . 'transportista y los declara él en su propia guía.',
                );
            }

            if ($this->vehiculoMenor) {
                throw ContratoInvalidoException::campo(
                    'traslado.vehiculo_menor',
                    'El vehículo menor solo aplica al transporte propio.',
                );
            }

            return;
        }

        if ($this->transportista !== null) {
            throw ContratoInvalidoException::campo(
                'traslado.transportista',
                'En transporte privado lo lleva la propia empresa: sobra el transportista. '
                    . 'Si lo lleva un tercero, la modalidad es «transporte público».',
            );
        }

        // Moto o auto particular: SUNAT no pide vehículo ni chofer, y tampoco nosotros.
        if ($this->vehiculoMenor) {
            if ($this->vehiculo !== null || $this->conductores !== []) {
                throw ContratoInvalidoException::campo(
                    'traslado.vehiculo_menor',
                    'Marcado como vehículo menor, no corresponde informar placa ni conductor.',
                );
            }

            return;
        }

        if ($this->vehiculo === null) {
            throw ContratoInvalidoException::campo(
                'traslado.vehiculo',
                'Falta la placa del vehículo que hace el traslado.',
            );
        }

        if ($this->conductores === []) {
            throw ContratoInvalidoException::campo(
                'traslado.conductores',
                'Falta el conductor: nombre, documento y licencia.',
            );
        }

        $principales = array_filter($this->conductores, static fn (Conductor $c) => $c->principal);

        if (count($principales) !== 1) {
            throw ContratoInvalidoException::campo(
                'traslado.conductores',
                'Tiene que haber exactamente un conductor principal; los demás van como secundarios.',
            );
        }
    }

    /** El conductor que figura al frente de la guía. */
    public function conductorPrincipal(): ?Conductor
    {
        foreach ($this->conductores as $conductor) {
            if ($conductor->principal) {
                return $conductor;
            }
        }

        return null;
    }

    /** ¿Hace falta declarar vehículo y chofer en esta guía? */
    public function declaraVehiculo(): bool
    {
        return $this->modalidad->exigeVehiculoPropio() && ! $this->vehiculoMenor;
    }

    public static function desdeArray(array $datos): self
    {
        $motivo = $datos['motivo'] ?? null;

        if (! is_string($motivo) || MotivoTraslado::tryFrom($motivo) === null) {
            throw ContratoInvalidoException::campo(
                'traslado.motivo',
                'Motivo de traslado no reconocido. Valores admitidos: '
                    . implode(', ', array_column(MotivoTraslado::cases(), 'value')) . '.',
            );
        }

        $modalidad = $datos['modalidad'] ?? null;

        if (! is_string($modalidad) || ModalidadTraslado::tryFrom($modalidad) === null) {
            throw ContratoInvalidoException::campo(
                'traslado.modalidad',
                'La modalidad de traslado es «PRIVADO» (lo llevo yo) o «PUBLICO» (lo lleva un transportista).',
            );
        }

        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($datos['fecha_inicio'] ?? '')) ?: null;

        if ($fecha === null) {
            throw ContratoInvalidoException::campo(
                'traslado.fecha_inicio',
                'La fecha de inicio del traslado es obligatoria y debe ser YYYY-MM-DD.',
            );
        }

        if (! isset($datos['partida']) || ! is_array($datos['partida'])) {
            throw ContratoInvalidoException::campo('traslado.partida', 'Falta el punto de partida.');
        }

        if (! isset($datos['llegada']) || ! is_array($datos['llegada'])) {
            throw ContratoInvalidoException::campo('traslado.llegada', 'Falta el punto de llegada.');
        }

        $conductores = $datos['conductores'] ?? [];

        if (! is_array($conductores)) {
            throw ContratoInvalidoException::campo(
                'traslado.conductores',
                'Los conductores son una lista.',
            );
        }

        return new self(
            motivo: MotivoTraslado::from($motivo),
            modalidad: ModalidadTraslado::from($modalidad),
            fechaInicio: $fecha,
            partida: Ubicacion::desdeArray($datos['partida'], 'traslado.partida'),
            llegada: Ubicacion::desdeArray($datos['llegada'], 'traslado.llegada'),
            pesoTotal: (float) ($datos['peso_total'] ?? 0),
            unidadPeso: strtoupper((string) ($datos['unidad_peso'] ?? 'KGM')),
            numeroBultos: isset($datos['numero_bultos']) ? (int) $datos['numero_bultos'] : null,
            descripcionMotivo: $datos['descripcion_motivo'] ?? null,
            transportista: isset($datos['transportista']) && is_array($datos['transportista'])
                ? Transportista::desdeArray($datos['transportista'])
                : null,
            vehiculo: isset($datos['vehiculo']) && is_array($datos['vehiculo'])
                ? Vehiculo::desdeArray($datos['vehiculo'])
                : null,
            conductores: array_map(
                static function (mixed $c, int $n): Conductor {
                    if (! is_array($c)) {
                        throw ContratoInvalidoException::campo(
                            "traslado.conductores.{$n}",
                            'Cada conductor debe ser un objeto.',
                        );
                    }

                    return Conductor::desdeArray($c, "traslado.conductores.{$n}");
                },
                array_values($conductores),
                array_keys(array_values($conductores)),
            ),
            vehiculoMenor: (bool) ($datos['vehiculo_menor'] ?? false),
        );
    }

    public function aArray(): array
    {
        return array_filter([
            'motivo'             => $this->motivo->value,
            'modalidad'          => $this->modalidad->value,
            'fecha_inicio'       => $this->fechaInicio->format('Y-m-d'),
            'partida'            => $this->partida->aArray(),
            'llegada'            => $this->llegada->aArray(),
            'peso_total'         => $this->pesoTotal,
            'unidad_peso'        => $this->unidadPeso,
            'numero_bultos'      => $this->numeroBultos,
            'descripcion_motivo' => $this->descripcionMotivo,
            'transportista'      => $this->transportista?->aArray(),
            'vehiculo'           => $this->vehiculo?->aArray(),
            'conductores'        => $this->conductores === []
                ? null
                : array_map(static fn (Conductor $c) => $c->aArray(), $this->conductores),
            'vehiculo_menor'     => $this->vehiculoMenor ?: null,
        ], static fn ($v) => $v !== null);
    }
}
