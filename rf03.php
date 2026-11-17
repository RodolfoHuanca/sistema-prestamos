<?php
declare(strict_types=1);

/**
 * RF-03 - Gestor de recursos, integrado con RF-02 (Gestión de usuarios y roles).
 * Uso con XAMPP: copia rf02.php y rf03.php en C:\xampp\htdocs\
 *   http://localhost/rf03.php            (interfaz)
 *   http://localhost/rf03.php?pruebas=1  (pruebas)
 */

define('SOLO_CLASES', true);
require_once __DIR__ . '/rf02.php'; // UsuarioService, RolService, e() (inicia la sesión)

/**
 * Componente Gestor de recursos.
 * Servicio que necesita de RF-02: UsuarioService::tienePermiso(idUsuario, 'gestionar_recursos').
 */
class GestorRecursos
{
    public const TIPOS   = ['equipo', 'laboratorio', 'material'];
    public const ESTADOS = ['disponible', 'prestado', 'de_baja'];

    private UsuarioService $usuarios;
    private ?string $ruta;
    private array $recursos = [];

    public function __construct(UsuarioService $usuarios, ?string $ruta = null)
    {
        $this->usuarios = $usuarios;
        $this->ruta = $ruta;
        if ($ruta !== null && is_file($ruta)) {
            $this->recursos = json_decode((string) file_get_contents($ruta), true) ?: [];
        }
    }

    public function tipo(string $tipo): bool
    {
        return in_array($tipo, self::TIPOS, true);
    }

    public function recurso(int $id): ?array
    {
        return $this->recursos[$id] ?? null;
    }

    public function listar(): array
    {
        return array_values($this->recursos);
    }

    /** Componente A (Gestor) → servicio (tienePermiso) → Componente B (Usuarios/Roles) → respuesta. */
    private function autorizar(int $idUsuario): ?array
    {
        if (!$this->usuarios->tienePermiso($idUsuario, 'gestionar_recursos')) {
            return $this->resp(false, 'Acción rechazada: el usuario no tiene permiso para gestionar recursos.');
        }
        return null;
    }

    public function registrar(int $idUsuario, array $d): array
    {
        if ($r = $this->autorizar($idUsuario)) {
            return $r;
        }
        $errores = $this->validar($d);
        if ($errores) {
            return $this->resp(false, 'Datos no válidos.', $errores);
        }
        $id = $this->recursos ? max(array_keys($this->recursos)) + 1 : 1;
        $this->recursos[$id] = [
            'id' => $id, 'nombre' => trim($d['nombre']), 'tipo' => $d['tipo'],
            'caracteristicas' => trim($d['caracteristicas'] ?? ''), 'estado' => 'disponible',
            'ubicacion' => trim($d['ubicacion']),
        ];
        $this->guardar();
        return $this->resp(true, 'Recurso registrado correctamente.');
    }

    public function actualizar(int $idUsuario, int $id, array $d): array
    {
        if ($r = $this->autorizar($idUsuario)) {
            return $r;
        }
        if ($this->recurso($id) === null) {
            return $this->resp(false, 'El recurso no existe.');
        }
        $errores = $this->validar($d);
        if ($errores) {
            return $this->resp(false, 'Datos no válidos.', $errores);
        }
        $estado = $d['estado'] ?? $this->recursos[$id]['estado'];
        if (!in_array($estado, self::ESTADOS, true)) {
            return $this->resp(false, 'Estado no válido.');
        }
        $this->recursos[$id] = array_merge($this->recursos[$id], [
            'nombre' => trim($d['nombre']), 'tipo' => $d['tipo'], 'estado' => $estado,
            'caracteristicas' => trim($d['caracteristicas'] ?? ''), 'ubicacion' => trim($d['ubicacion']),
        ]);
        $this->guardar();
        return $this->resp(true, 'Recurso actualizado correctamente.');
    }

    public function darDeBaja(int $idUsuario, int $id): array
    {
        if ($r = $this->autorizar($idUsuario)) {
            return $r;
        }
        if ($this->recurso($id) === null) {
            return $this->resp(false, 'El recurso no existe.');
        }
        $this->recursos[$id]['estado'] = 'de_baja';
        $this->guardar();
        return $this->resp(true, 'Recurso dado de baja correctamente.');
    }

    /** Un recurso dado de baja (o prestado) no puede asignarse a nuevas solicitudes. */
    public function puedeAsignarse(int $id): bool
    {
        return ($this->recurso($id)['estado'] ?? '') === 'disponible';
    }

    private function validar(array $d): array
    {
        $e = [];
        if (trim($d['nombre'] ?? '') === '')    { $e['nombre'] = 'El nombre es obligatorio.'; }
        if (!$this->tipo((string) ($d['tipo'] ?? ''))) { $e['tipo'] = 'Tipo no válido.'; }
        if (trim($d['ubicacion'] ?? '') === '') { $e['ubicacion'] = 'La ubicación es obligatoria.'; }
        return $e;
    }

    private function guardar(): void
    {
        if ($this->ruta !== null) {
            file_put_contents($this->ruta, json_encode($this->recursos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
    }

    private function resp(bool $ok, string $mensaje, array $errores = []): array
    {
        return ['correcto' => $ok, 'mensaje' => $mensaje, 'errores' => $errores];
    }
}

// ============================================================================
// PRUEBAS DE INTEGRACIÓN (?pruebas=1)
// ============================================================================
function pruebasRf03(): array
{
    $usuarios = new UsuarioService(new UsuarioRepositorioMemoria(), new RolService());
    $usuarios->registrarUsuario(['nombres' => 'Rosa', 'apellidos' => 'Mamani', 'correo' => 'rosa@unap.edu.pe', 'password' => 'Clave1234', 'rol' => 'personal_administrativo']); // id 1
    $usuarios->registrarUsuario(['nombres' => 'Ana', 'apellidos' => 'Quispe', 'correo' => 'ana@unap.edu.pe', 'password' => 'Clave1234', 'rol' => 'estudiante']);                // id 2
    $g = new GestorRecursos($usuarios);
    $datos = ['nombre' => 'Proyector', 'tipo' => 'equipo', 'ubicacion' => 'Almacén 1', 'caracteristicas' => 'Full HD'];
    $res = [];

    $r = $g->registrar(1, $datos);
    $res[] = ['P1 Personal administrativo registra recurso', 'Se registra el recurso', $r['mensaje'],
        $r['correcto'] && count($g->listar()) === 1];

    $r = $g->registrar(2, $datos);
    $res[] = ['P2 Estudiante intenta registrar recurso', 'Se rechaza y no se registra', $r['mensaje'],
        !$r['correcto'] && count($g->listar()) === 1];

    $r = $g->registrar(1, ['nombre' => '', 'tipo' => 'auto', 'ubicacion' => '']);
    $res[] = ['P3 Datos no válidos', 'Se rechaza con 3 errores', count($r['errores']) . ' errores',
        !$r['correcto'] && count($r['errores']) === 3];

    $r = $g->darDeBaja(2, 1);
    $res[] = ['P4 Estudiante intenta dar de baja', 'Se rechaza y el recurso sigue disponible', $r['mensaje'],
        !$r['correcto'] && $g->puedeAsignarse(1)];

    $r = $g->darDeBaja(1, 1);
    $res[] = ['P5 Personal administrativo da de baja', 'Queda de baja y no puede asignarse', $r['mensaje'],
        $r['correcto'] && !$g->puedeAsignarse(1)];

    $r = $g->registrar(99, $datos);
    $res[] = ['P6 Usuario inexistente', 'Se rechaza la operación', $r['mensaje'], !$r['correcto']];

    return $res;
}

if (isset($_GET['pruebas'])) {
    $res = pruebasRf03();
    echo '<!DOCTYPE html><meta charset="UTF-8"><title>Pruebas RF-03</title><body style="font-family:Arial;padding:20px">';
    echo '<h2>Pruebas de integración RF-03 ↔ RF-02</h2><table border="1" cellpadding="8" style="border-collapse:collapse">';
    echo '<tr><th>Prueba</th><th>Esperado</th><th>Obtenido</th><th>Estado</th></tr>';
    foreach ($res as [$n, $esp, $obt, $ok]) {
        echo '<tr><td>' . e($n) . '</td><td>' . e($esp) . '</td><td>' . e($obt) . '</td><td>' . ($ok ? '✅ Correcto' : '❌ Incorrecto') . '</td></tr>';
    }
    echo '</table><p><a href="rf03.php">Volver</a></p>';
    exit;
}

// ============================================================================
// INTERFAZ
// ============================================================================
$usuarios = new UsuarioService(new UsuarioRepositorioJson(__DIR__ . '/usuarios_rf02.json'), new RolService());
$gestor   = new GestorRecursos($usuarios, __DIR__ . '/recursos_rf03.json');

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

$resultado = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $resultado = ['correcto' => false, 'mensaje' => 'Solicitud no válida. Recarga la página.', 'errores' => []];
    } else {
        $idUsuario = (int) ($_POST['usuario'] ?? 0);
        if (($_POST['accion'] ?? '') === 'registrar') {
            $resultado = $gestor->registrar($idUsuario, $_POST);
        } elseif (($_POST['accion'] ?? '') === 'baja') {
            $resultado = $gestor->darDeBaja($idUsuario, (int) ($_POST['id'] ?? 0));
        }
    }
}
$lista = $usuarios->listarUsuarios();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Gestor de recursos</title>
<style>
  body { margin:0; padding:20px; background:#FFF8ED; color:#806653; font-family:system-ui,Arial,sans-serif; }
  .c { max-width:900px; margin:0 auto; }
  .t { background:#fff; border:1px solid #E9DDF5; border-radius:18px; padding:20px; margin:12px 0; }
  h1,h2 { color:#8064A2; margin-top:0; }
  label { display:block; font-weight:600; margin:10px 0 4px; }
  input, select { width:100%; padding:10px; border:1px solid #E9DDF5; border-radius:10px; box-sizing:border-box; }
  button { padding:10px 16px; border-radius:12px; border:1px solid #D7C4E9; background:#E9DDF5; color:#8064A2; font-weight:bold; cursor:pointer; margin-top:12px; }
  .ok { background:#E2F2E4; } .mal { background:#F8E3E5; }
  .aviso { border:1px solid #E9DDF5; border-radius:14px; padding:14px; margin:12px 0; }
  table { width:100%; border-collapse:collapse; font-size:14px; }
  th, td { text-align:left; padding:8px; border-bottom:1px solid #EDE0D1; }
</style>
</head>
<body><div class="c">
<h1>📦 Gestor de recursos</h1>
<p><a href="?pruebas=1">🧪 Ver pruebas</a> · <a href="rf02.php">👥 Usuarios y roles</a></p>

<?php if ($resultado): ?>
  <div class="aviso <?= $resultado['correcto'] ? 'ok' : 'mal' ?>">
    <?= e($resultado['mensaje']) ?>
    <?php foreach ($resultado['errores'] as $err): ?><br>• <?= e($err) ?><?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="t">
  <h2>Registrar recurso</h2>
  <?php if (!$lista): ?><p>Primero registra usuarios en <a href="rf02.php">rf02.php</a>.</p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
    <input type="hidden" name="accion" value="registrar">
    <label>Usuario que realiza la acción</label>
    <select name="usuario">
      <?php foreach ($lista as $u): ?>
        <option value="<?= e($u['id']) ?>"><?= e($u['nombres'] . ' ' . $u['apellidos'] . ' (' . $u['rol'] . ')') ?></option>
      <?php endforeach; ?>
    </select>
    <label>Nombre</label><input name="nombre">
    <label>Tipo</label>
    <select name="tipo"><?php foreach (GestorRecursos::TIPOS as $t): ?><option><?= e($t) ?></option><?php endforeach; ?></select>
    <label>Características</label><input name="caracteristicas">
    <label>Ubicación</label><input name="ubicacion">
    <button>Registrar</button>
  </form>
</div>

<div class="t">
  <h2>Recursos</h2>
  <table>
    <tr><th>ID</th><th>Nombre</th><th>Tipo</th><th>Estado</th><th>Ubicación</th><th></th></tr>
    <?php foreach ($gestor->listar() as $r): ?>
      <tr>
        <td><?= e($r['id']) ?></td><td><?= e($r['nombre']) ?></td><td><?= e($r['tipo']) ?></td>
        <td><?= e($r['estado']) ?></td><td><?= e($r['ubicacion']) ?></td>
        <td>
          <?php if ($r['estado'] !== 'de_baja'): ?>
          <form method="post" style="margin:0">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
            <input type="hidden" name="accion" value="baja">
            <input type="hidden" name="id" value="<?= e($r['id']) ?>">
            <select name="usuario" style="width:auto">
              <?php foreach ($lista as $u): ?><option value="<?= e($u['id']) ?>"><?= e($u['nombres']) ?></option><?php endforeach; ?>
            </select>
            <button style="margin:0">Dar de baja</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
</div></body></html>
