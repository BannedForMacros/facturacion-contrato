<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Dto;

use MacSoft\Facturacion\Contrato\Enum\TipoDocumento;
use MacSoft\Facturacion\Contrato\Excepcion\ContratoInvalidoException;

/**
 * La empresa de transporte contratada.
 *
 * Solo aparece en transporte público, y entonces es TODO lo que se informa del
 * transporte: ni placa ni chofer. Esos datos son suyos y los declara en su propia
 * guía de transportista, que es un documento distinto y no nos corresponde.
 *
 * OJO CON LA CONFUSIÓN HABITUAL: contratar un flete no libera al remitente de
 * emitir su guía. Quien manda la mercadería emite la suya siempre; el transportista
 * emite ADEMÁS la suya. Creer lo contrario es lo que acaba en multa.
 */
final readonly class Transportista
{
    public function __construct(
        public Documento $documento,
        public string $razonSocial,
        /** Registro del MTC. Lo tienen las empresas de transporte de carga. */
        public ?string $registroMtc = null,
    ) {
        if (! $documento->tipo->esEmpresa()) {
            throw ContratoInvalidoException::campo(
                'transportista.documento',
                'El transportista se identifica con RUC: una empresa de transporte siempre lo tiene.',
            );
        }

        if (trim($razonSocial) === '') {
            throw ContratoInvalidoException::campo(
                'transportista.razon_social',
                'La razón social del transportista es obligatoria.',
            );
        }
    }

    /** Atajo para construirlo desde lo que devuelve una consulta de RUC. */
    public static function conRuc(string $ruc, string $razonSocial, ?string $registroMtc = null): self
    {
        return new self(new Documento(TipoDocumento::RUC, $ruc), $razonSocial, $registroMtc);
    }

    public static function desdeArray(array $datos): self
    {
        $numero = $datos['ruc'] ?? ($datos['documento']['numero'] ?? null);

        if (is_int($numero) || is_float($numero)) {
            $numero = (string) $numero;
        }

        if (! is_string($numero) || trim($numero) === '') {
            throw ContratoInvalidoException::campo(
                'transportista.ruc',
                'Falta el RUC del transportista.',
            );
        }

        try {
            return new self(
                documento: new Documento(TipoDocumento::RUC, $numero),
                razonSocial: (string) ($datos['razon_social'] ?? ''),
                registroMtc: $datos['registro_mtc'] ?? null,
            );
        } catch (ContratoInvalidoException $e) {
            // `Documento` culpa al adquirente por defecto; aquí el dato es otro.
            $campo = str_starts_with((string) $e->campo, 'cliente.documento.')
                ? 'transportista.ruc'
                : (string) $e->campo;

            throw ContratoInvalidoException::campo($campo, $e->getMessage());
        }
    }

    public function aArray(): array
    {
        return array_filter([
            'ruc'          => $this->documento->numero,
            'razon_social' => $this->razonSocial,
            'registro_mtc' => $this->registroMtc,
        ], static fn ($v) => $v !== null);
    }
}
