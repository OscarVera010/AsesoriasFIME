<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth_guard.php';

$method = requireMethod('GET', 'PATCH', 'DELETE');
$pdo    = getPDO();
exigirRol($pdo, ROL_ADMIN);

if ($method === 'GET') {
    $stmt = $pdo->query(
        'SELECT p.id_peticion, p.nombre_materia, p.semestre, p.comentarios, p.fecha,
                p.id_urgencia, u.nombre AS urgencia,
                p.id_estado, e.nombre AS estado,
                est.nombre AS estudiante, est.matricula
         FROM PeticionesMateria p
         INNER JOIN Urgencias u ON u.id_urgencia = p.id_urgencia
         INNER JOIN EstadosRevision e ON e.id_estado = p.id_estado
         INNER JOIN Usuarios est ON est.id_usuario = p.id_estudiante
         ORDER BY (p.id_estado = 1) DESC, p.id_urgencia DESC, p.fecha DESC'
    );
    jsonResponse(['ok' => true, 'peticiones' => $stmt->fetchAll()]);
}

$data = readJsonBody();

if ($method === 'PATCH') {
    $idPeticion = (int) ($data['id_peticion'] ?? 0);
    $idEstado   = (int) ($data['id_estado'] ?? 0);

    if ($idPeticion <= 0 || !in_array($idEstado, [2, 3], true)) {
        jsonError('Datos inválidos para actualizar petición.', 422);
    }

    $stmt = $pdo->prepare('UPDATE PeticionesMateria SET id_estado = :estado WHERE id_peticion = :id');
    $stmt->execute([':estado' => $idEstado, ':id' => $idPeticion]);
    jsonResponse([
        'ok'      => true,
        'mensaje' => $idEstado === 2 ? 'Petición aprobada.' : 'Petición rechazada.',
    ]);
}

// DELETE
if (isset($_GET['limpiar']) && $_GET['limpiar'] === '1') {
    $pdo->exec('DELETE FROM PeticionesMateria WHERE id_estado IN (2, 3)');
    jsonResponse(['ok' => true, 'mensaje' => 'Peticiones revisadas eliminadas.']);
}

$idPeticion = (int) ($data['id_peticion'] ?? 0);
if ($idPeticion <= 0) {
    jsonError('ID de petición inválido.', 422);
}

$stmt = $pdo->prepare('DELETE FROM PeticionesMateria WHERE id_peticion = :id AND id_estado <> 1');
$stmt->execute([':id' => $idPeticion]);
if ($stmt->rowCount() === 0) {
    jsonError('Solo se pueden eliminar peticiones ya revisadas.', 409);
}
jsonResponse(['ok' => true, 'mensaje' => 'Petición eliminada.']);
