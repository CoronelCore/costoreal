<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Viaje - {{ $trip->nombre }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #1f2937;
            line-height: 1.6;
            padding: 40px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 3px solid #6366f1;
        }

        .logo {
            font-size: 32px;
            font-weight: bold;
            color: #6366f1;
            margin-bottom: 10px;
        }

        .report-title {
            font-size: 24px;
            font-weight: bold;
            color: #111827;
            margin-top: 10px;
        }

        .report-date {
            font-size: 12px;
            color: #6b7280;
            margin-top: 5px;
        }

        .section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
        }

        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }

        .info-row {
            display: table-row;
        }

        .info-label {
            display: table-cell;
            padding: 8px 15px 8px 0;
            font-weight: 600;
            color: #374151;
            width: 30%;
        }

        .info-value {
            display: table-cell;
            padding: 8px 0;
            color: #1f2937;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        thead {
            background-color: #f3f4f6;
        }

        th {
            padding: 12px 10px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #d1d5db;
        }

        td {
            padding: 10px;
            font-size: 11px;
            border-bottom: 1px solid #e5e7eb;
        }

        tbody tr:hover {
            background-color: #f9fafb;
        }

        .text-right {
            text-align: right;
        }

        .category-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            color: white;
        }

        .total-row {
            background-color: #f3f4f6;
            font-weight: bold;
        }

        .total-row td {
            padding: 12px 10px;
            border-top: 2px solid #6366f1;
            border-bottom: 2px solid #6366f1;
        }

        .summary-boxes {
            display: table;
            width: 100%;
            margin-top: 30px;
        }

        .summary-box {
            display: table-cell;
            width: 33.33%;
            padding: 20px;
            text-align: center;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
        }

        .summary-label {
            font-size: 11px;
            color: #6b7280;
            margin-bottom: 5px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .summary-value {
            font-size: 20px;
            font-weight: bold;
            color: #111827;
        }

        .summary-box.highlight {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .summary-box.highlight .summary-label,
        .summary-box.highlight .summary-value {
            color: white;
        }

        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 10px;
            color: #9ca3af;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="logo">$ CostoReal</div>
        <div class="report-title">Reporte de Viaje</div>
        <div class="report-date">Generado el {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    <!-- Información del Viaje -->
    <div class="section">
        <div class="section-title">Información del Viaje</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Nombre:</div>
                <div class="info-value">{{ $trip->nombre }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Descripción:</div>
                <div class="info-value">{{ $trip->descripcion ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Fecha Inicio:</div>
                <div class="info-value">{{ $trip->fecha_inicio->format('d/m/Y') }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Fecha Fin:</div>
                <div class="info-value">{{ $trip->fecha_fin->format('d/m/Y') }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Duración:</div>
                <div class="info-value">{{ $trip->fecha_inicio->diffInDays($trip->fecha_fin) }} días</div>
            </div>
            <div class="info-row">
                <div class="info-label">Moneda Base:</div>
                <div class="info-value">{{ $trip->moneda_base }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Moneda Trabajo:</div>
                <div class="info-value">{{ $trip->moneda_trabajo }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Tipo de Cambio:</div>
                <div class="info-value">{{ number_format($trip->tipo_cambio, 2, ',', '.') }}</div>
            </div>
        </div>
    </div>

    <!-- Resumen de Gastos por Categoría -->
    @if($expensesByCategory->isNotEmpty())
    <div class="section">
        <div class="section-title">Resumen de Gastos por Categoría</div>
        <table>
            <thead>
                <tr>
                    <th>Categoría</th>
                    <th class="text-right">Cantidad Gastos</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($expensesByCategory as $category)
                <tr>
                    <td>
                        <span class="category-badge" style="background-color: {{ $category['color'] }}">
                            {{ $category['categoria'] }}
                        </span>
                    </td>
                    <td class="text-right">{{ $category['gastos']->count() }}</td>
                    <td class="text-right">${{ number_format($category['total'], 0, ',', '.') }}</td>
                </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="2">TOTAL GASTOS</td>
                    <td class="text-right">${{ number_format($trip->total_gastos, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    @endif

    <!-- Detalle de Gastos -->
    @if($trip->expenses->isNotEmpty())
    <div class="section page-break">
        <div class="section-title">Detalle de Gastos</div>
        <table>
            <thead>
                <tr>
                    <th>Categoría</th>
                    <th>Concepto</th>
                    <th class="text-right">Moneda</th>
                    <th class="text-right">Monto Original</th>
                    <th class="text-right">Monto CLP</th>
                </tr>
            </thead>
            <tbody>
                @foreach($trip->expenses as $expense)
                <tr>
                    <td>
                        <span class="category-badge" style="background-color: {{ $expense->category->color }}">
                            {{ $expense->category->nombre }}
                        </span>
                    </td>
                    <td>{{ $expense->concepto }}</td>
                    <td class="text-right">{{ $expense->moneda }}</td>
                    <td class="text-right">{{ number_format($expense->monto, 2, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($expense->monto_clp, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Detalle de Productos -->
    @if($trip->products->isNotEmpty())
    <div class="section">
        <div class="section-title">Productos y Prorrateo</div>
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th class="text-right">Cant.</th>
                    <th class="text-right">P. Unit.</th>
                    <th class="text-right">Subtotal</th>
                    <th class="text-right">% Prorr.</th>
                    <th class="text-right">Gastos Prorr.</th>
                    <th class="text-right">Costo Final</th>
                    <th class="text-right">C. Unit. Final</th>
                </tr>
            </thead>
            <tbody>
                @foreach($trip->products as $product)
                <tr>
                    <td>{{ $product->nombre }}</td>
                    <td class="text-right">{{ $product->cantidad }}</td>
                    <td class="text-right">${{ number_format($product->precio_unitario, 0, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($product->subtotal, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($product->porcentaje_prorrateo, 2) }}%</td>
                    <td class="text-right">${{ number_format($product->gastos_prorrateados, 0, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($product->costo_final, 0, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($product->costo_unitario, 0, ',', '.') }}</td>
                </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="3">TOTAL PRODUCTOS</td>
                    <td class="text-right">${{ number_format($trip->products->sum('subtotal'), 0, ',', '.') }}</td>
                    <td></td>
                    <td class="text-right">${{ number_format($trip->products->sum('gastos_prorrateados'), 0, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($trip->products->sum('costo_final'), 0, ',', '.') }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>
    @endif

    <!-- Resumen Total -->
    <div class="summary-boxes">
        <div class="summary-box">
            <div class="summary-label">Total Gastos</div>
            <div class="summary-value">${{ number_format($trip->total_gastos, 0, ',', '.') }}</div>
        </div>
        <div class="summary-box">
            <div class="summary-label">Total Productos</div>
            <div class="summary-value">${{ number_format($trip->total_productos, 0, ',', '.') }}</div>
        </div>
        <div class="summary-box highlight">
            <div class="summary-label">Costo Total del Viaje</div>
            <div class="summary-value">${{ number_format($trip->costo_total, 0, ',', '.') }}</div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>Generado por CostoReal - Sistema de Gestión de Viajes y Prorrateo</p>
        <p>{{ now()->format('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>
