<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth_guard.php';

requireMethod('POST');

$data       = readJsonBody();
$nombre     = trim((string) ($data['nombre'] ?? ''));
$matricula  = trim((string) ($data['matricula'] ?? ''));
$idCarrera  = (int) ($data['id_carrera'] ?? 0);
$correo     = mb_strtolower(trim((string) ($data['correo'] ?? '')));
$password   = (string) ($data['password'] ?? '');

if ($nombre === '' || mb_strlen($nombre) > 100) {
    jsonError('Ingresa un nombre válido (máx. 100 caracteres).', 422);
}
if (!preg_match('/^\d{6,8}$/', $matricula)) {
    jsonError('La matrícula debe tener entre 6 y 8 dígitos.', 422);
}
if ($idCarrera <= 0) {
    jsonError('Selecciona una carrera válida.', 422);
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 100) {
    jsonError('Ingresa un correo institucional válido.', 422);
}
if (strlen($password) < 8) {
    jsonError('La contraseña debe tener al menos 8 caracteres.', 422);
}

try {
    $pdo = getPDO();

    $stmtCarrera = $pdo->prepare('SELECT id_carrera FROM Carreras WHERE id_carrera = :id LIMIT 1');
    $stmtCarrera->execute([':id' => $idCarrera]);
    if (!$stmtCarrera->fetch()) {
        jsonError('La carrera seleccionada no existe en el catálogo.', 422);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        'INSERT INTO Usuarios (nombre, matricula, id_carrera, correo, password_hash, id_rol, activo)
         VALUES (:nombre, :matricula, :id_carrera, :correo, :password_hash, :id_rol, 1)'
    );
    $stmt->execute([
        ':nombre'        => $nombre,
        ':matricula'     => $matricula,
        ':id_carrera'    => $idCarrera,
        ':correo'        => $correo,
        ':password_hash' => $hash,
        ':id_rol'        => ROL_ESTUDIANTE,
    ]);

    jsonResponse([
        'ok'         => true,
        'mensaje'    => 'Cuenta de estudiante creada con éxito.',
        'id_usuario' => (int) $pdo->lastInsertId(),
    ], 201);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        jsonError('La matrícula o el correo ya están registrados.', 409);
    }
    jsonError('Error al registrar el usuario.', 500);
}
