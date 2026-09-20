<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Enum;

/**
 * Quién lleva la mercadería.
 *
 * Se decide guía por guía, no empresa por empresa: el mismo día se puede sacar una
 * con la camioneta propia y otra con un flete contratado. Cualquier diseño que lo
 * fije en la configuración de la empresa estorba al primer día de trabajo real.
 *
 * Las dos modalidades son excluyentes y piden bloques de datos DISTINTOS. Enviar
 * los dos a la vez —placa propia y RUC de transportista— es motivo de rechazo, así
 * que el DTO `Traslado` lo comprueba antes de salir.
 */
enum ModalidadTraslado: string
{
    /** Lo lleva la empresa con su propio vehículo y su propio chofer. */
    case PRIVADO = 'PRIVADO';

    /** Lo lleva una empresa de transporte contratada. */
    case PUBLICO = 'PUBLICO';

    /**
     * ¿Hay que informar al transportista contratado?
     *
     * En transporte público la placa y el chofer NO se envían: son datos del
     * transportista, que los declara en su propia guía. Aquí solo va quién es.
     */
    public function exigeTransportista(): bool
    {
        return $this === self::PUBLICO;
    }

    /**
     * ¿Hay que informar vehículo y conductor propios?
     *
     * Con una salvedad, que vive en `Traslado`: si el reparto se hace en moto o en
     * auto particular, SUNAT exime de declarar vehículo y conductor.
     */
    public function exigeVehiculoPropio(): bool
    {
        return $this === self::PRIVADO;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::PRIVADO => 'Transporte privado',
            self::PUBLICO => 'Transporte público',
        };
    }

    /** Cómo se le explica al usuario, que no piensa en «modalidades». */
    public function pregunta(): string
    {
        return match ($this) {
            self::PRIVADO => 'Lo llevo yo, con mi vehículo',
            self::PUBLICO => 'Lo lleva un transportista contratado',
        };
    }
}
