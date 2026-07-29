# macsoft/facturacion-contrato

El vocabulario compartido entre **FacturaMac** (el emisor) y los sistemas que emiten a
través de él (ventoryPOS, gestvenin, los que vengan).

Son DTOs y enums, sin dependencias y sin framework: se instala en cualquier proyecto
PHP 8.2+. No hace peticiones HTTP, no habla con SUNAT y **no calcula impuestos**. Solo
define qué se manda, cómo se llama cada cosa y qué combinaciones son ilegales.

## Por qué existe

Porque el contrato ya existía —repartido entre dos repositorios, en forma de listas de
cadenas escritas a mano— y las dos copias se desincronizaron. Los dos bugs fiscales que
salieron de ahí son el mismo fallo:

**1 · Se podía anular una venta que SUNAT ya conocía.**
Al POS le faltaban `enviado`, `en_resumen` y `pendiente_anulacion` en su lista de
estados "ya informados". Anular localmente una venta ya declarada descuadra la
declaración, y el único instrumento para revertirla es una nota de crédito.

**2 · Ninguna boleta devuelta llegaba a tener su nota de crédito.**
El POS la pedía sobre boletas en `pendiente_resumen`, un estado que el emisor rechaza.
Como toda boleta vive ahí hasta que sale el Resumen Diario a las 23:55, la mayoría de
devoluciones del día caían justo en ese hueco. La respuesta correcta era **esperar**,
no fallar.

Con las respuestas aquí —en `EstadoComprobante::bloqueaAnulacion()`,
`admiteNotaCredito()` y `debeEsperar()`, y no repetidas en cada repositorio— los dos
habrían sido errores al compilar. Hay un test que impide que se repitan: añadir un
estado al enum sin decidir si bloquea la anulación rompe la suite.

Hubo un tercero, el que justifica `NotaCredito`: **una devolución parcial acreditaba la
factura entera** porque los importes enviados se descartaban en silencio. Por eso, aquí,
una nota con líneas no puede construirse sin declarar su `total`.

## Qué NO hace

- **No calcula bases ni impuestos.** Eso es conocimiento fiscal y vive en el emisor, en
  un solo sitio y con reglas de redondeo probadas.
- **No conoce los catálogos de SUNAT.** El contrato habla por nombres (`RUC`, `gravado`,
  `boleta`), no por códigos (`6`, `10`, `03`). Un código equivocado es un error
  silencioso; un nombre equivocado lo caza la validación.
- **No valida lo que depende de la empresa** (umbral de boleta identificada, tasa
  vigente, tolerancia de redondeo): esa configuración solo la conoce el emisor.

Lo que sí valida son las reglas invariantes: una factura necesita RUC y dirección, un
DNI tiene 8 dígitos, una línea necesita cantidad positiva, una nota parcial necesita su
total.

## Instalación

Mientras se desarrolla, con el paquete en local (*path repository*):

```json
{
    "repositories": [
        { "type": "path", "url": "../facturacion-contrato", "options": { "symlink": true } }
    ],
    "require": { "macsoft/facturacion-contrato": "@dev" }
}
```

Desde el repositorio Git (*vcs*), que es lo que se usa en despliegue:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/BannedForMacros/facturacion-contrato.git" }
    ],
    "require": { "macsoft/facturacion-contrato": "^1.0" }
}
```

```bash
composer require macsoft/facturacion-contrato
```

## Uso

```php
use MacSoft\Facturacion\Contrato\Dto\{Venta, Cliente, Documento, Item};
use MacSoft\Facturacion\Contrato\Enum\{TipoDocumento, Impuesto};

$venta = new Venta(
    idempotencyKey: 'venta-8123',                     // sin ella, un reintento emite dos veces
    referenciaExterna: "T{$turno->id}-{$origen->numero}", // única por (empresa, sistema)
    cliente: new Cliente(
        new Documento(TipoDocumento::RUC, '20601030013'),
        'ACME SAC',
        'Av. Siempre Viva 742 - Lima',                // obligatoria en factura
    ),
    items: [
        new Item('Paracetamol 500mg caja x100', 10, 2.50, codigo: 'MED-001'),
        new Item('Jarabe 120ml', 1, 8.00, impuesto: Impuesto::EXONERADO),
    ],
    total: 33.00,                                     // verificación cruzada: si no cuadra, no se emite
);

$http->post('/api/v1/ventas', $venta->aArray());      // tipo → `auto` resuelve a factura por el RUC
```

Si algo no cumple el contrato, el constructor lanza `ContratoInvalidoException` con el
campo exacto (`$e->campo`) — antes de salir por la red y antes de consumir correlativo.

La respuesta se lee igual de vuelta:

```php
$respuesta = Respuesta::desdeArray($json);

if ($respuesta->estado?->bloqueaAnulacion()) {
    // SUNAT ya lo conoce: solo se revierte con nota de crédito.
}
```

## Contenido

| | |
|---|---|
| `Dto\Venta` · `Cliente` · `Documento` · `Item` · `Pago` | Lo que se envía |
| `Dto\NotaCredito` | Anulación total (sin líneas) o devolución parcial (con líneas + `total`) |
| `Dto\Respuesta` · `Comprobante` · `Totales` · `Aviso` | Lo que se recibe |
| `Dto\ErrorRespuesta` | Error con código estable, campo culpable y política de reintento |
| `Enum\EstadoComprobante` | Los estados y sus tres preguntas con consecuencias fiscales |
| `Enum\CodigoError` | Catálogo cerrado, con su HTTP y si se reintenta |
| `Enum\TipoComprobante` · `TipoDocumento` · `Impuesto` · `CondicionPago` · `MotivoNotaCredito` | El vocabulario |

## Tests

```bash
composer install
composer test
```

Los tests no comprueban implementación: comprueban **decisiones**. Si alguien cambia una
respuesta del enum de estados o del catálogo de errores, tiene que venir a la tabla del
test y justificarlo por escrito.

## Convenciones

- Todos los DTOs son `final readonly class` y validan en el constructor.
- Las claves de array son `snake_case` (es el JSON del contrato); las propiedades PHP son
  `camelCase`.
- `desdeArray(array): self` y `aArray(): array` en todos. Los opcionales ausentes se
  omiten, no viajan como `null`.
- La especificación completa está en `CONTRATO_API.md`, en el repositorio de FacturaMac.
