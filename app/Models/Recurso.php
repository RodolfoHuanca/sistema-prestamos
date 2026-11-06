<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recurso extends Model
{
    protected $fillable = ['codigo', 'nombre', 'tipo', 'caracteristicas', 'ubicacion', 'estado'];

    public function puedeAsignarse(): bool
    {
        return $this->estado === 'disponible';
    }
}