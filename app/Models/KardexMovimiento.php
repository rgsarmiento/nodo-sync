<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KardexMovimiento extends Model
{
    protected $table = 'KardexMovimientos';
    protected $primaryKey = 'Id';
    public $timestamps = false;

    protected $fillable = [
        'Id_lst_InventarioTransacciones',
        'Documento',
        'FechaDocumento',
        'CodigoProducto',
        'NombreProducto',
        'Concepto',
        'Cantidad',
        'CantidadTotal',
        'CostoUnitarioCompra',
        'CostoUnitarioPromedio',
        'CodigoAlmacen',
        'LlaveDocumentoVentas',
        'LlaveDocumentoCompras',
        'IdUsuario',
        'NumeroCaja'
    ];
}
