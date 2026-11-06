<?php

namespace App\Http\Controllers;

use App\Models\Recurso;
use App\Services\RecursoService;
use DomainException;
use Illuminate\Http\Request;

class RecursoController extends Controller
{
    public function __construct(private RecursoService $service) {}

    public function index()
    {
        return view('recursos.index', ['recursos' => $this->service->listar()]);
    }

    public function store(Request $request)
    {
        $this->service->registrar($request->validate([
            'codigo' => 'required|max:30|unique:recursos,codigo',
            'nombre' => 'required|max:100',
            'tipo' => 'required|in:equipo,laboratorio,material',
            'caracteristicas' => 'nullable|max:255',
            'ubicacion' => 'required|max:100',
        ]));
        return back()->with('ok', 'Recurso registrado.');
    }

    public function update(Request $request, Recurso $recurso)
    {
        $datos = $request->validate(['estado' => 'required|in:disponible,prestado,mantenimiento']);
        return $this->ejecutar(fn () => $this->service->actualizar($recurso, $datos['estado']), 'Recurso actualizado.');
    }

    public function baja(Recurso $recurso)
    {
        return $this->ejecutar(fn () => $this->service->darDeBaja($recurso), 'Recurso dado de baja.');
    }

    private function ejecutar(callable $accion, string $mensaje)
    {
        try {
            $accion();
        } catch (DomainException $e) {
            return back()->withErrors($e->getMessage());
        }
        return back()->with('ok', $mensaje);
    }
}