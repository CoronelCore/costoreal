# Detalle y resumen de importación

La página Show compone paneles especializados de resumen, hoja, gastos, precios, documentos, escenarios, revisión e historial. Las pestañas calculan sus conteos desde las relaciones actuales de `trip`; no mantienen copias de conteos. Los guardados Inertia y las recargas posteriores a mutaciones JSON actualizan las props autorizadas. El resumen distingue costos guardados de proyecciones y limita la proyección parcial a líneas con precio.

## Ensayo funcional privado

Ejecutado con las fuentes aplicadas del commit 7d382f6, DB y discos propios. Partió de una importación sintética sin productos, revisión 1. Ningún guardado de este recorrido usó las DB personal o QA originales.

| Acción | Resultado visible sin recarga manual |
|---|---|
| Resumen vacío → Agregar productos | Abre Hoja de productos, sección válida en URL |
| Guardar 2 unidades a 100 CLP | Hoja 0→1, revisión 2, compra 200 CLP |
| Guardar flete de 20 CLP | Gastos 0→1, revisión 3, costo distribuido 220 CLP |
| Guardar precio unitario 150 CLP | Revisión 4, cobertura 1/1, ingreso 300, utilidad 80, margen 26,7 % |
| Adjuntar PNG sintético | Documentos 0→1, revisión 5; recargar conserva archivo/contador/sección |
| Abrir hash inexistente | Recupera Resumen y URL válida, cifras conservadas |
| Resumen → Revisar importación | Abre Revisión con costo conciliado 220 CLP |
| Control → atrás → adelante | Restaura Revisión y regresa a Control; no altera otra ruta |

El resumen a 390 px conserva cifras/conteos y no presenta exceso de ancho global ni fallos en las muestras de contraste medidas. La matriz de vistas base de la fase visual también incluye resumen a 320/390/1024/1280 y ambos temas. No se extrapola este recorrido a todos los estados dinámicos de las funciones avanzadas.

## Ciclo de eventos

`useTabs` valida hashes/secciones, conserva query e historial de Inertia y utiliza la función de desuscripción devuelta por `router.on`. Al desmontarse libera tanto hashchange como finish. La nueva prueba `tests/frontend/useTabs.test.js` monta el composable en componentes Vue con un renderer en memoria y utiliza la suscripción real del router Inertia sobre EventTarget. No sustituye el composable por un mock.

Tres regresiones verifican enlaces profundos/valores inválidos, veinte montajes/desmontajes con cero listeners restantes y cero escrituras después de desmontar, y finish acotado a la importación. El ensayo de navegador verifica el comportamiento integrado de navegación y props. La suite frontend completa pasa con 21 pruebas, sin dependencias nuevas ni cambios en el comportamiento desplegado.

CR-024 queda verificado. Continúan las tareas globales de acciones móviles, estados visuales/accesibles, rendimiento máximo, release/restauración y aceptación humana. Las tablas/archivos originales se compararon de nuevo con el checkpoint visual: 21 tablas de dominio iguales por DB; copias restauradas 29/29, integridad ok/FK cero; entorno y tres archivos originales iguales.
