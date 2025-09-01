<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DocumentoCompraProducto extends Model
{
    protected $table = 'DocumentosComprasProductos';
    protected $primaryKey = 'Id';
    public $timestamps = false;
    protected $fillable = ['Codigo','CostoBase','Creado','CodigoAlmacen','LlaveDocumentosCompras'];
}
