<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DocumentoVentaDistribucionPago extends Model
{
    protected $table = 'DocumentosVentasDistribucionPago';
    protected $primaryKey = 'Id';
    public $incrementing = true;
    public $timestamps = false;
    protected $fillable = [
        'IdDocumentoVenta','CodigoDian','Nombre','Valor','Creado','IdUsuario','NumeroCaja','Tipo',
        'LlaveDocumentosVentas','EnviadoServidor','Datafono','DatafonoOK','DatafonoNumeroTarjeta',
        'DatafonoFranquicia','DatafonoAprobacion','DatafonoRecibo','CodigoAlmacen'
    ];
}
