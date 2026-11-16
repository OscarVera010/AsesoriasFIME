<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth_guard.php';

requireMethod('POST');

$data       = readJsonBody();
$credencial = trim((string) ($data['credencial'] ?? ''));
$password   = (string) ($data['password'] ?? '');

if ($credencial === '' || $password === '') {
    jsonError('Completa tu credencial y contraseña.', 422);
}

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare(
        'SELECT u.id_usuario, u.nombre, u.matricula, u.id_carrera, u.correo,
                u.password_hash, u.id_rol, u.activo,
                r.nombre AS rol_nombre,
                c.clave AS carrera_clave, c.nombre AS carrera_nombre
         FROM Usuarios u
         INNER JOIN Roles r ON r.id_rol = u.id_rol
         LEFT JOIN Carreras c ON c.id_carrera = u.id_carrera
         WHERE u.correo = :correo OR u.matricula = :matricula
         LIMIT 1'
    );
    $stmt->execute([
        ':correo'    => mb_strtolower($credencial),
        ':matricula' => $credencial,
    ]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonError('Credenciales incorrectas.', 401);
    }

    if (!(bool) $user['activo']) {
        jsonError('Tu cuenta se encuentra suspendida. Contacta a Jefatura.', 403);
    }

    iniciarSesionSegura();
    session_regenerate_id(true);
    $_SESSION['id_usuario'] = (int) $user['id_usuario'];
    $_SESSION['id_rol']     = (int) $user['id_rol'];

    jsonResponse([
        'ok'      => true,
        'usuario' => [
            'id_usuario'   => (int) $user['id_usuario'],
            'nombre'       => $user['nombre'],
            'matricula'    => $user['matricula'],
            'id_carrera'   => $user['id_carrera'] !== null ? (int) $user['id_carrera'] : null,
            'carreraCorta' => $user['carrera_clave'] ?? '',
            'carrera'      => $user['carrera_nombre']
                ? sprintf('%s (%s)', $user['carrera_nombre'], $user['carrera_clave'])
                : '',
            'correo'       => $user['correo'],
            'id_rol'       => (int) $user['id_rol'],
            'rol'          => rolSlug((int) $user['id_rol']),
            'rol_nombre'   => $user['rol_nombre'],
        ],
    ]);
} catch (Throwable $e) {
    jsonError('Error al iniciar sesión.', 500);
}
