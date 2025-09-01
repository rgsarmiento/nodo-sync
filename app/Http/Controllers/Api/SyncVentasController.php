<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncVentasRequest;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use Illuminate\Support\Facades\Log;

class SyncVentasController extends Controller
{
    public function store(SyncVentasRequest $request)
    {
        
        $ventas = $request->input('ventas', []);
        $procesadas = [];

        foreach ($ventas as $venta) {
            DB::beginTransaction();
            try {
                $llave = $venta['llave'] ?? null;
                if (!$llave) {
                    throw new \Exception('Llave faltante en una venta.');
                }

                // Verificar si la venta ya existe
                $existe = DB::table('DocumentosVentas')->where('Llave', $llave)->exists();
                if ($existe) {
                    // Si ya existe, marcar como procesada y continuar
                    $procesadas[] = $llave;
                    DB::commit(); // aunque no insertamos, cerramos la transacción
                    continue;
                }

                // preparar datos de encabezado (excluir colecciones)
                $encabezado = collect($venta)
                    ->except(['Productos','Impuestos','Pagos'])
                    ->toArray();

                // asegurar campos obligatorios
                $encabezado['Observacion'] = $encabezado['Observacion'] ?? '';


                // convertir fechas a formato SQL (si vienen como yyyy-mm-dd)
                if (!empty($encabezado['FechaDocumento'])) {
                    $encabezado['FechaDocumento'] = Carbon::parse($encabezado['FechaDocumento'])->toDateString();
                }
                if (!empty($encabezado['FechaVencimiento'])) {
                    $encabezado['FechaVencimiento'] = Carbon::parse($encabezado['FechaVencimiento'])->toDateString();
                }

                 // Insertar encabezado
                DB::table('DocumentosVentas')->insert($encabezado);

                // Fecha de venta para cálculo de costo
                $fechaVenta = $venta['FechaDocumento'];

                // Opcional: CodigoAlmacen desde encabezado
                $codigoAlmacen = $venta['CodigoAlmacen'] ?? $encabezado['CodigoAlmacen'] ?? null;

                // Productos
                foreach ($venta['Productos'] as $prod) {
                    $where = [
                        'LlaveDocumentosVentas' => $llave,
                        'Codigo' => $prod['Codigo'],
                        'Orden' => $prod['Orden'] ?? 0
                    ];

                    // calcular costo
                    $costo = $this->costoBaseParaProducto(
                        (int)$prod['Codigo'],
                        $fechaVenta
                    );

                    // preparar datos para inserción / actualización
                    $prodData = $prod;
                    $prodData['CostoBase'] = $costo;
                    $prodData['LlaveDocumentosVentas'] = $llave;
                    $prodData['NombreImpuestoSaludable'] = $prodData['NombreImpuestoSaludable'] ?? '';// evitar null
                    $prodData['PrefijoImpuestoSaludable'] = $prodData['PrefijoImpuestoSaludable'] ?? '';
                    $prodData['DescripcionDescuentoGeneral'] = $prodData['DescripcionDescuentoGeneral'] ?? '';
                    $prodData['DescripcionDescuentoProgramado'] = $prodData['DescripcionDescuentoProgramado'] ?? '';
                    $prodData['Observacion'] = $prodData['Observacion'] ?? '';
                    $prodData['NombreOtrosImpuestos'] = $prodData['NombreOtrosImpuestos'] ?? '';

                    DB::table('DocumentosVentasProductos')->updateOrInsert(
                        $where,
                        $prodData
                    );
                }

                // Impuestos
                if (!empty($venta['Impuestos'])) {
                    foreach ($venta['Impuestos'] as $imp) {
                        $impWhere = [
                            'LlaveDocumentosVentas' => $llave,
                            'IdImpuesto' => $imp['IdImpuesto'] ?? 0,
                            'CodigoDian' => $imp['CodigoDian'] ?? 0
                        ];
                        $impData = $imp;
                        $impData['LlaveDocumentosVentas'] = $llave;
                        DB::table('DocumentosVentasImpuestos')->updateOrInsert($impWhere, $impData);
                    }
                }

                // Pagos / DistribucionPago
                if (!empty($venta['Pagos'])) {
                    foreach ($venta['Pagos'] as $pago) {
                        $pagoWhere = [
                            'LlaveDocumentosVentas' => $llave,
                            'CodigoDian' => $pago['CodigoDian'] ?? 0,
                            'Nombre' => $pago['Nombre'] ?? ''
                        ];
                        $pagoData = $pago;
                        $pagoData['LlaveDocumentosVentas'] = $llave;
                        DB::table('DocumentosVentasDistribucionPago')->updateOrInsert($pagoWhere, $pagoData);
                    }
                }

                DB::commit();
                $procesadas[] = $llave;

            } catch (\Throwable $e) {
                DB::rollBack();
                // devuelve error detallado para diagnóstico (puedes sanitizar en producción)
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'llave' => $venta['llave'] ?? null
                ], 500);
            }
        }

        return response()->json([
            'status' => 'ok',
            'procesadas' => $procesadas
        ], 200);
    }

    /**
     * Busca el costo base del producto tomando la última compra con fecha <= fechaVenta.
     * - 1) Busca DocumentosCompras JOIN DocumentosComprasProductos por FechaDocumento <= $fechaVenta
     * - 2) Si no encuentra, busca DocumentosComprasProductos por Creado <= fechaVenta
     * - 3) Si no encuentra, devuelve Productos.PrecioCompra
     */
    private function costoBaseParaProducto(string $codigoProducto, string $fechaVenta, ?string $codigoAlmacen = null): float
    {
        $fecha = Carbon::parse($fechaVenta)->toDateString();

        // Intento 1: DocumentosComprasProductos JOIN DocumentosCompras por FechaDocumento
        $query = DB::table('DocumentosComprasProductos as dcp')
            ->join('DocumentosCompras as dc', 'dc.llave', '=', 'dcp.LlaveDocumentosCompras')
            ->where('dcp.Codigo', $codigoProducto)
            ->where('dc.FechaDocumento', '<=', $fecha)
            ->orderBy('dc.FechaDocumento', 'desc')
            ->orderBy('dcp.Id', 'desc')
            ->select('dcp.CostoBase');

        if ($codigoAlmacen) {
            // si deseas filtrar por almacen (ajusta nombre de columna si es diferente)
            if (Schema::hasColumn('DocumentosCompras', 'CodigoAlmacen')) {
                $query->where('dc.CodigoAlmacen', $codigoAlmacen);
            } else {
                // opcional: si DocumentosComprasProductos tiene CodigoAlmacen:
                $query->where('dcp.CodigoAlmacen', $codigoAlmacen);
            }
        }

        $row = $query->first();
        if ($row && $row->CostoBase !== null) {
            return (float)$row->CostoBase;
        }

        // Intento 2: DocumentosComprasProductos por Creado
        $row2 = DB::table('DocumentosComprasProductos')
            ->where('Codigo', $codigoProducto)
            ->where('Creado', '<=', $fecha . ' 23:59:59')
            ->orderBy('Creado', 'desc')
            ->orderBy('Id', 'desc')
            ->select('CostoBase')
            ->first();

        if ($row2 && $row2->CostoBase !== null) {
            return (float)$row2->CostoBase;
        }

        // Intento 3: Productos.PrecioCompra
        //$prod = DB::table('Productos')->where('CodigoProducto', $codigoProducto)->select('PrecioCompra')->first();
        //if ($prod && $prod->PrecioCompra !== null) {
        //    return (float)$prod->PrecioCompra;
        //}

        return 0.0;
    }
}
