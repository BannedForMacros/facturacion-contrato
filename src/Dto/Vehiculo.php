<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Dto;

use MacSoft\Facturacion\Contrato\Excepcion\ContratoInvalidoException;

/**
 * El vehículo que hace el traslado.
 *
 * Solo en transporte privado: en un flete contratado la placa la pone el
 * transportista en su guía.
 *
 * LA PLACA SE NORMALIZA, NO SE RECHAZA. Quien la escribe la escribe como la lee en
 * la tarjeta —«ABC-123», «abc 123»— y SUNAT la quiere sin separadores y en
 * mayúsculas. Rechazarla por el guion sería exigirle al almacenero que conozca un
 * formato que no es suyo; se limpia y en paz. Lo que sí se comprueba es que no
 * tenga símbolos raros ni una longitud imposible, porque una placa mal tecleada es
 * una multa en el control de carretera, no un error de pantalla.
 */
final readonly class Vehiculo
{
    public function __construct(
        public string $placa,
        /** Tarjeta única de circulación. SUNAT la pide en algunos traslados. */
        public ?string $tarjetaCirculacion = null,
        /**
         * Placas de los vehículos secundarios: la carreta de un tracto, el remolque.
         *
         * @var string[]
         */
        public array $secundarios = [],
    ) {
        if (self::normalizarPlaca($placa) === '') {
            throw ContratoInvalidoException::campo(
                'vehiculo.placa',
                'La placa del vehículo es obligatoria.',
            );
        }

        self::exigirPlacaVerosimil($placa, 'vehiculo.placa');

        foreach ($secundarios as $i => $secundaria) {
            if (! is_string($secundaria)) {
                throw ContratoInvalidoException::campo(
                    "vehiculo.secundarios.{$i}",
                    'Cada vehículo secundario es una placa.',
                );
            }

            self::exigirPlacaVerosimil($secundaria, "vehiculo.secundarios.{$i}");
        }
    }

    /** La placa tal y como la quiere SUNAT: sin separadores y en mayúsculas. */
    public function placaNormalizada(): string
    {
        return self::normalizarPlaca($this->placa);
    }

    /** @return string[] Las secundarias, ya normalizadas. */
    public function secundariasNormalizadas(): array
    {
        return array_values(array_map(self::normalizarPlaca(...), $this->secundarios));
    }

    public static function normalizarPlaca(string $placa): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $placa));
    }

    private static function exigirPlacaVerosimil(string $placa, string $campo): void
    {
        $limpia = self::normalizarPlaca($placa);

        if ($limpia === '') {
            throw ContratoInvalidoException::campo($campo, 'La placa no puede ir vacía.');
        }

        // Entre 6 y 8 caracteres cubre coches, camiones, remolques y motos, incluidas
        // las placas antiguas. Fuera de ahí es que se tecleó otra cosa.
        $largo = strlen($limpia);

        if ($largo < 6 || $largo > 8) {
            throw ContratoInvalidoException::campo(
                $campo,
                "«{$placa}» no parece una placa: debe tener entre 6 y 8 letras o números.",
            );
        }
    }

    public static function desdeArray(array $datos): self
    {
        $secundarios = $datos['secundarios'] ?? [];

        if (! is_array($secundarios)) {
            throw ContratoInvalidoException::campo(
                'vehiculo.secundarios',
                'Los vehículos secundarios son una lista de placas.',
            );
        }

        return new self(
            placa: (string) ($datos['placa'] ?? ''),
            tarjetaCirculacion: $datos['tarjeta_circulacion'] ?? null,
            secundarios: array_values($secundarios),
        );
    }

    public function aArray(): array
    {
        return array_filter([
            'placa'               => $this->placaNormalizada(),
            'tarjeta_circulacion' => $this->tarjetaCirculacion,
            'secundarios'         => $this->secundarios === [] ? null : $this->secundariasNormalizadas(),
        ], static fn ($v) => $v !== null);
    }
}
