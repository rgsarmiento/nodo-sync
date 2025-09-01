<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncVentasRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ventas' => ['required','array','min:1'],
            'ventas.*.llave' => ['required','string'],
            'ventas.*.FechaDocumento' => ['required','date'],
            'ventas.*.Productos' => ['required','array','min:1'],
            'ventas.*.Productos.*.IdProducto' => ['required','integer'],
            // añade más validaciones según necesites
        ];
    }
}
