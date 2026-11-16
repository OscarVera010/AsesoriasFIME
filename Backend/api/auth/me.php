<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth_guard.php';

requireMethod('GET');

try {
    $pdo = getPDO();
    $usuario = obtenerUsuarioSesion($pdo);
    if (!$usuario) {
        jsonError('No hay sesión activa.', 401);
    }
    jsonResponse(['ok' => true, 'usuario' => $usuario]);
} catch (Throwable $e) {
    jsonError('Error al verificar la sesión.', 500);
}
