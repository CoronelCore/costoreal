# Política operativa de costos · versión 1

Uso personal preparado para crecer. Moneda base CLP. El sistema calcula el costo operativo de una compra y la proyección de sus precios; no convierte esa proyección en ventas realizadas ni en un asiento contable.

## Entradas y precisión

Los importes y tasas se intercambian como cadenas decimales. Las cantidades son enteros positivos. La compra de una línea es cantidad × costo unitario original × tasa. Se redondea el subtotal de la línea al peso CLP, una sola vez, con HALF_UP. El costo unitario final conserva ocho decimales y se presenta con dos; multiplicar un unitario mostrado puede diferir del total conservado. El motor no usa floats.

La persistencia de cada lote está certificada hasta 9.000.000.000.000.000 CLP; el servidor rechaza totales superiores dentro de la transacción. El motor puro soporta magnitudes mayores, pero esa capacidad no se confunde con la capacidad certificada de la aplicación. Las exportaciones XLSX usan celdas numéricas para importes y texto explícito para nombres; los tipos numéricos de Excel tienen sus propias limitaciones de precisión.

## Tasas y procedencia

Una operación en CLP usa tasa 1. Para una operación estimada en la moneda de trabajo se usa la tasa de referencia del lote. Al cambiar esa tasa se revalúan tanto productos como gastos estimados.

Una operación pagada conserva su tasa aplicada, fecha y fuente. No se sustituye una tasa desconocida por 1. La referencia del lote debe ser positiva y se admite hasta ocho decimales. Cambiar la moneda de trabajo se rechaza si deja operaciones sin conversión.

Ejemplo: USD 100 de compra + USD 10 de gasto estimado, referencia inicial 900: 90.000 + 9.000 = 99.000 CLP. Al cambiar a 1.000: 100.000 + 10.000 = 110.000 CLP. El antiguo resultado 109.000 mezclaba una compra revaluada y un gasto sin revaluar. Si el gasto se pagó a 900 y se registra esa tasa histórica, 109.000 sí es el resultado correcto y explicable.

Los registros heredados tienen fuente `legacy-unverified` y modo estimado como compatibilidad con el esquema original. Esa clasificación no demuestra que un gasto realmente se haya pagado. No se inventa una fecha, comprobante ni fuente histórica. La migración de estructuras no recalcula silenciosamente las compras existentes; el comparador y la conciliación de datos heredados constituyen una entrega separada.

## Gastos y reparto

Todo gasto registrado en el lote integra su costo operativo. La distinción entre gastos capitalizables y gastos del período, los impuestos recuperables y su tratamiento contable requiere una política adicional antes de habilitar esas clasificaciones. No se deduce IVA ni se aplican impuestos de forma implícita.

El reparto predeterminado es proporcional al valor de compra convertido de cada línea. Alternativas explícitas: unidades, peso total de la línea en kg, volumen total de la línea en m³ y asignación a un producto. Peso y volumen ausentes generan un error; no se convierten en cero.

Por cada gasto, se calcula el reparto exacto, se toman los pesos completos inferiores y se asignan los pesos residuales a las fracciones mayores. Los empates usan el identificador del producto en orden natural, de forma estable. El gasto se distribuye exactamente; un total de pesos de reparto igual a cero deja el gasto explícitamente sin asignar. No hay una distribución oculta de respaldo.

Compra de tres líneas iguales, gasto 2 CLP: las asignaciones estables son 1, 1 y 0 CLP. Reordenar la entrada no cambia el resultado. Compra con valor total cero y gasto 20 CLP por valor: quedan 20 CLP sin distribuir y el cierre se bloquea.

## Proyección y cierre

El precio de venta es unitario y se expresa en CLP. Un precio ausente significa pendiente. No se usa cero para calcular una pérdida ficticia. Si falta cualquier precio, la proyección completa del lote permanece pendiente; se puede mostrar una proyección parcial de las líneas con precio. Una pérdida conserva su signo.

Margen sobre venta = (ingreso − costo) / ingreso. Recargo sobre costo es una magnitud distinta. Las comisiones, redondeo comercial y propuestas por objetivo se entregan con casos de aceptación propios; no se aplican de forma automática a precios existentes.

Cerrar exige productos, tasas conocidas y gasto sin asignar igual a cero. Los precios pendientes y los comprobantes ausentes son advertencias. Un cierre bloquea las mutaciones normales y conserva una ejecución con entradas, resultado, reparto, actor y versión. Reabrir conserva esa ejecución y crea una nueva revisión. Los reportes históricos se reconstruyen desde el snapshot solicitado y no desde los precios o tasas actuales.

La versión esperada permite rechazar escrituras desde una pestaña obsoleta. Los formularios renovados la envían; la obligatoriedad en todos los clientes heredados se completa al migrar las pantallas y sus contratos.
