<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Dto;

use MacSoft\Facturacion\Contrato\Excepcion\ContratoInvalidoException;

/**
 * Un extremo del traslado: de dónde sale la mercadería, o a dónde llega.
 *
 * POR QUÉ EL UBIGEO ES OBLIGATORIO Y NO SE DEDUCE DE LA DIRECCIÓN: porque no se
 * puede. «Av. Grau 123» existe en cientos de distritos del país, y SUNAT valida el
 * código, no el texto. Adivinarlo a partir de la dirección produciría guías
 * aceptadas con el distrito equivocado, que es peor que no emitirlas: el error
 * queda informado y firmado.
 *
 * El sistema origen elige el distrito de una lista; nunca lo teclea.
 */
final readonly class Ubicacion
{
    public function __construct(
        public string $ubigeo,
        public string $direccion,
        /**
         * Código del establecimiento anexo, tal y como la empresa lo tiene declarado
         * en el RUC. Solo pertinente cuando los dos extremos son locales propios.
         */
        public ?string $codigoEstablecimiento = null,
    ) {
        $ubigeoLimpio = trim($ubigeo);

        if ($ubigeoLimpio === '') {
            throw ContratoInvalidoException::campo(
                'ubigeo',
                'El ubigeo es obligatorio: SUNAT identifica el distrito por su código, no por el nombre.',
            );
        }

        if (! ctype_digit($ubigeoLimpio) || strlen($ubigeoLimpio) !== 6) {
            throw ContratoInvalidoException::campo(
                'ubigeo',
                "El ubigeo debe tener 6 dígitos (departamento, provincia y distrito); llegó «{$ubigeo}».",
            );
        }

        if (trim($direccion) === '') {
            throw ContratoInvalidoException::campo(
                'direccion',
                'La dirección es obligatoria en los dos extremos del traslado.',
            );
        }

        if ($codigoEstablecimiento !== null && trim($codigoEstablecimiento) === '') {
            throw ContratoInvalidoException::campo(
                'codigo_establecimiento',
                'El código de establecimiento no puede ir vacío; omítelo si no aplica.',
            );
        }
    }

    public static function desdeArray(array $datos, string $prefijo = ''): self
    {
        // El ubigeo llega como número en cuanto alguien lo serializa sin comillas, y
        // ahí se pierde el cero de la izquierda que llevan Amazonas, Áncash y Apurímac
        // (todos los `01xxxx`). Se rellena antes de validar: es un código, no una
        // cifra, y «10101» es tan inválido como ilegible.
        $ubigeo = $datos['ubigeo'] ?? '';

        if (is_int($ubigeo) || is_float($ubigeo)) {
            $ubigeo = str_pad((string) (int) $ubigeo, 6, '0', STR_PAD_LEFT);
        }

        try {
            return new self(
                ubigeo: (string) $ubigeo,
                direccion: (string) ($datos['direccion'] ?? ''),
                codigoEstablecimiento: $datos['codigo_establecimiento'] ?? null,
            );
        } catch (ContratoInvalidoException $e) {
            // El mensaje ya dice qué pasa; lo que falta es en cuál de los dos extremos,
            // que es justo lo que el usuario necesita para corregirlo.
            throw ContratoInvalidoException::campo(
                $prefijo !== '' ? "{$prefijo}.{$e->campo}" : (string) $e->campo,
                $e->getMessage(),
            );
        }
    }

    public function aArray(): array
    {
        return array_filter([
            'ubigeo'                 => $this->ubigeo,
            'direccion'              => $this->direccion,
            'codigo_establecimiento' => $this->codigoEstablecimiento,
        ], static fn ($v) => $v !== null);
    }
}
