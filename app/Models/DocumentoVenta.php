<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DocumentoVenta extends Model
{
    protected $table = 'DocumentosVentas';
    protected $primaryKey = 'Id';
    public $incrementing = true;
    public $timestamps = false; // usas campos Creado/Actualizado manualmente
    protected $fillable = [
        'Prefijo', 'Consecutivo', 'IdSocioComercial', 'FechaDocumento', 'FechaVencimiento',
        'Id_FormaPago', 'TotalLineasDetalle', 'TotalExcluyendoImpuestos', 'TotalIncluyendoImpuestos',
        'TotalDescuentos', 'TotalImpuestos', 'TotalPago', 'AjusteCentavo', 'EfectivoRecibido',
        'CambioEntregado', 'IdDocumentoVentaReferencia', 'NumeroDocumentoReferencia',
        'IdConceptoNotaCredito', 'IdConceptoNotaDebito', 'Observacion', 'EsElectronica',
        'EstadoElectronica', 'ClaveDocumentoXML', 'FechaDian', 'Reintentos', 'CajaNumero',
        'CajaNombre', 'CajaUbicacion', 'CajaTipo', 'IdUsuario', 'Tipo', 'Creado', 'Actualizado',
        'IdRuta', 'IdVendedor', 'IdSocioComercialSucursal', 'SocioComercial', 'llave',
        'EnviadoServidor', 'IdPuestoConsumo', 'LlaveDocumento', 'CodigoAlmacen'
    ];

    public function productos()
    {
        return $this->hasMany(DocumentoVentaProducto::class, 'LlaveDocumentosVentas', 'llave');
    }

    public function impuestos()
    {
        return $this->hasMany(DocumentoVentaImpuesto::class, 'LlaveDocumentosVentas', 'llave');
    }

    public function pagos()
    {
        return $this->hasMany(DocumentoVentaDistribucionPago::class, 'LlaveDocumentosVentas', 'llave');
    }
}
