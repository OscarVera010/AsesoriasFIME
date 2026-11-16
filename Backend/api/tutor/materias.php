<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth_guard.php';

$method  = requireMethod('GET', 'POST', 'DELETE');
$pdo     = getPDO();
$usuario = exigirRol($pdo, ROL_TUTOR);
$idTutor = $usuario['id_usuario'];

if ($method === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT m.id_materia, m.clave, m.nombre, m.descripcion, tm.publicada
         FROM TutorMaterias tm
         INNER JOIN Materias m ON m.id_materia = tm.id_materia
         WHERE tm.id_tutor = :tutor
         ORDER BY m.nombre ASC'
    );
    $stmt->execute([':tutor' => $idTutor]);
    $todas = $stmt->fetchAll();

    $publicadas  = [];
    $disponibles = [];
    foreach ($todas as $row) {
        $item = [
            'id_materia'  => (int) $row['id_materia'],
            'clave'       => $row['clave'],
            'nombre'      => $row['nombre'],
            'descripcion' => $row['descripcion'],
        ];
        if ((int) $row['publicada'] === 1) {
            $publicadas[] = $item;
        } else {
            $disponibles[] = $item;
        }
    }

    jsonResponse([
        'ok'          => true,
        'publicadas'  => $publicadas,
        'disponibles' => $disponibles,
    ]);
}

$data      = readJsonBody();
$idMateria = (int) ($data['id_materia'] ?? 0);
if ($idMateria <= 0) {
    jsonError('Selecciona una materia válida.', 422);
}

if ($method === 'POST') {
    $stmt = $pdo->prepare(
        'UPDATE TutorMaterias SET publicada = 1 WHERE id_tutor = :tutor AND id_materia = :materia'
    );
    $stmt->execute([':tutor' => $idTutor, ':materia' => $idMateria]);

    if ($stmt->rowCount() === 0) {
        $stmtCheck = $pdo->prepare(
            'SELECT publicada FROM TutorMaterias WHERE id_tutor = :tutor AND id_materia = :materia LIMIT 1'
        );
        $stmtCheck->execute([':tutor' => $idTutor, ':materia' => $idMateria]);
        $existe = $stmtCheck->fetch();
        if (!$existe) {
            jsonError('No tienes autorización de Jefatura para impartir esa materia.', 403);
        }
    }

    jsonResponse(['ok' => true, 'mensaje' => 'Materia publicada en tu perfil de tutor.']);
}

// DELETE: despublicar y eliminar horarios asociados
try {
    $pdo->beginTransaction();

    $pdo->prepare('UPDATE TutorMaterias SET publicada = 0 WHERE id_tutor = :tutor AND id_materia = :materia')
        ->execute([':tutor' => $idTutor, ':materia' => $idMateria]);

    $pdo->prepare('DELETE FROM Horarios WHERE id_tutor = :tutor AND id_materia = :materia')
        ->execute([':tutor' => $idTutor, ':materia' => $idMateria]);

    $pdo->commit();
    jsonResponse(['ok' => true, 'mensaje' => 'Has dejado de impartir la materia y se eliminaron sus horarios.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonError('Error al despublicar la materia.', 500);
}
