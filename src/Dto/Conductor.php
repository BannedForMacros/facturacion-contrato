<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Dto;

use MacSoft\Facturacion\Contrato\Enum\TipoDocumento;
use MacSoft\Facturacion\Contrato\Excepcion\ContratoInvalidoException;

/**
 * Quién conduce.
 *
 * Solo aparece en transporte privado. Si el flete es contratado, el chofer es del
 * transportista y lo declara él en su propia guía: mandarlo aquí sobra y estorba.
 *
 * SOBRE LA LICENCIA: se exige que venga, pero no se le impone formato. Conviven
 * licencias de varias épocas y de varios formatos, y una expresión regular
 * demasiado lista dejaría en tierra a un chofer con papeles en regla. Que el
 * formato lo juzgue SUNAT, que es quien manda; aquí basta con no dejarlo vacío,
 * que es el error que de verdad se comete.
 */
final readonly class Conductor
{
    public function __construct(
        public TipoDocumento $tipoDocumento,
        public string $numeroDocumento,
        public string $nombres,
        public string $apellidos,
        public string $licencia,
        /** El principal es el que figura al frente; los demás van como secundarios. */
        public bool $principal = true,
    ) {
        if (! $tipoDocumento->identifica()) {
            throw ContratoInvalidoException::campo(
                'conductor.tipo_documento',
                'El conductor tiene que ir identificado: «sin documento» no es admisible en una guía.',
            );
        }

        if ($tipoDocumento === TipoDocumento::RUC) {
            throw ContratoInvalidoException::campo(
                'conductor.tipo_documento',
                'El conductor es una persona: identifícalo con DNI, carné de extranjería o pasaporte.',
            );
        }

        // Reusa la validación de longitud y dígitos que ya vive en Documento, para que
        // un DNI de 7 cifras falle igual aquí que en el adquirente.
        new Documento($tipoDocumento, $numeroDocumento);

        if (trim($nombres) === '' || trim($apellidos) === '') {
            throw ContratoInvalidoException::campo(
                'conductor.nombres',
                'El nombre y los apellidos del conductor son obligatorios.',
            );
        }

        if (trim($licencia) === '') {
            throw ContratoInvalidoException::campo(
                'conductor.licencia',
                'La licencia de conducir es obligatoria: sin ella SUNAT rechaza la guía.',
            );
        }
    }

    /** Nombre completo, para pintarlo en la guía y en el buscador. */
    public function nombreCompleto(): string
    {
        return trim("{$this->nombres} {$this->apellidos}");
    }

    public static function desdeArray(array $datos, string $prefijo = 'conductor'): self
    {
        $tipo = $datos['tipo_documento'] ?? TipoDocumento::DNI->value;

        if (! is_string($tipo) || TipoDocumento::tryFrom($tipo) === null) {
            throw ContratoInvalidoException::campo(
                "{$prefijo}.tipo_documento",
                'Tipo de documento del conductor no reconocido.',
            );
        }

        $numero = $datos['numero_documento'] ?? '';

        if (is_int($numero) || is_float($numero)) {
            $numero = (string) $numero;
        }

        try {
            return new self(
                tipoDocumento: TipoDocumento::from($tipo),
                numeroDocumento: (string) $numero,
                nombres: (string) ($datos['nombres'] ?? ''),
                apellidos: (string) ($datos['apellidos'] ?? ''),
                licencia: (string) ($datos['licencia'] ?? ''),
                principal: (bool) ($datos['principal'] ?? true),
            );
        } catch (ContratoInvalidoException $e) {
            $campo = (string) $e->campo;

            // `Documento` señala el campo del adquirente («cliente.documento.numero»)
            // porque es donde vive normalmente. Aquí el dato es otro y el usuario
            // necesita que la pantalla le marque el documento DEL CONDUCTOR.
            if (str_starts_with($campo, 'cliente.documento.')) {
                $campo = "{$prefijo}.numero_documento";
            } elseif (str_starts_with($campo, 'conductor.')) {
                $campo = $prefijo . substr($campo, strlen('conductor'));
            }

            throw ContratoInvalidoException::campo($campo, $e->getMessage());
        }
    }

    public function aArray(): array
    {
        return [
            'tipo_documento'   => $this->tipoDocumento->value,
            'numero_documento' => $this->numeroDocumento,
            'nombres'          => $this->nombres,
            'apellidos'        => $this->apellidos,
            'licencia'         => $this->licencia,
            'principal'        => $this->principal,
        ];
    }
}
