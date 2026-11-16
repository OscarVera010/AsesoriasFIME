<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth_guard.php';

requireMethod('GET');

$pdo = getPDO();
exigirRol($pdo, ROL_ADMIN);

$idSolicitud = (int) ($_GET['id'] ?? 0);
if ($idSolicitud <= 0) {
    jsonError('ID de solicitud inválido.', 422);
}

$stmt = $pdo->prepare('SELECT kardex_pdf FROM SolicitudesTutor WHERE id_solicitud = :id LIMIT 1');
$stmt->execute([':id' => $idSolicitud]);
$archivo = $stmt->fetchColumn();

if (!$archivo) {
    jsonError('Expediente no encontrado.', 404);
}

$nombreSeguro = basename((string) $archivo);
$ruta         = __DIR__ . '/../../uploads/kardex/' . $nombreSeguro;

if (!is_file($ruta)) {
    jsonError('El archivo PDF no existe en el servidor.', 404);
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $nombreSeguro . '"');
header('Content-Length: ' . filesize($ruta));
readfile($ruta);
exit;
