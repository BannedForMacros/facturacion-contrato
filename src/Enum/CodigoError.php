<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Enum;

/**
 * Catálogo cerrado de errores del emisor.
 *
 * POR QUÉ UN ENUM Y NO CADENAS SUELTAS: el consumidor programa contra el código,
 * no contra el mensaje. Un `if ($e->error === 'cliente_sin_ruc')` escrito a mano se
 * rompe con una errata y nadie se entera hasta que la cajera ve un error genérico.
 *
 * REGLA QUE NO SE NEGOCIA: un código NUNCA cambia de significado. Añadir uno nuevo
 * es aditivo y no rompe a nadie; reutilizar uno existente para otra cosa rompe en
 * silencio a todos los consumidores que ya decidían con él.
 *
 * `esReintentable()` es la parte con consecuencias reales: marcar como definitivo
 * algo transitorio pierde la venta (nadie vuelve a intentarlo), y marcar como
 * transitorio algo definitivo deja una cola girando para siempre. Por eso solo son
 * reintentables los tres casos en los que el mismo payload puede triunfar más tarde.
 */
enum CodigoError: string
{
    /** Token inválido o revocado. */
    case NO_AUTORIZADO = 'no_autorizado';

    /** La empresa está desactivada en el emisor. */
    case EMPRESA_INACTIVA = 'empresa_inactiva';

    /** Validación genérica; el detalle va en `campo`. */
    case DATOS_INVALIDOS = 'datos_invalidos';

    /** Esa referencia ya se usó en otra venta: delata un fallo del adaptador. */
    case REFERENCIA_EXTERNA_DUPLICADA = 'referencia_externa_duplicada';

    /** Factura sin RUC. */
    case CLIENTE_SIN_RUC = 'cliente_sin_ruc';

    /** Factura sin dirección fiscal. */
    case CLIENTE_SIN_DIRECCION = 'cliente_sin_direccion';

    /** Boleta por encima del umbral sin documento del adquirente. */
    case CLIENTE_NO_IDENTIFICADO = 'cliente_no_identificado';

    /** v1 solo emite en PEN. */
    case MONEDA_NO_SOPORTADA = 'moneda_no_soportada';

    /** El total declarado no coincide con el calculado. No se emite ni se numera. */
    case TOTALES_NO_CUADRAN = 'totales_no_cuadran';

    /** La empresa opera con una tasa distinta de la del emisor. */
    case TASA_IMPUESTO_NO_SOPORTADA = 'tasa_impuesto_no_soportada';

    /** La serie indicada no existe o está inactiva. */
    case SERIE_NO_ENCONTRADA = 'serie_no_encontrada';

    /** Modo simulación sin serie propia definida. */
    case SERIE_SIMULACION_NO_CONFIGURADA = 'serie_simulacion_no_configurada';

    /** La empresa no tiene certificado o clave SOL cargados. */
    case SIN_CERTIFICADO = 'sin_certificado';

    /** No existe la venta consultada. */
    case VENTA_NO_ENCONTRADA = 'venta_no_encontrada';

    /**
     * El comprobante todavía no llegó a SUNAT.
     *
     * Reintentable A PROPÓSITO: la boleta vive en `pendiente_resumen` hasta las
     * 23:55, así que la mayoría de devoluciones del día caen aquí. Tratarlo como
     * fallo definitivo significaría que ninguna boleta devuelta genera jamás su
     * nota de crédito.
     */
    case ESTADO_NO_PERMITE_NOTA_CREDITO = 'estado_no_permite_nota_credito';

    /** Se intenta acreditar más de lo facturado. */
    case NOTA_CREDITO_EXCEDE_ORIGINAL = 'nota_credito_excede_original';

    /** SUNAT rechazó el comprobante. Determinista: reenviar lo mismo no lo arregla. */
    case RECHAZADO_POR_SUNAT = 'rechazado_por_sunat';

    /** Red o SOAP de SUNAT caído. Transitorio por definición. */
    case SUNAT_NO_DISPONIBLE = 'sunat_no_disponible';

    /** Fallo no previsto del emisor. */
    case ERROR_INTERNO = 'error_interno';

    /** Código HTTP con el que viaja este error. */
    public function httpStatus(): int
    {
        return match ($this) {
            self::NO_AUTORIZADO     => 401,
            self::EMPRESA_INACTIVA  => 403,
            self::VENTA_NO_ENCONTRADA => 404,

            // 409 = conflicto de estado, no de datos: el payload es correcto, lo que
            // no encaja es la situación actual del recurso.
            self::REFERENCIA_EXTERNA_DUPLICADA,
            self::SIN_CERTIFICADO,
            self::ESTADO_NO_PERMITE_NOTA_CREDITO => 409,

            self::DATOS_INVALIDOS,
            self::CLIENTE_SIN_RUC,
            self::CLIENTE_SIN_DIRECCION,
            self::CLIENTE_NO_IDENTIFICADO,
            self::MONEDA_NO_SOPORTADA,
            self::TOTALES_NO_CUADRAN,
            self::TASA_IMPUESTO_NO_SOPORTADA,
            self::SERIE_NO_ENCONTRADA,
            self::SERIE_SIMULACION_NO_CONFIGURADA,
            self::NOTA_CREDITO_EXCEDE_ORIGINAL => 422,

            self::ERROR_INTERNO       => 500,
            self::RECHAZADO_POR_SUNAT => 502,
            self::SUNAT_NO_DISPONIBLE => 503,
        };
    }

    /**
     * ¿Volver a intentarlo con el MISMO payload puede acabar funcionando?
     *
     * Solo tres casos: el estado transitorio de la nota de crédito, SUNAT caída y un
     * fallo interno del emisor. Todo lo demás exige que alguien cambie algo (el
     * dato, la configuración o la empresa), y reintentarlo solo gasta cola.
     */
    public function esReintentable(): bool
    {
        return match ($this) {
            self::ESTADO_NO_PERMITE_NOTA_CREDITO,
            self::SUNAT_NO_DISPONIBLE,
            self::ERROR_INTERNO => true,
            default             => false,
        };
    }

    /** ¿Lo causó el payload del sistema origen? Distingue "arréglalo tú" de "espera". */
    public function esDelSistemaOrigen(): bool
    {
        return $this->httpStatus() >= 400 && $this->httpStatus() < 500;
    }
}
