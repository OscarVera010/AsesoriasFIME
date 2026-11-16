<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth_guard.php';

requireMethod('GET');

try {
    $pdo = getPDO();
    exigirRol($pdo, ROL_ADMIN);

    $solicitudesPendientes = (int) $pdo->query('SELECT COUNT(*) FROM SolicitudesTutor WHERE id_estado = 1')->fetchColumn();
    $tutoresActivos        = (int) $pdo->query('SELECT COUNT(*) FROM Usuarios WHERE id_rol = 2 AND activo = 1')->fetchColumn();
    $materiasCatalogo      = (int) $pdo->query('SELECT COUNT(*) FROM Materias')->fetchColumn();

    jsonResponse([
        'ok'                     => true,
        'solicitudes_pendientes' => $solicitudesPendientes,
        'tutores_activos'        => $tutoresActivos,
        'materias_catalogo'      => $materiasCatalogo,
    ]);
} catch (Throwable $e) {
    jsonError('Error al obtener el resumen de administración.', 500);
}
