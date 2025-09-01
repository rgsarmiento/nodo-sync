<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DocumentoCompra extends Model
{
    protected $table = 'DocumentosCompras';
    protected $primaryKey = 'Id';
    public $timestamps = false;
    protected $fillable = ['FechaDocumento','CodigoAlmacen','llave'];

    
}
