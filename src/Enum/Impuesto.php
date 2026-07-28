<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Enum;

/**
 * Cómo tributa una línea de venta.
 *
 * IMPORTANTE — esto NO responde a "¿el precio ya trae el impuesto?". Esa es una
 * pregunta distinta y vive en `Item::$precioIncluyeImpuesto`. Son ortogonales: un
 * producto gravado puede llegar con precio bruto o neto.
 *
 * Mezclarlas es un error habitual (ventoryPOS lo hace hoy con un único booleano
 * `incluye_igv`, donde `false` significa *exonerado*) y produce una clase entera de
 * errores de importe. Cada sistema traduce su modelo a este enum en SU adaptador.
 *
 * Aquí NO hay códigos de SUNAT a propósito: el catálogo 07 (`10`, `20`, `30`…) es
 * conocimiento fiscal y vive en el emisor. Este paquete solo fija el vocabulario.
 */
enum Impuesto: string
{
    /** Lo normal: paga el impuesto general. */
    case GRAVADO = 'gravado';

    /** Exonerado por ley: medicamentos, libros, ciertos productos agrícolas. */
    case EXONERADO = 'exonerado';

    /** Fuera del ámbito del impuesto. */
    case INAFECTO = 'inafecto';

    /** Venta al exterior. */
    case EXPORTACION = 'exportacion';

    /** Bonificaciones y muestras: se entregan sin cobro. */
    case GRATUITO = 'gratuito';

    /** ¿Esta línea genera impuesto? */
    public function generaImpuesto(): bool
    {
        return $this === self::GRAVADO;
    }

    /**
     * ¿Suma al importe que paga el cliente?
     *
     * Lo gratuito se declara ante SUNAT pero no se cobra, así que no entra en el
     * total. Olvidarlo descuadra la comprobación de totales.
     */
    public function sumaAlTotal(): bool
    {
        return $this !== self::GRATUITO;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::GRAVADO     => 'Gravado',
            self::EXONERADO   => 'Exonerado',
            self::INAFECTO    => 'Inafecto',
            self::EXPORTACION => 'Exportación',
            self::GRATUITO    => 'Gratuito',
        };
    }
}
