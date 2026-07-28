<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Enum;

/**
 * Estado de un comprobante en el emisor.
 *
 * POR QUÉ ESTE ENUM ES COMPARTIDO Y NO UNA LISTA EN CADA LADO:
 *
 * Durante la construcción de la integración aparecieron dos bugs fiscales, y los
 * dos fueron el mismo fallo — dos sistemas que se creían de acuerdo sobre qué
 * significaba cada estado:
 *
 *   1. Al POS le faltaban `enviado`, `en_resumen` y `pendiente_anulacion` en su
 *      lista de "ya informado a SUNAT", así que dejaba ANULAR localmente una venta
 *      cuyo comprobante SUNAT ya conocía. Eso descuadra la declaración.
 *   2. El POS pedía una nota de crédito sobre boletas en `pendiente_resumen`, un
 *      estado que el emisor rechaza. Como toda boleta vive ahí hasta las 23:55, la
 *      consecuencia era que NINGUNA devolución de boleta generaba jamás su nota de
 *      crédito.
 *
 * Con la respuesta a esas preguntas aquí —y no repetida en cada repositorio— las
 * dos habrían sido errores al compilar.
 *
 * REGLA AL AÑADIR UN ESTADO: si hay duda sobre `bloqueaAnulacion()`, se responde
 * `true`. Dejar anular una venta ya informada descuadra la declaración ante SUNAT;
 * bloquearla de más solo obliga a emitir una nota de crédito.
 */
enum EstadoComprobante: string
{
    /** Creado en el emisor, todavía sin salir hacia SUNAT. */
    case PENDIENTE = 'pendiente';

    /** En camino: el envío está encolado o ejecutándose. */
    case ENVIANDO = 'enviando';

    /** Enviado, pero sin CDR interpretado todavía. */
    case ENVIADO = 'enviado';

    /** Boleta a la espera del Resumen Diario (se envía a las 23:55). */
    case PENDIENTE_RESUMEN = 'pendiente_resumen';

    /** Boleta ya incluida en un resumen enviado a SUNAT. */
    case EN_RESUMEN = 'en_resumen';

    /** Boleta marcada para baja, aún sin confirmación de SUNAT. */
    case PENDIENTE_ANULACION = 'pendiente_anulacion';

    /** SUNAT devolvió CDR conforme. */
    case ACEPTADO = 'aceptado';

    /** SUNAT lo rechazó. Determinista: reintentar lo mismo no lo arregla. */
    case RECHAZADO = 'rechazado';

    /** Fallo de transporte (red, SOAP). Reintentable. */
    case ERROR_ENVIO = 'error_envio';

    /** Anulado mediante nota de crédito aceptada. */
    case ANULADO = 'anulado';

    /** Modo simulación: calculado y numerado en serie propia, nunca enviado. */
    case SIMULADO = 'simulado';

    /**
     * ¿El comprobante ya salió del emisor hacia SUNAT (o saldrá hoy sin remedio)?
     *
     * Si es `true`, el sistema origen NO puede anular ni editar la venta: el único
     * instrumento legal para revertirla es una nota de crédito.
     */
    public function bloqueaAnulacion(): bool
    {
        return match ($this) {
            self::ENVIANDO,
            self::ENVIADO,
            self::PENDIENTE_RESUMEN,
            self::EN_RESUMEN,
            self::PENDIENTE_ANULACION,
            self::ACEPTADO,
            self::ANULADO => true,

            self::PENDIENTE,
            self::RECHAZADO,
            self::ERROR_ENVIO,
            self::SIMULADO => false,
        };
    }

    /**
     * ¿Se le puede emitir una nota de crédito?
     *
     * Solo se acredita lo que SUNAT ya conoce. Una boleta en `pendiente_resumen`
     * todavía no existe para SUNAT: hay que ESPERAR, no fallar. Ver `debeEsperar()`.
     */
    public function admiteNotaCredito(): bool
    {
        return $this === self::ACEPTADO || $this === self::ENVIADO;
    }

    /**
     * ¿Es un estado transitorio en el que una nota de crédito debe posponerse en
     * lugar de darse por fallida?
     *
     * Distinguir esto de un fallo real es lo que evita que una devolución de boleta
     * se pierda para siempre por pedirla antes de las 23:55.
     */
    public function debeEsperar(): bool
    {
        return ! $this->admiteNotaCredito() && ! $this->esTerminal();
    }

    /** ¿Ya no va a cambiar por sí solo? Deja de tener sentido seguir consultando. */
    public function esTerminal(): bool
    {
        return match ($this) {
            self::ACEPTADO, self::RECHAZADO, self::ANULADO, self::SIMULADO => true,
            default => false,
        };
    }

    /** ¿Admite un nuevo intento de envío sobre el MISMO comprobante (sin consumir correlativo)? */
    public function esReintentable(): bool
    {
        return match ($this) {
            self::PENDIENTE, self::ERROR_ENVIO, self::RECHAZADO => true,
            default => false,
        };
    }

    /** Etiqueta breve para mostrar a una cajera. */
    public function etiqueta(): string
    {
        return match ($this) {
            self::PENDIENTE           => 'Pendiente de envío',
            self::ENVIANDO            => 'Enviando a SUNAT',
            self::ENVIADO             => 'Enviado',
            self::PENDIENTE_RESUMEN   => 'En el resumen del día',
            self::EN_RESUMEN          => 'Informado en resumen',
            self::PENDIENTE_ANULACION => 'Anulación en trámite',
            self::ACEPTADO            => 'Aceptado por SUNAT',
            self::RECHAZADO           => 'Rechazado por SUNAT',
            self::ERROR_ENVIO         => 'Error de envío',
            self::ANULADO             => 'Anulado',
            self::SIMULADO            => 'Simulado (no enviado)',
        };
    }

    /** Color sugerido para la interfaz. */
    public function color(): string
    {
        return match ($this) {
            self::ACEPTADO                              => 'verde',
            self::ENVIANDO, self::ENVIADO, self::EN_RESUMEN => 'azul',
            self::PENDIENTE_RESUMEN, self::PENDIENTE_ANULACION, self::ANULADO => 'naranja',
            self::RECHAZADO, self::ERROR_ENVIO          => 'rojo',
            self::PENDIENTE, self::SIMULADO             => 'gris',
        };
    }
}
