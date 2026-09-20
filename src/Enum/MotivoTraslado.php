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

    /**
     * Le compré y lo recojo yo. La mercadería viaja HACIA mi almacén, así que el
     * destinatario soy yo: SUNAT rechaza (2554) una compra dirigida a un tercero.
     */
    case COMPRA = 'COMPRA';

    /** Le facturo a uno y se lo entrego a otro. Son dos partes distintas. */
    case VENTA_ENTREGA_TERCEROS = 'VENTA_ENTREGA_TERCEROS';

    /** Muevo entre mis propios locales. No hay cliente: remitente y destinatario soy yo. */
    case TRASLADO_ENTRE_ESTABLECIMIENTOS = 'TRASLADO_ENTRE_ESTABLECIMIENTOS';

    /** Dejo mercadería para que me la vendan. Sigue siendo mía hasta que se venda. */
    case CONSIGNACION = 'CONSIGNACION';

    /** Vuelve: me la devuelven, o la devuelvo yo al proveedor. */
    case DEVOLUCION = 'DEVOLUCION';

    /** Recojo lo que mandé a transformar. Vuelve a mí, así que el destinatario soy yo. */
    case RECOJO_BIENES_TRANSFORMADOS = 'RECOJO_BIENES_TRANSFORMADOS';

    /** Mando a transformar a un tercero y luego vuelve. */
    case TRASLADO_PARA_TRANSFORMACION = 'TRASLADO_PARA_TRANSFORMACION';

    /** Entró por aduanas y va del puerto a mi almacén. */
    case IMPORTACION = 'IMPORTACION';

    /** Sale del país. */
    case EXPORTACION = 'EXPORTACION';

    /** Venta que el comprador todavía puede rechazar cuando la vea. */
    case VENTA_SUJETA_CONFIRMACION = 'VENTA_SUJETA_CONFIRMACION';

    /**
     * Vendedor ambulante que emite en ruta.
     *
     * Admite las dos cosas: normalmente todavía no hay comprador —por eso sale a
     * vender—, pero SUNAT también acepta que se informe uno.
     */
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
     * Quién puede figurar como destinatario.
     *
     * NO SE DEDUJO DE LA DOCUMENTACIÓN, SE MIDIÓ. Cada motivo se envió dos veces al
     * validador de SUNAT, una con un tercero y otra con la propia empresa, y esta
     * tabla es el resultado literal de esas 28 respuestas. La versión anterior tenía
     * dos motivos mal —compra y recojo de bienes transformados— y los dos habrían
     * salido como rechazo 2554 con la guía ya numerada.
     *
     * La regla de fondo es sencilla en cuanto se ve: si la mercadería SALE de la
     * empresa hacia alguien, hace falta ese alguien; si VIENE hacia la empresa, el
     * destinatario es ella misma.
     */
    public function reglaDestinatario(): ReglaDestinatario
    {
        return match ($this) {
            // Viene hacia la empresa: el destinatario es ella. Un tercero da 2554.
            self::COMPRA,
            self::TRASLADO_ENTRE_ESTABLECIMIENTOS,
            self::RECOJO_BIENES_TRANSFORMADOS => ReglaDestinatario::PROPIA_EMPRESA,

            // Sale hacia alguien: hace falta decir hacia quién. La propia empresa da 2555.
            self::VENTA,
            self::VENTA_ENTREGA_TERCEROS,
            self::VENTA_SUJETA_CONFIRMACION,
            self::CONSIGNACION,
            self::DEVOLUCION,
            self::TRASLADO_PARA_TRANSFORMACION,
            self::EXPORTACION => ReglaDestinatario::TERCERO,

            // Las dos formas se aceptaron. No se fuerza ninguna.
            self::EMISOR_ITINERANTE,
            self::OTROS,
            self::IMPORTACION,
            self::ZONA_PRIMARIA => ReglaDestinatario::CUALQUIERA,
        };
    }

    /**
     * ¿Puede el emisor producir hoy una guía válida con este motivo?
     *
     * Importación y traslado a zona primaria piden datos aduaneros —puerto o
     * aeropuerto de embarque, número de DAM— que todavía no se recogen en ninguna
     * pantalla. SUNAT los rechaza por ello (errores 3440 y 3405), comprobado.
     *
     * Siguen en el catálogo porque existen y porque el día que se añadan esos campos
     * solo habrá que cambiar esta respuesta. Lo que NO se hace es ofrecerlos en un
     * desplegable: dejar elegir un motivo que siempre va a rechazarse es una trampa
     * para quien lo elige.
     */
    public function soportado(): bool
    {
        return match ($this) {
            self::IMPORTACION, self::ZONA_PRIMARIA => false,
            default                                => true,
        };
    }

    /**
     * Los motivos que hoy se pueden emitir, para poblar un desplegable.
     *
     * @return list<self>
     */
    public static function soportados(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $m) => $m->soportado(),
        ));
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
     * Cuando es que sí, los dos extremos llevan el código del establecimiento anexo
     * que la empresa tiene dado de alta en su RUC, además de la dirección. Sin ellos
     * SUNAT rechaza este motivo.
     */
    public function exigeCodigoEstablecimiento(): bool
    {
        return $this === self::TRASLADO_ENTRE_ESTABLECIMIENTOS;
    }

    /*
    |---------------------------------------------------------------------------
    | Códigos de establecimiento: solo en el extremo que es tuyo
    |---------------------------------------------------------------------------
    |
    | Otra regla medida, no deducida. El código de establecimiento anexo viaja en el
    | XML con TU RUC como atributo, así que declararlo significa «este extremo es un
    | local mío». Ponerlo donde no toca es el error 3411, y SUNAT dice en cuál de los
    | dos extremos te has equivocado.
    |
    | Comprobado enviando las cuatro combinaciones:
    |
    |   venta  + establecimiento de partida .... ACEPTADA  (sale de mi almacén)
    |   venta  + establecimiento de llegada .... 3411      (llega a casa del cliente)
    |   compra + establecimiento de llegada .... ACEPTADA  (llega a mi almacén)
    |   compra + establecimiento de partida .... 3411      (sale de casa del proveedor)
    |
    | O sea: el extremo que puedes identificar con un código es el que es tuyo, y cuál
    | de los dos lo es se deduce del sentido del traslado —que es justo lo que ya dice
    | `reglaDestinatario()`—. En un traslado entre locales propios los dos lo son.
    */

    /** ¿Puede declararse el código del local de PARTIDA? */
    public function admiteEstablecimientoPartida(): bool
    {
        return $this->reglaDestinatario() !== ReglaDestinatario::PROPIA_EMPRESA
            || $this === self::TRASLADO_ENTRE_ESTABLECIMIENTOS;
    }

    /** ¿Puede declararse el código del local de LLEGADA? */
    public function admiteEstablecimientoLlegada(): bool
    {
        return $this->reglaDestinatario() === ReglaDestinatario::PROPIA_EMPRESA;
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
