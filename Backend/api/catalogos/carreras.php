<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/response.php';

requireMethod('GET');

try {
    $pdo = getPDO();
    $stmt = $pdo->query('SELECT id_carrera, clave, nombre FROM Carreras ORDER BY id_carrera ASC');
    jsonResponse(['ok' => true, 'carreras' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    jsonError('Error al consultar las carreras.', 500);
}
