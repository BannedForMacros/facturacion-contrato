<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Enum;

/**
 * Estado de una guía de remisión en el emisor.
 *
 * NO ES `EstadoComprobante` Y NO DEBE SERLO. Una guía viaja por un canal distinto
 * —una API REST con token, no el web service de las facturas— y su ciclo de vida no
 * coincide en nada relevante:
 *
 *   · No hay resumen diario: una guía se informa siempre de una en una.
 *   · La respuesta es DIFERIDA. SUNAT devuelve un ticket y hay que volver a
 *     preguntar por él. El estado `ENVIADA` no es un instante, puede durar minutos.
 *   · No se anula con nota de crédito, porque no tiene importes. Se da de baja en
 *     el portal SOL, a mano, y solo antes de que empiece el traslado.
 *
 * LA PREGUNTA QUE IMPORTA ES `permiteTraslado()`. Una factura rechazada es un
 * problema administrativo que se arregla al día siguiente; una guía rechazada con
 * el camión ya en la carretera es una multa y la mercadería retenida en el control.
 * Por eso el estado no es un adorno de la pantalla: es un semáforo, y todo lo que
 * no sea verde tiene que verse como rojo.
 */
enum EstadoGuia: string
{
    /** Creada en el emisor, todavía sin salir hacia SUNAT. */
    case PENDIENTE = 'pendiente';

    /** En camino: el envío está encolado o ejecutándose. */
    case ENVIANDO = 'enviando';

    /** Entregada a SUNAT, que devolvió un ticket. Falta consultar el resultado. */
    case ENVIADA = 'enviada';

    /** SUNAT la aceptó. Es la única situación en la que la mercadería puede salir. */
    case ACEPTADA = 'aceptada';

    /** SUNAT la rechazó. Determinista: reenviar lo mismo no la arregla. */
    case RECHAZADA = 'rechazada';

    /** Fallo de transporte o de credenciales. Reintentable tal cual. */
    case ERROR_ENVIO = 'error_envio';

    /** Dada de baja en SOL. El emisor la marca cuando el usuario confirma la baja. */
    case ANULADA = 'anulada';

    /** Modo simulación: numerada en serie propia y nunca enviada. */
    case SIMULADA = 'simulada';

    /**
     * ¿La mercadería puede salir amparada por esta guía?
     *
     * Solo si SUNAT ya la aceptó. En cualquier otro estado el documento no existe
     * para SUNAT todavía, y circular con él es circular sin guía.
     *
     * La simulación no cuenta, por si hace falta decirlo: sirve para probar el flujo
     * en pantalla, no para sacar un camión.
     */
    public function permiteTraslado(): bool
    {
        return $this === self::ACEPTADA;
    }

    /**
     * ¿SUNAT ya sabe de esta guía?
     *
     * Si es `true`, el número está consumido y no se puede reutilizar ni reasignar:
     * hay que dar de baja en SOL o emitir otra. Igual que en los comprobantes, ante
     * la duda se responde `true`.
     */
    public function informada(): bool
    {
        return match ($this) {
            self::ENVIANDO,
            self::ENVIADA,
            self::ACEPTADA,
            self::RECHAZADA,
            self::ANULADA => true,

            self::PENDIENTE,
            self::ERROR_ENVIO,
            self::SIMULADA => false,
        };
    }

    /** ¿Tiene sentido volver a enviar exactamente lo mismo? */
    public function reintentable(): bool
    {
        return match ($this) {
            self::PENDIENTE, self::ERROR_ENVIO => true,
            default                            => false,
        };
    }

    /** ¿Hay que seguir preguntándole a SUNAT por ella? */
    public function esperaRespuesta(): bool
    {
        return match ($this) {
            self::ENVIANDO, self::ENVIADA => true,
            default                       => false,
        };
    }

    /** ¿Se acabó el recorrido? Si es `true`, dejar de consultar. */
    public function terminal(): bool
    {
        return match ($this) {
            self::ACEPTADA, self::RECHAZADA, self::ANULADA, self::SIMULADA => true,
            default                                                       => false,
        };
    }

    /**
     * ¿Se puede pedir la baja de esta guía?
     *
     * Solo tiene sentido sobre una guía viva ante SUNAT. Una rechazada no existe;
     * una que nunca salió se borra sin más.
     */
    public function permiteBaja(): bool
    {
        return $this === self::ACEPTADA;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::PENDIENTE   => 'Pendiente de envío',
            self::ENVIANDO    => 'Enviando a SUNAT',
            self::ENVIADA     => 'Esperando respuesta de SUNAT',
            self::ACEPTADA    => 'Aceptada',
            self::RECHAZADA   => 'Rechazada',
            self::ERROR_ENVIO => 'Error de envío',
            self::ANULADA     => 'Dada de baja',
            self::SIMULADA    => 'Simulada',
        };
    }

    /**
     * Qué decirle a quien está a punto de cargar el camión.
     *
     * En pantalla pesa más esto que el nombre del estado: el almacenero no tiene por
     * qué saber qué es un ticket de SUNAT, solo si puede salir o no.
     */
    public function aviso(): string
    {
        return match ($this) {
            self::ACEPTADA    => 'La mercadería puede salir.',
            self::PENDIENTE   => 'Todavía no se ha enviado a SUNAT. La mercadería no puede salir.',
            self::ENVIANDO,
            self::ENVIADA     => 'SUNAT aún no responde. Espera la confirmación antes de que salga el vehículo.',
            self::RECHAZADA   => 'SUNAT la rechazó. Corrige el motivo y emite una guía nueva.',
            self::ERROR_ENVIO => 'No se pudo enviar. Reintenta; si persiste, avisa a soporte.',
            self::ANULADA     => 'Dada de baja. No ampara ningún traslado.',
            self::SIMULADA    => 'Guía de prueba. No tiene validez ante SUNAT.',
        };
    }
}
