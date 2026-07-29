<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Tests\Enum;

use MacSoft\Facturacion\Contrato\Enum\CodigoError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * El catálogo de errores es un contrato dentro del contrato: el consumidor programa
 * contra estos códigos y decide con `esReintentable()` si insistir.
 *
 * Igual que con los estados, la tabla se escribe aquí a mano para que el enum tenga
 * contra qué contrastarse, y añadir un código sin decidir su comportamiento rompa la
 * suite en vez de heredar un default silencioso.
 */
final class CodigoErrorTest extends TestCase
{
    /** @return array<string, array{CodigoError, int, bool}> */
    public static function catalogo(): array
    {
        return [
            'no_autorizado'                   => [CodigoError::NO_AUTORIZADO, 401, false],
            'empresa_inactiva'                => [CodigoError::EMPRESA_INACTIVA, 403, false],
            'datos_invalidos'                 => [CodigoError::DATOS_INVALIDOS, 422, false],
            'referencia_externa_duplicada'    => [CodigoError::REFERENCIA_EXTERNA_DUPLICADA, 409, false],
            'cliente_sin_ruc'                 => [CodigoError::CLIENTE_SIN_RUC, 422, false],
            'cliente_sin_direccion'           => [CodigoError::CLIENTE_SIN_DIRECCION, 422, false],
            'cliente_no_identificado'         => [CodigoError::CLIENTE_NO_IDENTIFICADO, 422, false],
            'moneda_no_soportada'             => [CodigoError::MONEDA_NO_SOPORTADA, 422, false],
            'totales_no_cuadran'              => [CodigoError::TOTALES_NO_CUADRAN, 422, false],
            'tasa_impuesto_no_soportada'      => [CodigoError::TASA_IMPUESTO_NO_SOPORTADA, 422, false],
            'serie_no_encontrada'             => [CodigoError::SERIE_NO_ENCONTRADA, 422, false],
            'serie_simulacion_no_configurada' => [CodigoError::SERIE_SIMULACION_NO_CONFIGURADA, 422, false],
            'sin_certificado'                 => [CodigoError::SIN_CERTIFICADO, 409, false],
            'venta_no_encontrada'             => [CodigoError::VENTA_NO_ENCONTRADA, 404, false],
            'estado_no_permite_nota_credito'  => [CodigoError::ESTADO_NO_PERMITE_NOTA_CREDITO, 409, true],
            'nota_credito_excede_original'    => [CodigoError::NOTA_CREDITO_EXCEDE_ORIGINAL, 422, false],
            'rechazado_por_sunat'             => [CodigoError::RECHAZADO_POR_SUNAT, 502, false],
            'sunat_no_disponible'             => [CodigoError::SUNAT_NO_DISPONIBLE, 503, true],
            'error_interno'                   => [CodigoError::ERROR_INTERNO, 500, true],
        ];
    }

    #[Test]
    #[DataProvider('catalogo')]
    public function cada_codigo_tiene_su_http_y_su_politica_de_reintento(
        CodigoError $codigo,
        int $http,
        bool $reintentable,
    ): void {
        self::assertSame($http, $codigo->httpStatus(), $codigo->value);
        self::assertSame($reintentable, $codigo->esReintentable(), $codigo->value);
    }

    #[Test]
    public function ningun_codigo_puede_existir_sin_decidir_si_se_reintenta(): void
    {
        $decididos = array_map(static fn (array $fila) => $fila[0]->value, array_values(self::catalogo()));
        $existentes = array_column(CodigoError::cases(), 'value');

        sort($decididos);
        sort($existentes);

        self::assertSame(
            $existentes,
            $decididos,
            'Hay un código de error sin decidir su HTTP y su política de reintento.',
        );
    }

    /**
     * El que más consecuencias tiene de los tres reintentables.
     *
     * Toda boleta vive en `pendiente_resumen` hasta las 23:55. Si este código fuera
     * definitivo, ninguna boleta devuelta generaría jamás su nota de crédito.
     */
    #[Test]
    public function el_estado_que_no_permite_nota_de_credito_se_reintenta(): void
    {
        self::assertTrue(CodigoError::ESTADO_NO_PERMITE_NOTA_CREDITO->esReintentable());
        self::assertSame(409, CodigoError::ESTADO_NO_PERMITE_NOTA_CREDITO->httpStatus());
    }

    /** Un rechazo de SUNAT es determinista: reenviar lo mismo da el mismo rechazo. */
    #[Test]
    public function el_rechazo_de_sunat_no_se_reintenta_pero_su_caida_si(): void
    {
        self::assertFalse(CodigoError::RECHAZADO_POR_SUNAT->esReintentable());
        self::assertTrue(CodigoError::SUNAT_NO_DISPONIBLE->esReintentable());
    }

    #[Test]
    public function distingue_lo_que_arregla_el_origen_de_lo_que_arregla_el_emisor(): void
    {
        self::assertTrue(CodigoError::CLIENTE_SIN_RUC->esDelSistemaOrigen());
        self::assertFalse(CodigoError::SUNAT_NO_DISPONIBLE->esDelSistemaOrigen());
        self::assertFalse(CodigoError::ERROR_INTERNO->esDelSistemaOrigen());
    }
}
