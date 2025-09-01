<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarioAlmacen extends Model
{
    protected $table = 'DocumentosCompras';
    protected $primaryKey = 'Id';
    public $timestamps = false;
    protected $fillable = ['CodigoProducto',
        'NombreProducto',
        'CodigoAlmacen',
        'CantidadActual',
        'CantidadMinima',
        'CantidadMaxima',
        'Actualizado',
        'PrecioCompra',
        'PrecioCompraAnterior'];
}
