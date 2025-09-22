<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'Productos';
    protected $primaryKey = 'Id';
    public $timestamps = false; // si tu tabla no usa created_at / updated_at

    protected $fillable = [
        'Id_TipoProducto',
        'Codigo',
        'CodigoInterno',
        'Nombre',
        'Destacado',
        'SeCompra',
        'SeVende',
        'EsCombo',
        'Id_Marca',
        'Id_Categoria',
        'Id_UnidadMedida',
        'Inventariable',
        'CantidadMinima',
        'CantidadMaxima',
        'Volumen',
        'GramosAzucar',
        'Peso',
        'EsFraccion',
        'UnidadesEmpaque',
        'Id_ClasificacionTributariaCompra',
        'Id_ImpuestoCompra',
        'ValorSaludableCompra',
        'Id_OtrosCostos',
        'ValorOtrosCostos',
        'Id_ClasificacionTributariaVenta',
        'Id_ImpuestoVenta',
        'PrecioVenta',
        'PrecioVenta2',
        'PrecioVenta3',
        'Id_ImpuestoSaludableVenta',
        'TieneSeries',
        'ObtenerPesoBascula',
        'Actualizado',
        'Creado',
        'IdProductoPadreFraccion',
        'VentaPorValor',
        'ValorOtroImpuestoCompra',
        'Id_ImpuestoOtroCompra',
        'SumarOtroImpuestoEnVenta'
    ];
}
