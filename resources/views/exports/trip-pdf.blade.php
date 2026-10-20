<!doctype html>
<html lang="es"><head><meta charset="utf-8"><title>CostoReal · {{ $trip->nombre }}</title>
<style>
body{font-family:'DejaVu Sans',sans-serif;color:#17263b;font-size:11px;line-height:1.5;margin:24px}h1{font-size:22px;margin:8px 0}h2{font-size:15px;margin:24px 0 10px}.brand{color:#254eb5;font-size:18px;font-weight:bold}.sub{color:#52647b}table{width:100%;border-collapse:collapse;margin:12px 0}th,td{padding:7px 5px;border-bottom:1px solid #d9e1ed;text-align:left;word-wrap:break-word}th{background:#edf2ff;font-size:10px}.num{text-align:right}thead{display:table-header-group}tr{page-break-inside:avoid}.totals td{font-weight:bold;background:#edf2ff}.footer{font-size:9px;color:#52647b;margin-top:24px}
</style></head><body>
<div class="brand">CostoReal</div><h1>{{ $trip->nombre }}</h1>
<p class="sub">{{ $trip->company?->nombre ?? 'Sin empresa asignada' }} · {{ $trip->estado }} · Generado {{ now()->format('d/m/Y H:i') }}</p>
@if($trip->report_provenance)<p class="sub">{{ $trip->report_provenance }}</p>@endif
<p>{{ $trip->descripcion }}</p><p>Inicio: {{ $trip->fecha_inicio?->format('d/m/Y') ?? 'Sin fecha' }} · Fin: {{ $trip->fecha_fin?->format('d/m/Y') ?? 'Sin fecha' }}</p>
<p>Tasa de referencia: {{ \App\Modules\Reporting\DecimalFormatter::format($trip->tipo_cambio, 8) }} CLP / {{ $trip->moneda_trabajo }}</p>
<h2>Resumen financiero · CLP</h2><table><tbody>
<tr><td>Compra</td><td class="num">${{ \App\Modules\Reporting\DecimalFormatter::format($stats['total_productos_clp'], 0) }}</td></tr>
<tr><td>Gastos</td><td class="num">${{ \App\Modules\Reporting\DecimalFormatter::format($stats['total_gastos_clp'], 0) }}</td></tr>
<tr class="totals"><td>Costo del lote</td><td class="num">${{ \App\Modules\Reporting\DecimalFormatter::format($stats['costo_total'], 0) }}</td></tr>
<tr><td>Gastos sin asignar</td><td class="num">${{ \App\Modules\Reporting\DecimalFormatter::format($stats['gastos_sin_asignar_clp'], 0) }}</td></tr>
</tbody></table>
<h2>Proyección de venta · CLP</h2>
<p class="sub">{{ $projection['priced_lines'] }} de {{ $projection['lines'] }} líneas con precio. Proyección; no representa ventas realizadas.</p>
<table><tbody>
<tr><td>Ingreso del lote</td><td class="num">{{ $projection['revenue'] === null ? 'Pendiente' : '$'.\App\Modules\Reporting\DecimalFormatter::format($projection['revenue']) }}</td></tr>
<tr><td>Utilidad del lote</td><td class="num">{{ $projection['profit'] === null ? 'Pendiente' : '$'.\App\Modules\Reporting\DecimalFormatter::format($projection['profit']) }}</td></tr>
<tr><td>Margen sobre ventas</td><td class="num">{{ !$projection['complete'] || $projection['margin'] === null ? 'Pendiente' : \App\Modules\Reporting\DecimalFormatter::format($projection['margin'], 2).'%' }}</td></tr>
@if(!$projection['complete'])
<tr><td>Ingreso de líneas con precio</td><td class="num">${{ \App\Modules\Reporting\DecimalFormatter::format($projection['covered_revenue']) }}</td></tr>
<tr><td>Utilidad de líneas con precio</td><td class="num">${{ \App\Modules\Reporting\DecimalFormatter::format($projection['covered_profit']) }}</td></tr>
@endif
</tbody></table>
<h2>Productos y costeo</h2><table><thead><tr><th>Producto</th><th class="num">Cant.</th><th class="num">Compra original</th><th class="num">Compra CLP</th><th class="num">Gastos CLP</th><th class="num">Total CLP</th><th class="num">Unitario CLP</th></tr></thead><tbody>
@forelse($trip->products as $p)<tr><td>{{ $p->nombre }}</td><td class="num">{{ $p->cantidad }}</td><td class="num">{{ \App\Modules\Reporting\DecimalFormatter::format($p->costo_unitario, 2) }} {{ $p->moneda }}</td><td class="num">{{ \App\Modules\Reporting\DecimalFormatter::format($p->subtotal_clp, 0) }}</td><td class="num">{{ \App\Modules\Reporting\DecimalFormatter::format($p->gastos_prorrateados_clp, 0) }}</td><td class="num">{{ \App\Modules\Reporting\DecimalFormatter::format($p->costo_final_total_clp, 0) }}</td><td class="num">{{ \App\Modules\Reporting\DecimalFormatter::format($p->costo_final_unitario_clp, 2) }}</td></tr>
@empty<tr><td colspan="7">Sin productos</td></tr>@endforelse
<tr class="totals"><td colspan="3">TOTAL CLP</td><td class="num">{{ \App\Modules\Reporting\DecimalFormatter::format($stats['total_productos_clp'], 0) }}</td><td class="num">{{ \App\Modules\Reporting\DecimalFormatter::format($stats['total_gastos_prorrateados_clp'], 0) }}</td><td class="num">{{ \App\Modules\Reporting\DecimalFormatter::format($stats['costo_total_final_clp'], 0) }}</td><td></td></tr>
</tbody></table>
<h2>Gastos</h2><table><thead><tr><th>Categoría</th><th>Concepto</th><th class="num">Monto original</th><th class="num">Monto CLP</th></tr></thead><tbody>
@forelse($trip->expenses as $e)<tr><td>{{ $e->category?->nombre ?? 'Sin categoría' }}</td><td>{{ $e->concepto }}</td><td class="num">{{ \App\Modules\Reporting\DecimalFormatter::format($e->monto, 2) }} {{ $e->moneda }}</td><td class="num">{{ \App\Modules\Reporting\DecimalFormatter::format($e->monto_clp, 0) }}</td></tr>@empty<tr><td colspan="4">Sin gastos</td></tr>@endforelse
</tbody></table><p class="footer">Costo unitario presentado con dos decimales; el total de cada línea conserva el resultado del reparto al peso. El precio de venta es una proyección y no representa ventas realizadas.</p>
</body></html>
