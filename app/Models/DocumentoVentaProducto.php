<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DocumentoVentaProducto extends Model
{
    protected $table = 'DocumentosVentasProductos';
    protected $primaryKey = 'Id';
    public $incrementing = true;
    public $timestamps = false;
    protected $fillable = [
        'IdDocumentoVenta','IdProducto','Orden','Codigo','Nombre','Cantidad','CostoBase','PrecioBase','Volumen',
        'IdImpuesto','CodigoImpuestoDian','NombreImpuesto','TarifaImpuesto','PrefijoImpuesto','ValorImpuesto',
        'ValorTotalImpuesto','IdImpuestoSaludable','CodigoImpuestoSaludableDian','NombreImpuestoSaludable',
        'TarifaImpuestoSaludable','PrefijoImpuestoSaludable','ValorImpuestoSaludable','ValorTotalImpuestoSaludable',
        'ValorTotalImpuestos','PorcentajeDescuentoGeneral','DescripcionDescuentoGeneral','TotalDescuentoGeneral',
        'PorcentajeDescuentoProgramado','DescripcionDescuentoProgramado','TotalDescuentoProgramado','TotalDescuentos',
        'ValorTotalUnidad','ValorTotalVenta','Observacion','Creado','IdUsuario','NumeroCaja','Tipo','IdUnidadMedida',
        'LlaveDocumentosVentas','EnviadoServidor','IdOtrosImpuestos','CodigoOtrosImpuestosDian','NombreOtrosImpuestos',
        'ValorOtrosImpuestos','ValorTotalOtrosImpuestos','LlaveEvento','CodigoAlmacen'
    ];


    public function setNombreImpuestoSaludableAttribute($value)
    {
        $this->attributes['NombreImpuestoSaludable'] = $value ?: '';
    }


}
