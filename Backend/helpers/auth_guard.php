<?php
declare(strict_types=1);

require_once __DIR__ . '/response.php';

const ROL_ESTUDIANTE = 1;
const ROL_TUTOR      = 2;
const ROL_ADMIN      = 3;

/**
 * Inicia la sesión PHP con cookies seguras para localhost/producción.
 */
function iniciarSesionSegura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Traduce el id_rol numérico al slug usado en el Frontend.
 */
function rolSlug(int $idRol): string
{
    return match ($idRol) {
        ROL_ESTUDIANTE => 'estudiante',
        ROL_TUTOR      => 'tutor',
        ROL_ADMIN      => 'admin',
        default        => 'invitado',
    };
}

/**
 * Obtiene al usuario autenticado desde BD para validar estado activo y rol actual.
 */
function obtenerUsuarioSesion(PDO $pdo): ?array
{
    iniciarSesionSegura();

    $idUsuario = $_SESSION['id_usuario'] ?? null;
    if (!$idUsuario) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT u.id_usuario, u.nombre, u.matricula, u.id_carrera, u.correo, u.id_rol, u.activo,
                r.nombre AS rol_nombre,
                c.clave AS carrera_clave, c.nombre AS carrera_nombre
         FROM Usuarios u
         INNER JOIN Roles r ON r.id_rol = u.id_rol
         LEFT JOIN Carreras c ON c.id_carrera = u.id_carrera
         WHERE u.id_usuario = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => (int) $idUsuario]);
    $usuario = $stmt->fetch();

    if (!$usuario || !(bool) $usuario['activo']) {
        cerrarSesionActual();
        return null;
    }

    $_SESSION['id_rol'] = (int) $usuario['id_rol'];

    return [
        'id_usuario'   => (int) $usuario['id_usuario'],
        'nombre'       => $usuario['nombre'],
        'matricula'    => $usuario['matricula'],
        'id_carrera'   => $usuario['id_carrera'] !== null ? (int) $usuario['id_carrera'] : null,
        'carreraCorta' => $usuario['carrera_clave'] ?? '',
        'carrera'      => $usuario['carrera_nombre']
            ? sprintf('%s (%s)', $usuario['carrera_nombre'], $usuario['carrera_clave'])
            : '',
        'correo'       => $usuario['correo'],
        'id_rol'       => (int) $usuario['id_rol'],
        'rol'          => rolSlug((int) $usuario['id_rol']),
        'rol_nombre'   => $usuario['rol_nombre'],
    ];
}

/**
 * Exige una sesión activa y válida; de lo contrario responde HTTP 401.
 */
function exigirSesion(PDO $pdo): array
{
    $usuario = obtenerUsuarioSesion($pdo);
    if (!$usuario) {
        jsonError('Debes iniciar sesión para continuar.', 401);
    }
    return $usuario;
}

/**
 * Exige que el usuario autenticado posea alguno de los roles permitidos.
 */
function exigirRol(PDO $pdo, int ...$rolesPermitidos): array
{
    $usuario = exigirSesion($pdo);
    if (!in_array($usuario['id_rol'], $rolesPermitidos, true)) {
        jsonError('No tienes permisos para acceder a este recurso.', 403);
    }
    return $usuario;
}

/**
 * Cierra y limpia la sesión actual.
 */
function cerrarSesionActual(): void
{
    iniciarSesionSegura();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}
