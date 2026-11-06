<?php

namespace App\Services;

use App\Models\Recurso;
use DomainException;

class RecursoService
{
    public function listar()
    {
        return Recurso::orderBy('nombre')->get();
    }

    public function registrar(array $datos): Recurso
    {
        return Recurso::create($datos);
    }

    public function actualizar(Recurso $recurso, string $estado): void
    {
        if ($recurso->estado === 'baja') {
            throw new DomainException('No se puede modificar un recurso dado de baja.');
        }
        $recurso->update(['estado' => $estado]);
    }

    public function darDeBaja(Recurso $recurso): void
    {
        if ($recurso->estado === 'prestado') {
            throw new DomainException('No se puede dar de baja un recurso prestado.');
        }
        $recurso->update(['estado' => 'baja']);
    }
}