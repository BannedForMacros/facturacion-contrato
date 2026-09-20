<?php

declare(strict_types=1);

namespace MacSoft\Facturacion\Contrato\Enum;

/**
 * Por qué se mueve la mercadería.
 *
 * Se nombran, no se numeran, igual que el resto del contrato: que «venta» sea el
 * `01` del catálogo 20 de SUNAT es un detalle interno del emisor.
 *
 * POR QUÉ ESTE ENUM LLEVA TANTA LÓGICA:
 *
 * El motivo no es una etiqueta, es lo que decide qué campos pide una guía. Una de
 * traslado entre locales propios no lleva cliente y SÍ lleva código de
 * establecimiento; una venta con entrega a terceros lleva DOS partes distintas
 * (quién compra y quién recibe); una de «otros» exige explicar por escrito qué se
 * está moviendo. Si esa tabla vive repartida entre el formulario del POS y el
 * validador del emisor, los dos se desincronizan y el aviso llega como rechazo de
 * SUNAT, con la mercadería ya en la calle.
 *
 * Está aquí, una sola vez, y los dos lados preguntan.
 */
enum MotivoTraslado: string
{
    /** Le vendí y se lo llevo. El caso corriente. */
    case VENTA = 'VENTA';

    /** Le compré y lo recojo yo. La mercadería viaja hacia mi almacén. */
    case COMPRA = 'COMPRA';

    /** Le facturo a uno y se lo entrego a otro. Son dos partes distintas. */
    case VENTA_ENTREGA_TERCEROS = 'VENTA_ENTREGA_TERCEROS';

    /** Muevo entre mis propios locales. No hay cliente: remitente y destinatario soy yo. */
    case TRASLADO_ENTRE_ESTABLECIMIENTOS = 'TRASLADO_ENTRE_ESTABLECIMIENTOS';

    /** Dejo mercadería para que me la vendan. Sigue siendo mía hasta que se venda. */
    case CONSIGNACION = 'CONSIGNACION';

    /** Vuelve: me la devuelven, o la devuelvo yo al proveedor. */
    case DEVOLUCION = 'DEVOLUCION';

    /** Recojo lo que mandé a transformar. */
    case RECOJO_BIENES_TRANSFORMADOS = 'RECOJO_BIENES_TRANSFORMADOS';

    /** Mando a transformar a un tercero y luego vuelve. */
    case TRASLADO_PARA_TRANSFORMACION = 'TRASLADO_PARA_TRANSFORMACION';

    /** Entró por aduanas y va del puerto a mi almacén. */
    case IMPORTACION = 'IMPORTACION';

    /** Sale del país. */
    case EXPORTACION = 'EXPORTACION';

    /** Venta que el comprador todavía puede rechazar cuando la vea. */
    case VENTA_SUJETA_CONFIRMACION = 'VENTA_SUJETA_CONFIRMACION';

    /** Vendedor ambulante que emite en ruta. */
    case EMISOR_ITINERANTE = 'EMISOR_ITINERANTE';

    /** Traslado a zona primaria aduanera. */
    case ZONA_PRIMARIA = 'ZONA_PRIMARIA';

    /** Cualquier otra cosa. Obliga a explicarla por escrito. */
    case OTROS = 'OTROS';

    /**
     * ¿Hay que escribir a mano de qué se trata?
     *
     * Solo «otros». SUNAT rechaza (error 4055) una guía con este motivo que no
     * traiga la descripción, y la acepta con cualquier texto: es el comodín del
     * catálogo y el precio de usarlo es explicarlo.
     */
    public function exigeDescripcion(): bool
    {
        return $this === self::OTROS;
    }

    /**
     * ¿El destinatario es el propio remitente?
     *
     * En estos dos casos la mercadería no cambia de dueño, solo de sitio. SUNAT lo
     * comprueba (error 2554: «el Destinatario debe ser igual al remitente») y el
     * formulario, en consecuencia, no debe pedir cliente: lo rellena solo con los
     * datos de la empresa.
     */
    public function destinatarioEsElRemitente(): bool
    {
        return match ($this) {
            self::TRASLADO_ENTRE_ESTABLECIMIENTOS,
            self::EMISOR_ITINERANTE => true,
            default                 => false,
        };
    }

    /**
     * ¿Hay que informar a un comprador distinto de quien recibe?
     *
     * Solo en la venta con entrega a terceros: se factura a uno y se descarga en
     * otro. En todos los demás motivos, quien recibe es quien compra, y enviar el
     * comprador por separado sobra.
     */
    public function exigeComprador(): bool
    {
        return $this === self::VENTA_ENTREGA_TERCEROS;
    }

    /**
     * ¿Se espera un comprobante que respalde el traslado?
     *
     * No es obligatorio para SUNAT —se puede despachar antes de facturar—, pero es
     * lo normal, y el formulario debe ofrecerlo arriba en vez de esconderlo. En un
     * traslado entre locales propios no existe tal documento.
     */
    public function esperaComprobante(): bool
    {
        return match ($this) {
            self::VENTA,
            self::VENTA_ENTREGA_TERCEROS,
            self::VENTA_SUJETA_CONFIRMACION,
            self::COMPRA,
            self::DEVOLUCION => true,
            default          => false,
        };
    }

    /**
     * ¿Se mueve entre dos establecimientos declarados de la misma empresa?
     *
     * Cuando es que sí, ambos extremos llevan el código de establecimiento anexo
     * que la empresa tiene dado de alta en SUNAT, además de la dirección.
     */
    public function exigeCodigoEstablecimiento(): bool
    {
        return $this === self::TRASLADO_ENTRE_ESTABLECIMIENTOS;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::VENTA                           => 'Venta',
            self::COMPRA                          => 'Compra',
            self::VENTA_ENTREGA_TERCEROS          => 'Venta con entrega a terceros',
            self::TRASLADO_ENTRE_ESTABLECIMIENTOS => 'Traslado entre establecimientos de la misma empresa',
            self::CONSIGNACION                    => 'Consignación',
            self::DEVOLUCION                      => 'Devolución',
            self::RECOJO_BIENES_TRANSFORMADOS     => 'Recojo de bienes transformados',
            self::TRASLADO_PARA_TRANSFORMACION    => 'Traslado de bienes para transformación',
            self::IMPORTACION                     => 'Importación',
            self::EXPORTACION                     => 'Exportación',
            self::VENTA_SUJETA_CONFIRMACION       => 'Venta sujeta a confirmación del comprador',
            self::EMISOR_ITINERANTE               => 'Traslado por emisor itinerante',
            self::ZONA_PRIMARIA                   => 'Traslado a zona primaria',
            self::OTROS                           => 'Otros',
        };
    }
}
