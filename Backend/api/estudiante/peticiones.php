<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth_guard.php';

$method  = requireMethod('GET', 'POST');
$pdo     = getPDO();
$usuario = exigirRol($pdo, ROL_ESTUDIANTE);
$idEst   = $usuario['id_usuario'];

if ($method === 'GET') {
    $stmt = $pdo->prepare(
        "SELECT p.id_peticion, p.nombre_materia, p.semestre, p.comentarios,
                DATE_FORMAT(p.fecha, '%d/%m/%Y') AS fecha,
                u.nombre AS urgencia,
                e.nombre AS estado
         FROM PeticionesMateria p
         INNER JOIN Urgencias u ON u.id_urgencia = p.id_urgencia
         INNER JOIN EstadosRevision e ON e.id_estado = p.id_estado
         WHERE p.id_estudiante = :est
         ORDER BY p.fecha DESC"
    );
    $stmt->execute([':est' => $idEst]);
    jsonResponse(['ok' => true, 'peticiones' => $stmt->fetchAll()]);
}

$data          = readJsonBody();
$nombreMateria = trim((string) ($data['nombre_materia'] ?? ''));
$semestre      = trim((string) ($data['semestre'] ?? ''));
$idUrgencia    = (int) ($data['id_urgencia'] ?? 1);
$comentarios   = trim((string) ($data['comentarios'] ?? ''));

if ($nombreMateria === '' || mb_strlen($nombreMateria) > 100) {
    jsonError('Ingresa el nombre de la materia (máx. 100 caracteres).', 422);
}
if (!in_array($idUrgencia, [1, 2, 3], true)) {
    jsonError('Nivel de urgencia inválido.', 422);
}

$stmt = $pdo->prepare(
    'INSERT INTO PeticionesMateria (id_estudiante, nombre_materia, semestre, id_urgencia, comentarios, id_estado)
     VALUES (:est, :mat, :sem, :urg, :com, 1)'
);
$stmt->execute([
    ':est' => $idEst,
    ':mat' => $nombreMateria,
    ':sem' => $semestre !== '' ? mb_substr($semestre, 0, 20) : null,
    ':urg' => $idUrgencia,
    ':com' => $comentarios !== '' ? $comentarios : null,
]);

jsonResponse([
    'ok'          => true,
    'mensaje'     => 'Petición enviada a Jefatura.',
    'id_peticion' => (int) $pdo->lastInsertId(),
], 201);
