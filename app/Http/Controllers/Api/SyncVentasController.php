<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Str;

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
                    ->except(['Productos', 'Impuestos', 'Pagos'])
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
                    $costo = $this->costoBaseParaProducto($prod['Codigo'], $fechaVenta);

                    // preparar datos para inserción / actualización
                    $prodData = $prod;
                    $prodData['CostoBase'] = $costo;
                    $prodData['LlaveDocumentosVentas'] = $llave;
                    $prodData['NombreImpuestoSaludable'] = $prodData['NombreImpuestoSaludable'] ?? ''; // evitar null
                    $prodData['PrefijoImpuestoSaludable'] = $prodData['PrefijoImpuestoSaludable'] ?? '';
                    $prodData['DescripcionDescuentoGeneral'] = $prodData['DescripcionDescuentoGeneral'] ?? '';
                    $prodData['DescripcionDescuentoProgramado'] = $prodData['DescripcionDescuentoProgramado'] ?? '';
                    $prodData['Observacion'] = $prodData['Observacion'] ?? '';
                    $prodData['NombreOtrosImpuestos'] = $prodData['NombreOtrosImpuestos'] ?? '';

                    //($prodData);
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

                if ($venta['Tipo'] === 'FV') {
                    // ajustar inventario solo para FACTURA o CREDITO FISCAL
                    foreach ($venta['Productos'] as $prod) {
                        //$this->restarCantidadActual($prod['Codigo'], $prod['Cantidad']);
                        $this->registrarSalida($venta, $prod);
                    }
                } else if ($venta['Tipo'] === 'NC') {
                    // para NOTA CREDITO, aumentar inventario
                    // foreach ($venta['Productos'] as $prod) {
                    //     $this->sumarCantidadActual($prod['Codigo'], $prod['Cantidad']);
                    // }
                    $this->procesarNotaCredito($venta);
                }

                DB::commit();
                $procesadas[] = $llave;
            } catch (\Throwable $e) {
                DB::rollBack();
                // devuelve error detallado para diagnóstico (puedes sanitizar en producción)
                return response()->json([
                     'status' => 'error',
                     'message' => $e->getMessage(),
                     'file'    => $e->getFile(),
                     'line'    => $e->getLine(),
                     'llave'   => $venta['llave'] ?? null,
                     'trace' => $e->getTraceAsString(), // descomenta si quieres TODO el stack
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


        $row = $query->first();
        if ($row && $row->CostoBase !== null) {
            return (float)$row->CostoBase;
        }

        $query_almacen = DB::table('InventarioAlmacen')
            ->where('CodigoProducto', $codigoProducto)->select('CostoUnitarioPromedio')->first();
        if ($query_almacen && $query_almacen->CostoUnitarioPromedio !== null) {
            return (float)$query_almacen->CostoUnitarioPromedio;
        }
        return 0.0;
    }


    function sumarCantidadActual($codigoProducto, $cantidad)
    {
        return DB::table('InventarioAlmacen')
            ->where('CodigoProducto', $codigoProducto)
            ->increment('CantidadActual', $cantidad);
    }

    function restarCantidadActual($codigoProducto, $cantidad)
    {
        return DB::table('InventarioAlmacen')
            ->where('CodigoProducto', $codigoProducto)
            ->decrement('CantidadActual', $cantidad);
    }

    function registrarSalida(array $venta, array $producto)
    {
        DB::transaction(function () use ($venta, $producto) {

            $llaveVenta = $venta['Llave'] ?? null;
            $fechaDocumento = $venta['FechaDocumento'] ?? now()->toDateString();
            $codigoAlmacen = $venta['CodigoAlmacen'] ?? null;

            // 1. Leer stock actual con UPDLOCK
            $row = DB::table('InventarioAlmacen')
                ->where('CodigoProducto', $producto['Codigo'])
                ->lockForUpdate()
                ->first();

            $stockAnterior = $row?->CantidadActual ?? 0;
            $costoPromedio = $row?->CostoUnitarioPromedio ?? 0;

            // 2. Validar stock
            //if ($stockAnterior < $producto['Cantidad']) {
            //  throw new \Exception("Stock insuficiente para el producto {$producto['Codigo']}");
            //}

            // 3. Calcular nuevo stock
            $nuevoStock = $stockAnterior - $producto['Cantidad'];

            // 4. Actualizar Inventario
            DB::table('InventarioAlmacen')
                ->where('CodigoProducto', $producto['Codigo'])
                ->update(['CantidadActual' => $nuevoStock]);

            // 5. Insertar en KardexMovimientos
            DB::table('KardexMovimientos')->insert([
                'Id_lst_InventarioTransacciones' => 5, // salida por venta
                'Documento' => $venta['Prefijo'] . '-' . $venta['Consecutivo'] ?? null,
                'FechaDocumento' => $fechaDocumento,
                'CodigoProducto' => $producto['Codigo'],
                'NombreProducto' => $producto['Nombre'] ?? '',
                'Concepto' => '- Salida por venta ' . $venta['Prefijo'] . '-' . $venta['Consecutivo'] ?? '',
                'Cantidad' => $producto['Cantidad'], // negativo para salidas
                'CantidadTotal' => $nuevoStock,
                'CostoUnitarioCompra' => 0,
                'CostoUnitarioPromedio' => $costoPromedio,
                'CodigoAlmacen' => $codigoAlmacen,
                'LlaveDocumentoVentas' => $llaveVenta,
                'LlaveDocumentoCompras' => null,
                'IdUsuario' => $venta['IdUsuario'] ?? 1,
                'NumeroCaja' => $venta['CajaNumero'] ?? 0,
                'Creado' => now(),
            ]);
        });
    }


    // public function procesarNotaCredito(array $notaCredito, array $producto)
    // {
    //     DB::transaction(function () use ($notaCredito, $producto) {

    //         // 1. Extraer TrackID de las observaciones
    //         $observaciones = $notaCredito['Observaciones'] ?? '';
    //         preg_match('/TrackID:\s*(\d+)/', $observaciones, $matches);
    //         if (empty($matches[1])) {
    //             throw new \Exception("No se encontró TrackID en observaciones");
    //         }
    //         $trackId = $matches[1];

    //         // 2. Obtener detalles de la venta original
    //         $detalles = DB::table('DocumentosVentasProductos')
    //             ->where('LlaveDocumentosVentas', $trackId)
    //             ->where('Codigo', $producto['Codigo'])
    //             ->first();

    //         if ($detalles->isEmpty()) {
    //             throw new \Exception("La venta con TrackID {$trackId} no tiene productos registrados");
    //         }

    //         // 3. Registrar devolución producto por producto
    //         foreach ($detalles as $detalle) {
    //             $producto = [
    //                 'CodigoProducto' => $detalle->CodigoProducto,
    //                 'NombreProducto' => $detalle->NombreProducto,
    //                 'Cantidad' => $detalle->Cantidad,
    //                 'CostoBase' => $detalle->CostoBase,
    //                 'LlaveDocumentoVentas' => $trackId,
    //             ];

    //             $this->registrarDevolucion($notaCredito, $producto);
    //         }
    //     });
    // }



    public function procesarNotaCredito(array $notaCredito)
{
    DB::transaction(function () use ($notaCredito) {

        // 1. Extraer TrackID de las observaciones
        $observaciones = $notaCredito['Observaciones'] ?? '';
        preg_match('/TrackID:\s*(\d+)/', $observaciones, $matches);
        if (empty($matches[1])) {
            throw new \Exception("No se encontró TrackID en observaciones");
        }
        $trackId = $matches[1];

        // 2. Buscar venta original
        $venta = DB::table('Ventas')->where('TrackID', $trackId)->first();
        if (!$venta) {
            throw new \Exception("No se encontró la venta con TrackID {$trackId}");
        }

        // 3. Recorrer productos enviados en la nota crédito
        if (empty($notaCredito['Productos'])) {
            throw new \Exception("La nota crédito no tiene productos");
        }

        foreach ($notaCredito['Productos'] as $prodNC) {
            // Buscar el producto original en la factura
            $detalleOriginal = DB::table('VentasDetalle')
                ->where('LlaveDocumentosVentas', $trackId)
                ->where('Codigo', $prodNC->Codigo)
                ->first();
           

            // Armar el objeto producto para la devolución
            $producto = [
                'Codigo' => $prodNC->Codigo,
                'Nombre' => $prodNC->NombreProducto,
                'Cantidad' => $prodNC['Cantidad'], // 👈 cantidad de la NC, no de la factura completa
                'PrecioBase' => $detalleOriginal->PrecioUnitario,
                'LlaveDocumentoVentas' => $venta->Llave,
            ];

            // Registrar devolución (esto suma al inventario y deja el movimiento en Kardex)
            $this->registrarDevolucion($notaCredito, $producto);
        }
    });
}


    function registrarDevolucion(array $notaCredito, array $producto)
    {
        DB::transaction(function () use ($notaCredito, $producto) {
            
            $fechaDocumento = $notaCredito['FechaDocumento'] ?? now()->toDateString();
            $codigoAlmacen = $notaCredito['CodigoAlmacen'] ?? null;

            // 1. Leer stock actual con UPDLOCK
            $row = DB::table('InventarioAlmacen')
                ->where('CodigoProducto', $producto['CodigoProducto'])
                ->lockForUpdate()
                ->first();

            $stockAnterior = $row?->CantidadActual ?? 0;
            $costoAnterior = $row?->CostoUnitarioPromedio ?? 0;

            // 2. Datos de la devolución (de la factura original)
            $cantidadDevuelta = $producto['Cantidad'];
            $costoBase = $producto['CostoBase'] ?? 0;

            // 3. Calcular costo promedio (nuevo ingreso al inventario)
            $totalAnterior = $stockAnterior * $costoAnterior;
            $totalNuevo = $cantidadDevuelta * $costoBase;

            $nuevoStock = $stockAnterior + $cantidadDevuelta;
            $nuevoCostoPromedio = $nuevoStock > 0
                ? ($totalAnterior + $totalNuevo) / $nuevoStock
                : $costoAnterior;

            // 4. Actualizar Inventario
            DB::table('InventarioAlmacen')
                ->where('CodigoProducto', $producto['CodigoProducto'])
                ->update([
                    'CantidadActual' => $nuevoStock,
                    'CostoUnitarioPromedioAnterior' => $costoAnterior,
                    'CostoUnitarioPromedio' => $nuevoCostoPromedio,
                ]);

            // 5. Insertar en KardexMovimientos
            DB::table('KardexMovimientos')->insert([
                'Id_lst_InventarioTransacciones' => 2, // entrada por devolución de venta
                'Documento' => $notaCredito['Prefijo'] . '-' . $notaCredito['Consecutivo'] ?? null,
                'FechaDocumento' => $fechaDocumento,
                'CodigoProducto' => $producto['CodigoProducto'],
                'NombreProducto' => $producto['NombreProducto'] ?? '',
                'Concepto' => '+ Devolución por nota crédito ' . $notaCredito['Prefijo'] . '-' . $notaCredito['Consecutivo'] ?? '',
                'Cantidad' => $cantidadDevuelta, // positivo porque entra
                'CantidadTotal' => $nuevoStock,
                'CostoUnitarioCompra' => $costoBase, // lo que costó originalmente
                'CostoUnitarioPromedio' => $nuevoCostoPromedio,
                'CodigoAlmacen' => $codigoAlmacen,
                'LlaveDocumentoVentas' => $producto['LlaveDocumentoVentas'] ?? null,
                'LlaveDocumentoCompras' => null,
                'IdUsuario' => $notaCredito['IdUsuario'] ?? 1,
                'NumeroCaja' => $notaCredito['CajaNumero'] ?? 0,
                'Creado' => now(),
            ]);
        });
    }
}
