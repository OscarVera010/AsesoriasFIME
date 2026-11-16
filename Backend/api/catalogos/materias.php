<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/response.php';

requireMethod('GET');

try {
    $pdo = getPDO();
    $stmt = $pdo->query('SELECT id_materia, clave, nombre, descripcion FROM Materias ORDER BY nombre ASC');
    jsonResponse(['ok' => true, 'materias' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    jsonError('Error al consultar el catálogo de materias.', 500);
}
