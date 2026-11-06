<?php
declare(strict_types=1);

/**
 * RF-02 - Gestión de usuarios y roles (archivo único).
 * Sistema de Gestión de Préstamos de Equipos, Laboratorios y Material Educativo.
 *
 * Cómo usarlo con XAMPP:
 *   1. Copia este archivo a C:\xampp\htdocs\rf02.php  (nómbralo rf02.php)
 *   2. Abre XAMPP y presiona "Start" en Apache.
 *   3. En el navegador entra a http://localhost/rf02.php          (interfaz)
 *                              http://localhost/rf02.php?pruebas=1 (pruebas)
 */

session_start();

function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

// ============================================================================
// CLASES DEL COMPONENTE
// ============================================================================

/**
 * Rol del sistema. Define qué puede hacer cada tipo de usuario.
 */
class Rol
{
    private string $codigo;
    private string $nombre;
    /** @var string[] */
    private array $permisos;

    public function __construct(string $codigo, string $nombre, array $permisos)
    {
        $this->codigo = $codigo;
        $this->nombre = $nombre;
        $this->permisos = $permisos;
    }

    public function getCodigo(): string   { return $this->codigo; }
    public function getNombre(): string   { return $this->nombre; }
    public function getPermisos(): array  { return $this->permisos; }

    public function tienePermiso(string $permiso): bool
    {
        return in_array($permiso, $this->permisos, true);
    }
}

/**
 * Servicio que Gestión de Usuarios necesita del componente Gestión de Roles.
 * UsuarioService depende de esta interfaz y no de la implementación (bajo acoplamiento).
 */
interface RolProveedor
{
    public function consultarRol(string $codigo): ?Rol;

    /** @return Rol[] */
    public function listarRoles(): array;
}

/**
 * Componente Gestión de Roles (versión básica con roles fijos).
 */
class RolService implements RolProveedor
{
    /** @var array<string, Rol> */
    private array $roles = [];

    public function __construct()
    {
        $lista = [
            new Rol('administrador', 'Administrador', [
                'gestionar_usuarios', 'gestionar_recursos', 'ver_reportes', 'solicitar_prestamo',
            ]),
            new Rol('personal_administrativo', 'Personal administrativo', [
                'gestionar_recursos', 'registrar_prestamo', 'registrar_devolucion',
            ]),
            new Rol('docente', 'Docente', ['solicitar_prestamo', 'consultar_disponibilidad']),
            new Rol('estudiante', 'Estudiante', ['solicitar_prestamo', 'consultar_disponibilidad']),
        ];
        foreach ($lista as $rol) {
            $this->roles[$rol->getCodigo()] = $rol;
        }
    }

    public function consultarRol(string $codigo): ?Rol
    {
        return $this->roles[$codigo] ?? null;
    }

    public function listarRoles(): array
    {
        return array_values($this->roles);
    }
}

/**
 * Entidad Usuario. Nunca guarda la contraseña en texto plano: solo su hash.
 */
class Usuario
{
    private ?int $id;
    private string $nombres;
    private string $apellidos;
    private string $correo;
    private string $passwordHash;
    private string $rol;

    public function __construct(
        ?int $id,
        string $nombres,
        string $apellidos,
        string $correo,
        string $passwordHash,
        string $rol
    ) {
        $this->id = $id;
        $this->nombres = $nombres;
        $this->apellidos = $apellidos;
        $this->correo = $correo;
        $this->passwordHash = $passwordHash;
        $this->rol = $rol;
    }

    public function getId(): ?int            { return $this->id; }
    public function getNombres(): string     { return $this->nombres; }
    public function getApellidos(): string   { return $this->apellidos; }
    public function getCorreo(): string      { return $this->correo; }
    public function getPasswordHash(): string { return $this->passwordHash; }
    public function getRol(): string         { return $this->rol; }

    public function setId(int $id): void                     { $this->id = $id; }
    public function setPasswordHash(string $hash): void      { $this->passwordHash = $hash; }
    public function setRol(string $rol): void                { $this->rol = $rol; }

    public function actualizarDatos(string $nombres, string $apellidos, string $correo): void
    {
        $this->nombres = $nombres;
        $this->apellidos = $apellidos;
        $this->correo = $correo;
    }

    /** Representación completa (para guardar). */
    public function aArray(): array
    {
        return [
            'id'            => $this->id,
            'nombres'       => $this->nombres,
            'apellidos'     => $this->apellidos,
            'correo'        => $this->correo,
            'password_hash' => $this->passwordHash,
            'rol'           => $this->rol,
        ];
    }

    /** Representación pública: NO incluye la contraseña ni su hash. */
    public function aPublico(): array
    {
        $datos = $this->aArray();
        unset($datos['password_hash']);
        return $datos;
    }

    public static function desdeArray(array $d): Usuario
    {
        return new Usuario(
            isset($d['id']) ? (int) $d['id'] : null,
            (string) $d['nombres'],
            (string) $d['apellidos'],
            (string) $d['correo'],
            (string) $d['password_hash'],
            (string) $d['rol']
        );
    }
}

/**
 * Servicio de persistencia que necesita Gestión de Usuarios.
 * Se puede cambiar JSON por MySQL/Laravel sin tocar UsuarioService.
 */
interface UsuarioRepositorio
{
    /** @return Usuario[] */
    public function todos(): array;

    public function buscarPorId(int $id): ?Usuario;

    public function buscarPorCorreo(string $correo): ?Usuario;

    /** Inserta (si no tiene id) o actualiza. Devuelve el usuario con su id. */
    public function guardar(Usuario $usuario): Usuario;
}

/**
 * Repositorio en memoria: se usa en las pruebas para no tocar el archivo real.
 */
class UsuarioRepositorioMemoria implements UsuarioRepositorio
{
    /** @var array<int, Usuario> */
    private array $usuarios = [];
    private int $siguienteId = 1;

    public function todos(): array
    {
        return array_values($this->usuarios);
    }

    public function buscarPorId(int $id): ?Usuario
    {
        return $this->usuarios[$id] ?? null;
    }

    public function buscarPorCorreo(string $correo): ?Usuario
    {
        foreach ($this->usuarios as $u) {
            if (strtolower($u->getCorreo()) === strtolower($correo)) {
                return $u;
            }
        }
        return null;
    }

    public function guardar(Usuario $usuario): Usuario
    {
        if ($usuario->getId() === null) {
            $usuario->setId($this->siguienteId++);
        }
        $this->usuarios[$usuario->getId()] = $usuario;
        return $usuario;
    }
}

/**
 * Repositorio que guarda los usuarios en un archivo JSON (no requiere base de datos).
 */
class UsuarioRepositorioJson implements UsuarioRepositorio
{
    private string $ruta;

    public function __construct(string $ruta)
    {
        $this->ruta = $ruta;
        $carpeta = dirname($ruta);
        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0775, true);
        }
    }

    public function todos(): array
    {
        return array_map([Usuario::class, 'desdeArray'], $this->leer());
    }

    public function buscarPorId(int $id): ?Usuario
    {
        foreach ($this->leer() as $fila) {
            if ((int) $fila['id'] === $id) {
                return Usuario::desdeArray($fila);
            }
        }
        return null;
    }

    public function buscarPorCorreo(string $correo): ?Usuario
    {
        foreach ($this->leer() as $fila) {
            if (strtolower($fila['correo']) === strtolower($correo)) {
                return Usuario::desdeArray($fila);
            }
        }
        return null;
    }

    public function guardar(Usuario $usuario): Usuario
    {
        $filas = $this->leer();

        if ($usuario->getId() === null) {
            $max = 0;
            foreach ($filas as $fila) {
                $max = max($max, (int) $fila['id']);
            }
            $usuario->setId($max + 1);
            $filas[] = $usuario->aArray();
        } else {
            $encontrado = false;
            foreach ($filas as $i => $fila) {
                if ((int) $fila['id'] === $usuario->getId()) {
                    $filas[$i] = $usuario->aArray();
                    $encontrado = true;
                    break;
                }
            }
            if (!$encontrado) {
                $filas[] = $usuario->aArray();
            }
        }

        $this->escribir($filas);
        return $usuario;
    }

    private function leer(): array
    {
        if (!is_file($this->ruta)) {
            return [];
        }
        $contenido = file_get_contents($this->ruta);
        $datos = json_decode((string) $contenido, true);
        return is_array($datos) ? $datos : [];
    }

    private function escribir(array $filas): void
    {
        $json = json_encode(array_values($filas), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($this->ruta, $json, LOCK_EX);
    }
}

/**
 * Componente Gestión de Usuarios (RF-02).
 *
 * Responsabilidad: registrar, consultar, modificar usuarios y asignarles un rol.
 * No conoce cómo se guardan los datos ni cómo se definen los roles:
 * usa solo las interfaces UsuarioRepositorio y RolProveedor (bajo acoplamiento).
 */
class UsuarioService
{
    private UsuarioRepositorio $repositorio;
    private RolProveedor $roles;

    public function __construct(UsuarioRepositorio $repositorio, RolProveedor $roles)
    {
        $this->repositorio = $repositorio;
        $this->roles = $roles;
    }

    /**
     * Valida los datos de un usuario.
     * Si $idActual es null se trata de un registro (todos los campos son obligatorios).
     * Si $idActual tiene valor se trata de una edición (la contraseña es opcional).
     *
     * @return array{correcto: bool, mensaje: string, errores: array<string,string>}
     */
    public function validarUsuario(array $datos, ?int $idActual = null): array
    {
        $errores = [];
        $esEdicion = $idActual !== null;

        $nombres   = trim((string) ($datos['nombres'] ?? ''));
        $apellidos = trim((string) ($datos['apellidos'] ?? ''));
        $correo    = strtolower(trim((string) ($datos['correo'] ?? '')));
        $password  = (string) ($datos['password'] ?? '');
        $rol       = trim((string) ($datos['rol'] ?? ''));

        // Nombres y apellidos
        foreach (['nombres' => [$nombres, 'Los nombres'], 'apellidos' => [$apellidos, 'Los apellidos']] as $campo => $info) {
            [$valor, $etiqueta] = $info;
            if ($valor === '') {
                $errores[$campo] = "$etiqueta son obligatorios.";
            } elseif (strlen($valor) > 100) {
                $errores[$campo] = "$etiqueta no deben superar los 100 caracteres.";
            } elseif (!preg_match('/^[\p{L}][\p{L}\s\'.-]*$/u', $valor)) {
                $errores[$campo] = "$etiqueta solo pueden contener letras, espacios, apóstrofes o guiones.";
            }
        }

        // Correo
        if ($correo === '') {
            $errores['correo'] = 'El correo electrónico es obligatorio.';
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strpos((string) substr(strrchr($correo, '@'), 1), '.') === false) {
            $errores['correo'] = 'El correo electrónico no es válido.';
        } else {
            $existente = $this->repositorio->buscarPorCorreo($correo);
            if ($existente !== null && $existente->getId() !== $idActual) {
                $errores['correo'] = 'Ya existe un usuario registrado con ese correo.';
            }
        }

        // Contraseña (en edición solo se valida si se escribe una nueva)
        if (!$esEdicion || $password !== '') {
            if ($password === '') {
                $errores['password'] = 'La contraseña es obligatoria.';
            } elseif (strlen($password) < 8) {
                $errores['password'] = 'La contraseña debe tener al menos 8 caracteres.';
            } elseif (strlen($password) > 72) {
                $errores['password'] = 'La contraseña no debe superar los 72 caracteres.';
            }
        }

        // Rol
        if ($rol === '') {
            $errores['rol'] = 'Debes seleccionar un rol.';
        } elseif ($this->roles->consultarRol($rol) === null) {
            $errores['rol'] = 'El rol seleccionado no existe.';
        }

        if ($errores === []) {
            return ['correcto' => true, 'mensaje' => 'Los datos del usuario son válidos.', 'errores' => []];
        }

        $mensaje = count($errores) === 1
            ? 'Se encontró 1 error de validación.'
            : 'Se encontraron ' . count($errores) . ' errores de validación.';

        return ['correcto' => false, 'mensaje' => $mensaje, 'errores' => $errores];
    }

    /**
     * Registra un usuario nuevo. La contraseña se guarda con hash (nunca en texto plano).
     */
    public function registrarUsuario(array $datos): array
    {
        $validacion = $this->validarUsuario($datos);
        if (!$validacion['correcto']) {
            return $validacion;
        }

        $usuario = new Usuario(
            null,
            trim($datos['nombres']),
            trim($datos['apellidos']),
            strtolower(trim($datos['correo'])),
            password_hash((string) $datos['password'], PASSWORD_DEFAULT),
            trim($datos['rol'])
        );
        $usuario = $this->repositorio->guardar($usuario);

        return [
            'correcto' => true,
            'mensaje'  => 'Usuario registrado correctamente.',
            'errores'  => [],
            'usuario'  => $usuario->aPublico(),
        ];
    }

    /**
     * Modifica los datos de un usuario existente. Si no se envía contraseña, se conserva la actual.
     */
    public function editarUsuario(int $id, array $datos): array
    {
        $usuario = $this->repositorio->buscarPorId($id);
        if ($usuario === null) {
            return $this->error('El usuario no existe.');
        }

        $validacion = $this->validarUsuario($datos, $id);
        if (!$validacion['correcto']) {
            return $validacion;
        }

        $usuario->actualizarDatos(
            trim($datos['nombres']),
            trim($datos['apellidos']),
            strtolower(trim($datos['correo']))
        );
        $usuario->setRol(trim($datos['rol']));

        $password = (string) ($datos['password'] ?? '');
        if ($password !== '') {
            $usuario->setPasswordHash(password_hash($password, PASSWORD_DEFAULT));
        }

        $this->repositorio->guardar($usuario);

        return [
            'correcto' => true,
            'mensaje'  => 'Usuario actualizado correctamente.',
            'errores'  => [],
            'usuario'  => $usuario->aPublico(),
        ];
    }

    /**
     * Asigna (o cambia) el rol de un usuario existente.
     */
    public function asignarRol(int $id, string $codigoRol): array
    {
        $usuario = $this->repositorio->buscarPorId($id);
        if ($usuario === null) {
            return $this->error('El usuario no existe.');
        }
        if ($this->roles->consultarRol($codigoRol) === null) {
            return $this->error('El rol seleccionado no existe.', ['rol' => 'El rol seleccionado no existe.']);
        }

        $usuario->setRol($codigoRol);
        $this->repositorio->guardar($usuario);

        return [
            'correcto' => true,
            'mensaje'  => 'Rol asignado correctamente.',
            'errores'  => [],
            'usuario'  => $usuario->aPublico(),
        ];
    }

    /** @return array[] Usuarios sin contraseña ni hash. */
    public function listarUsuarios(): array
    {
        return array_map(
            static function (Usuario $u): array { return $u->aPublico(); },
            $this->repositorio->todos()
        );
    }

    public function obtenerUsuario(int $id): ?array
    {
        $usuario = $this->repositorio->buscarPorId($id);
        return $usuario === null ? null : $usuario->aPublico();
    }

    /** Indica si el usuario tiene un permiso según su rol. */
    public function tienePermiso(int $id, string $permiso): bool
    {
        $usuario = $this->repositorio->buscarPorId($id);
        if ($usuario === null) {
            return false;
        }
        $rol = $this->roles->consultarRol($usuario->getRol());
        return $rol !== null && $rol->tienePermiso($permiso);
    }

    private function error(string $mensaje, array $errores = []): array
    {
        return ['correcto' => false, 'mensaje' => $mensaje, 'errores' => $errores];
    }
}

// ============================================================================
// PRUEBAS
// ============================================================================

/**
 * Ejecuta las pruebas con un repositorio en memoria (no toca el archivo real).
 * @return array<int, array{0:string,1:string,2:string,3:bool}>
 */
function ejecutarPruebas(): array
{
    $servicio = new UsuarioService(new UsuarioRepositorioMemoria(), new RolService());
    $resultados = [];
    $probar = function (string $nombre, string $esperado, string $obtenido, bool $cumple) use (&$resultados): void {
        $resultados[] = [$nombre, $esperado, $obtenido, $cumple];
    };

    // ---- Prueba 1: datos correctos --------------------------------------------
    $r = $servicio->registrarUsuario([
        'nombres' => 'Ana', 'apellidos' => 'Quispe Mamani', 'correo' => 'ana@unap.edu.pe',
        'password' => 'Clave1234', 'rol' => 'estudiante',
    ]);
    $probar(
        'P1 Datos correctos',
        'Registra el usuario con rol estudiante',
        $r['mensaje'] . ' (rol: ' . ($r['usuario']['rol'] ?? '-') . ')',
        $r['correcto'] === true && ($r['usuario']['rol'] ?? '') === 'estudiante'
    );

    // ---- Prueba 2: correo inválido y contraseña corta -------------------------
    $antes = count($servicio->listarUsuarios());
    $r = $servicio->registrarUsuario([
        'nombres' => 'Ana', 'apellidos' => 'Quispe', 'correo' => 'ana@unap',
        'password' => '123', 'rol' => 'estudiante',
    ]);
    $despues = count($servicio->listarUsuarios());
    $probar(
        'P2 Correo inválido y contraseña corta',
        'Rechaza con 2 errores y no registra',
        implode(' | ', $r['errores']),
        $r['correcto'] === false && isset($r['errores']['correo'], $r['errores']['password']) && $antes === $despues
    );

    // ---- Pruebas adicionales ---------------------------------------------------
    $r = $servicio->registrarUsuario([
        'nombres' => 'Otra', 'apellidos' => 'Persona', 'correo' => 'ANA@unap.edu.pe',
        'password' => 'Clave1234', 'rol' => 'docente',
    ]);
    $probar('P3 Correo duplicado', 'Rechaza el correo repetido', $r['errores']['correo'] ?? '-',
        $r['correcto'] === false && isset($r['errores']['correo']));

    $r = $servicio->registrarUsuario([]);
    $probar('P4 Campos vacíos', 'Rechaza con 5 errores', count($r['errores']) . ' errores',
        $r['correcto'] === false && count($r['errores']) === 5);

    $r = $servicio->registrarUsuario([
        'nombres' => 'Luis', 'apellidos' => 'Condori', 'correo' => 'luis@unap.edu.pe',
        'password' => 'Clave1234', 'rol' => 'superusuario',
    ]);
    $probar('P5 Rol inexistente', 'Rechaza el rol', $r['errores']['rol'] ?? '-',
        $r['correcto'] === false && isset($r['errores']['rol']));

    $r = $servicio->asignarRol(1, 'docente');
    $probar('P6 Asignar rol', 'El usuario 1 pasa a docente', $r['mensaje'] . ' (' . ($r['usuario']['rol'] ?? '-') . ')',
        $r['correcto'] === true && $r['usuario']['rol'] === 'docente');

    $r = $servicio->asignarRol(99, 'docente');
    $probar('P7 Asignar rol a usuario inexistente', 'Rechaza la operación', $r['mensaje'],
        $r['correcto'] === false);

    $r = $servicio->editarUsuario(1, [
        'nombres' => 'Ana María', 'apellidos' => 'Quispe Mamani', 'correo' => 'ana@unap.edu.pe',
        'password' => '', 'rol' => 'docente',
    ]);
    $probar('P8 Editar sin cambiar contraseña', 'Actualiza los datos', $r['mensaje'],
        $r['correcto'] === true && $r['usuario']['nombres'] === 'Ana María');

    $publico = $servicio->listarUsuarios()[0];
    $probar('P9 No se expone la contraseña', 'El listado no incluye password ni hash',
        implode(', ', array_keys($publico)), !isset($publico['password']) && !isset($publico['password_hash']));

    $u = new Usuario(null, 'X', 'Y', 'x@y.com', password_hash('Clave1234', PASSWORD_DEFAULT), 'estudiante');
    $probar('P10 Contraseña con hash', 'El hash no es el texto original y se verifica',
        substr($u->getPasswordHash(), 0, 7) . '...',
        $u->getPasswordHash() !== 'Clave1234' && password_verify('Clave1234', $u->getPasswordHash()));

    $probar('P11 Permisos por rol', 'Docente puede solicitar préstamo y no gestionar usuarios',
        'solicitar=' . var_export($servicio->tienePermiso(1, 'solicitar_prestamo'), true)
        . ', gestionar=' . var_export($servicio->tienePermiso(1, 'gestionar_usuarios'), true),
        $servicio->tienePermiso(1, 'solicitar_prestamo') && !$servicio->tienePermiso(1, 'gestionar_usuarios'));

    return $resultados;
}

// ============================================================================
// PÁGINA DE PRUEBAS (?pruebas=1)
// ============================================================================

if (isset($_GET['pruebas'])):
    $res = ejecutarPruebas();
    $total = count($res);
    $cumplen = count(array_filter($res, function ($r) { return $r[3]; }));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pruebas RF-02</title>
<style>
  body { margin:0; padding:20px; background:#FFF8ED; color:#806653; font-family:system-ui,Segoe UI,Arial,sans-serif; }
  .contenedor { max-width:1000px; margin:0 auto; }
  h1 { color:#8064A2; font-size:24px; }
  .resumen { background:#E9DDF5; border-radius:14px; padding:14px 18px; margin:12px 0; font-weight:bold; }
  table { width:100%; border-collapse:collapse; background:#fff; border-radius:14px; overflow:hidden; }
  th, td { text-align:left; padding:10px; border-bottom:1px solid #EDE0D1; vertical-align:top; font-size:14px; }
  th { background:#DCEFFA; color:#5B91B8; }
  .ok { background:#E2F2E4; font-weight:bold; text-align:center; }
  .mal { background:#F8E3E5; font-weight:bold; text-align:center; }
  .boton { display:inline-block; padding:10px 16px; border-radius:12px; border:1px solid #D7C4E9; background:#E9DDF5;
           color:#8064A2; font-weight:bold; text-decoration:none; }
  .tabla-scroll { overflow-x:auto; }
</style>
</head>
<body>
<div class="contenedor">
  <h1>🧪 Pruebas RF-02 · Gestión de usuarios y roles</h1>
  <div class="resumen"><?= $cumplen ?> de <?= $total ?> pruebas cumplen</div>
  <div class="tabla-scroll">
  <table>
    <tr><th>Caso</th><th>Resultado esperado</th><th>Resultado obtenido</th><th>Estado</th></tr>
    <?php foreach ($res as [$nombre, $esperado, $obtenido, $cumple]): ?>
      <tr>
        <td><?= e($nombre) ?></td>
        <td><?= e($esperado) ?></td>
        <td><?= e($obtenido) ?></td>
        <td class="<?= $cumple ? 'ok' : 'mal' ?>"><?= $cumple ? 'Cumple' : 'No cumple' ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <p><a class="boton" href="?">← Volver a la interfaz</a></p>
</div>
</body>
</html>
<?php
    exit;
endif;

// ============================================================================
// INTERFAZ WEB
// ============================================================================


$roles    = new RolService();
$servicio = new UsuarioService(new UsuarioRepositorioJson(__DIR__ . '/usuarios_rf02.json'), $roles);

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

$resultado  = null;
$datos      = ['nombres' => '', 'apellidos' => '', 'correo' => '', 'password' => '', 'rol' => ''];
$editandoId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $resultado = ['correcto' => false, 'mensaje' => 'Solicitud no válida. Recarga la página.', 'errores' => []];
    } else {
        $accion = (string) ($_POST['accion'] ?? '');
        $id     = (int) ($_POST['id'] ?? 0);
        foreach ($datos as $campo => $_) {
            $datos[$campo] = (string) ($_POST[$campo] ?? '');
        }

        if ($accion === 'registrar') {
            $resultado = $servicio->registrarUsuario($datos);
        } elseif ($accion === 'editar') {
            $resultado = $servicio->editarUsuario($id, $datos);
            $editandoId = $id;
        } elseif ($accion === 'asignar_rol') {
            $resultado = $servicio->asignarRol($id, (string) ($_POST['rol'] ?? ''));
            $datos = ['nombres' => '', 'apellidos' => '', 'correo' => '', 'password' => '', 'rol' => ''];
        }

        if ($resultado !== null && $resultado['correcto']) {
            $datos = ['nombres' => '', 'apellidos' => '', 'correo' => '', 'password' => '', 'rol' => ''];
            $editandoId = null;
        }
        $datos['password'] = ''; // nunca se vuelve a mostrar la contraseña
    }
} elseif (isset($_GET['editar'])) {
    $usuario = $servicio->obtenerUsuario((int) $_GET['editar']);
    if ($usuario !== null) {
        $editandoId = $usuario['id'];
        $datos = [
            'nombres' => $usuario['nombres'], 'apellidos' => $usuario['apellidos'],
            'correo' => $usuario['correo'], 'password' => '', 'rol' => $usuario['rol'],
        ];
    }
}

$errores  = $resultado['errores'] ?? [];
$usuarios = $servicio->listarUsuarios();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Gestión de usuarios y roles</title>
<style>
  :root { --crema:#FFF8ED; --lila:#E9DDF5; --lila-osc:#8064A2; --celeste:#DCEFFA; --celeste-osc:#5B91B8;
          --cafe:#806653; --cafe-claro:#EDE0D1; --verde:#E2F2E4; --rojo:#F8E3E5; }
  * { box-sizing:border-box; }
  body { margin:0; padding:20px; background:var(--crema); color:var(--cafe); font-family:system-ui,Segoe UI,Arial,sans-serif; }
  .contenedor { max-width:980px; margin:0 auto; }
  .encabezado { background:linear-gradient(135deg,var(--lila),var(--celeste)); border:1px solid #D8CBE8;
                border-radius:22px; padding:26px 20px; text-align:center; margin-bottom:16px; }
  .encabezado h1 { margin:6px 0; color:var(--lila-osc); font-size:26px; }
  .tarjeta { background:#fff; border:1px solid var(--lila); border-radius:18px; padding:20px; margin:12px 0;
             box-shadow:0 4px 12px rgba(128,100,162,.08); }
  .tarjeta h2 { margin-top:0; color:var(--lila-osc); font-size:18px; }
  label { display:block; font-weight:600; margin:10px 0 4px; }
  input, select { width:100%; padding:10px; border:1px solid var(--lila); border-radius:10px; color:var(--cafe); background:#fff; font-size:15px; }
  .fila { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  @media (max-width:640px){ .fila { grid-template-columns:1fr; } }
  button, .boton { display:inline-block; padding:10px 16px; border-radius:12px; border:1px solid #D7C4E9; background:var(--lila);
                   color:var(--lila-osc); font-weight:bold; cursor:pointer; text-decoration:none; font-size:14px; }
  button:hover, .boton:hover { background:var(--celeste); }
  .campo-error { color:#B04A55; font-size:13px; margin-top:4px; }
  .aviso { border:1px solid var(--lila); border-radius:14px; padding:14px 16px; margin:12px 0; }
  .aviso.ok { background:var(--verde); } .aviso.mal { background:var(--rojo); }
  table { width:100%; border-collapse:collapse; font-size:14px; }
  th, td { text-align:left; padding:10px 8px; border-bottom:1px solid var(--cafe-claro); vertical-align:middle; }
  th { color:var(--lila-osc); }
  .tabla-scroll { overflow-x:auto; }
  .inline { display:flex; gap:6px; align-items:center; }
  .inline select { width:auto; padding:6px; }
  small { color:#a08a78; }
</style>
</head>
<body>
<div class="contenedor">

  <div class="encabezado">
    <div style="font-size:34px">🎓 👥</div>
    <h1>Gestión de usuarios y roles</h1>
    <div>Sistema de Gestión de Préstamos de Equipos, Laboratorios y Material Educativo</div>
    <small>RF-02 · Universidad Nacional del Altiplano de Puno</small><br><br><a class="boton" href="?pruebas=1">🧪 Ver pruebas</a>
  </div>

  <?php if ($resultado !== null): ?>
    <div class="aviso <?= $resultado['correcto'] ? 'ok' : 'mal' ?>">
      <b><?= $resultado['correcto'] ? '✓' : '!' ?> <?= e($resultado['mensaje']) ?></b>
    </div>
  <?php endif; ?>

  <div class="tarjeta">
    <h2><?= $editandoId !== null ? '✏️ Editar usuario' : '➕ Registrar usuario' ?></h2>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
      <input type="hidden" name="accion" value="<?= $editandoId !== null ? 'editar' : 'registrar' ?>">
      <?php if ($editandoId !== null): ?><input type="hidden" name="id" value="<?= e($editandoId) ?>"><?php endif; ?>

      <div class="fila">
        <div>
          <label for="nombres">Nombres</label>
          <input id="nombres" name="nombres" value="<?= e($datos['nombres']) ?>" maxlength="100">
          <?php if (isset($errores['nombres'])): ?><div class="campo-error"><?= e($errores['nombres']) ?></div><?php endif; ?>
        </div>
        <div>
          <label for="apellidos">Apellidos</label>
          <input id="apellidos" name="apellidos" value="<?= e($datos['apellidos']) ?>" maxlength="100">
          <?php if (isset($errores['apellidos'])): ?><div class="campo-error"><?= e($errores['apellidos']) ?></div><?php endif; ?>
        </div>
      </div>

      <label for="correo">Correo electrónico</label>
      <input id="correo" name="correo" type="text" value="<?= e($datos['correo']) ?>" placeholder="usuario@unap.edu.pe">
      <?php if (isset($errores['correo'])): ?><div class="campo-error"><?= e($errores['correo']) ?></div><?php endif; ?>

      <div class="fila">
        <div>
          <label for="password">Contraseña<?= $editandoId !== null ? ' (déjala vacía para conservar la actual)' : '' ?></label>
          <input id="password" name="password" type="password" placeholder="Mínimo 8 caracteres">
          <?php if (isset($errores['password'])): ?><div class="campo-error"><?= e($errores['password']) ?></div><?php endif; ?>
        </div>
        <div>
          <label for="rol">Rol</label>
          <select id="rol" name="rol">
            <option value="">Selecciona un rol</option>
            <?php foreach ($roles->listarRoles() as $rol): ?>
              <option value="<?= e($rol->getCodigo()) ?>" <?= $datos['rol'] === $rol->getCodigo() ? 'selected' : '' ?>>
                <?= e($rol->getNombre()) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errores['rol'])): ?><div class="campo-error"><?= e($errores['rol']) ?></div><?php endif; ?>
        </div>
      </div>

      <p>
        <button type="submit"><?= $editandoId !== null ? 'Guardar cambios' : 'Registrar usuario' ?></button>
        <?php if ($editandoId !== null): ?><a class="boton" href="?">Cancelar</a><?php endif; ?>
      </p>
    </form>
  </div>

  <div class="tarjeta">
    <h2>📋 Usuarios registrados (<?= count($usuarios) ?>)</h2>
    <?php if (!$usuarios): ?>
      <p>Aún no hay usuarios registrados.</p>
    <?php else: ?>
      <div class="tabla-scroll">
      <table>
        <tr><th>#</th><th>Nombre</th><th>Correo</th><th>Rol</th><th></th></tr>
        <?php foreach ($usuarios as $u): ?>
          <tr>
            <td><?= e($u['id']) ?></td>
            <td><?= e($u['nombres'] . ' ' . $u['apellidos']) ?></td>
            <td><?= e($u['correo']) ?></td>
            <td>
              <form method="post" class="inline">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                <input type="hidden" name="accion" value="asignar_rol">
                <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                <select name="rol">
                  <?php foreach ($roles->listarRoles() as $rol): ?>
                    <option value="<?= e($rol->getCodigo()) ?>" <?= $u['rol'] === $rol->getCodigo() ? 'selected' : '' ?>>
                      <?= e($rol->getNombre()) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <button type="submit">Asignar</button>
              </form>
            </td>
            <td><a class="boton" href="?editar=<?= e($u['id']) ?>">Editar</a></td>
          </tr>
        <?php endforeach; ?>
      </table>
      </div>
    <?php endif; ?>
  </div>

  <div style="text-align:center;padding:16px;border-top:1px solid var(--lila);margin-top:16px">
    <b>Proyecto de Ingeniería de Software</b><br><small>Gestión de usuarios y roles · Prototipo académico</small>
  </div>
</div>
</body>
</html>
