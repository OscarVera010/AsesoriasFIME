<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/response.php';

requireMethod('POST');

$data   = readJsonBody();
$correo = mb_strtolower(trim((string) ($data['correo'] ?? '')));

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    jsonError('Ingresa un correo electrónico válido.', 422);
}

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id_usuario FROM Usuarios WHERE correo = :correo LIMIT 1');
    $stmt->execute([':correo' => $correo]);
    $existe = (bool) $stmt->fetch();

    // Respuesta neutra para no revelar si la cuenta existe
    jsonResponse([
        'ok'      => true,
        'mensaje' => 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.',
        'simulado_enviado' => $existe,
    ]);
} catch (Throwable $e) {
    jsonError('Error al procesar la solicitud.', 500);
}
