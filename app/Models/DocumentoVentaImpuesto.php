<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DocumentoVentaImpuesto extends Model
{
    protected $table = 'DocumentosVentasImpuestos';
    protected $primaryKey = 'Id';
    public $incrementing = true;
    public $timestamps = false;
    protected $fillable = [
        'IdDocumentoVenta','IdImpuesto','Nombre','CodigoDian','Tarifa','Base','Impuesto','Volumen',
        'EsSaludable','Creado','IdUsuario','NumeroCaja','Tipo','LlaveDocumentosVentas','EnviadoServidor','CodigoAlmacen'
    ];
}
