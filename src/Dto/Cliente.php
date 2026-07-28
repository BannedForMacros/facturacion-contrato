<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Dto;

use MacSoft\Facturacion\Contrato\Excepcion\ContratoInvalidoException;

/**
 * Adquirente del comprobante.
 *
 * El emisor hace *upsert* por (empresa, tipo de documento, número): el sistema
 * origen nunca gestiona identificadores de cliente ajenos.
 */
final readonly class Cliente
{
    public function __construct(
        public Documento $documento,
        public string $nombre,
        public ?string $direccion = null,
        public ?string $email = null,
    ) {
        if (trim($nombre) === '') {
            throw ContratoInvalidoException::campo(
                'cliente.nombre',
                'El nombre o razón social del cliente es obligatorio.',
            );
        }

        if ($email !== null && $email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ContratoInvalidoException::campo('cliente.email', 'El correo del cliente no es válido.');
        }
    }

    /** Cliente genérico de mostrador, para boletas por debajo del umbral. */
    public static function generico(string $nombre = 'CLIENTE VARIOS'): self
    {
        return new self(Documento::sinDocumento(), $nombre);
    }

    /** ¿Reúne lo que SUNAT exige para figurar en una factura? */
    public function aptoParaFactura(): bool
    {
        return $this->documento->tipo->esEmpresa() && trim((string) $this->direccion) !== '';
    }

    public static function desdeArray(array $datos): self
    {
        if (! isset($datos['documento']) || ! is_array($datos['documento'])) {
            throw ContratoInvalidoException::campo('cliente.documento', 'Falta el documento del cliente.');
        }

        return new self(
            Documento::desdeArray($datos['documento']),
            (string) ($datos['nombre'] ?? ''),
            $datos['direccion'] ?? null,
            $datos['email'] ?? null,
        );
    }

    public function aArray(): array
    {
        return array_filter([
            'documento' => $this->documento->aArray(),
            'nombre'    => $this->nombre,
            'direccion' => $this->direccion,
            'email'     => $this->email,
        ], static fn ($v) => $v !== null);
    }
}
