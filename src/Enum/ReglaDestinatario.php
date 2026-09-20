<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Enum;

/**
 * Quién puede figurar como destinatario, según el motivo del traslado.
 *
 * ─── POR QUÉ SON TRES ESTADOS Y NO UN BOOLEANO ─────────────────────────────────
 *
 * Empezó siendo «¿el destinatario es el remitente, sí o no?», y estaba mal. SUNAT
 * distingue TRES situaciones, y se comprobaron una por una contra su validador
 * enviando cada motivo dos veces, con un tercero y con la propia empresa:
 *
 *   · Hay motivos que EXIGEN un tercero. Poner a la propia empresa devuelve el
 *     error 2555. Es el caso de una venta: si te la llevas tú, no la has vendido.
 *   · Hay motivos que EXIGEN a la propia empresa. Poner a un tercero devuelve el
 *     2554. Es el caso de una compra: los bienes vienen HACIA ti.
 *   · Y hay motivos que admiten las dos cosas, porque de verdad dependen del caso.
 *
 * El booleano solo sabía contar dos de las tres, así que forzaba a elegir mal en la
 * tercera: o se prohibía algo que SUNAT acepta, o se permitía algo que rechaza.
 */
enum ReglaDestinatario: string
{
    /** Tiene que recibirlo otro. La propia empresa como destinataria da error 2555. */
    case TERCERO = 'TERCERO';

    /** Lo recibe la propia empresa. Un tercero da error 2554. */
    case PROPIA_EMPRESA = 'PROPIA_EMPRESA';

    /** Vale cualquiera de las dos: lo decide quien emite. */
    case CUALQUIERA = 'CUALQUIERA';

    /** ¿Hay que informar obligatoriamente a un destinatario distinto de la empresa? */
    public function exigeTercero(): bool
    {
        return $this === self::TERCERO;
    }

    /** ¿Está PROHIBIDO informar a un tercero? */
    public function prohibeTercero(): bool
    {
        return $this === self::PROPIA_EMPRESA;
    }

    /** Cómo explicárselo a quien rellena el formulario. */
    public function ayuda(): string
    {
        return match ($this) {
            self::TERCERO        => 'Indica quién recibe la mercadería.',
            self::PROPIA_EMPRESA => 'La recibe tu propia empresa: no hace falta destinatario.',
            self::CUALQUIERA     => 'Puedes indicar quién la recibe, o dejarlo en tu propia empresa.',
        };
    }
}
