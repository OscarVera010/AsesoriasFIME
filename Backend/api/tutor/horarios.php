<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth_guard.php';

$method  = requireMethod('GET', 'POST', 'PUT', 'DELETE');
$pdo     = getPDO();
$usuario = exigirRol($pdo, ROL_TUTOR);
$idTutor = $usuario['id_usuario'];

if ($method === 'GET') {
    $stmtHor = $pdo->prepare(
        "SELECT h.id_horario, h.id_materia, h.fecha, h.id_turno, h.aula, h.cupo,
                m.nombre AS materia,
                DATE_FORMAT(t.hora_inicio, '%h:%i %p') AS hora_inicio,
                DATE_FORMAT(t.hora_fin, '%h:%i %p') AS hora_fin,
                COUNT(c.id_estudiante) AS inscritos
         FROM Horarios h
         INNER JOIN Materias m ON m.id_materia = h.id_materia
         INNER JOIN Turnos t ON t.id_turno = h.id_turno
         LEFT JOIN Citas c ON c.id_horario = h.id_horario
         WHERE h.id_tutor = :tutor
         GROUP BY h.id_horario, h.id_materia, h.fecha, h.id_turno, h.aula, h.cupo,
                  m.nombre, t.hora_inicio, t.hora_fin
         ORDER BY h.fecha ASC, t.hora_inicio ASC"
    );
    $stmtHor->execute([':tutor' => $idTutor]);
    $horarios = $stmtHor->fetchAll();

    $stmtMat = $pdo->prepare(
        'SELECT m.id_materia, m.nombre
         FROM TutorMaterias tm
         INNER JOIN Materias m ON m.id_materia = tm.id_materia
         WHERE tm.id_tutor = :tutor AND tm.publicada = 1
         ORDER BY m.nombre ASC'
    );
    $stmtMat->execute([':tutor' => $idTutor]);

    jsonResponse([
        'ok'                  => true,
        'horarios'            => $horarios,
        'materias_publicadas' => $stmtMat->fetchAll(),
    ]);
}

$data = readJsonBody();

if ($method === 'POST' || $method === 'PUT') {
    $idHorario = (int) ($data['id_horario'] ?? 0);
    $idMateria = (int) ($data['id_materia'] ?? 0);
    $fecha     = trim((string) ($data['fecha'] ?? ''));
    $idTurno   = trim((string) ($data['id_turno'] ?? ''));
    $aula      = trim((string) ($data['aula'] ?? ''));
    $cupo      = (int) ($data['cupo'] ?? 0);

    if ($idMateria <= 0 || $idTurno === '' || $aula === '' || mb_strlen($aula) > 50) {
        jsonError('Completa todos los campos del horario.', 422);
    }
    if ($cupo < 1 || $cupo > 30) {
        jsonError('El cupo permitido es de 1 a 30 alumnos por horario.', 422);
    }
    $dt = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$dt || $dt->format('Y-m-d') !== $fecha) {
        jsonError('Formato de fecha inválido.', 422);
    }
    if ((int) $dt->format('N') === 7) {
        jsonError('No se imparten asesorías en domingo.', 422);
    }

    try {
        // Verificar que la materia esté autorizada y publicada para el tutor
        $stmtAuth = $pdo->prepare(
            'SELECT publicada FROM TutorMaterias WHERE id_tutor = :tutor AND id_materia = :mat LIMIT 1'
        );
        $stmtAuth->execute([':tutor' => $idTutor, ':mat' => $idMateria]);
        $tm = $stmtAuth->fetch();
        if (!$tm || (int) $tm['publicada'] !== 1) {
            jsonError('Primero debes publicar esta materia en tu perfil.', 403);
        }

        if ($method === 'POST') {
            $stmt = $pdo->prepare(
                'INSERT INTO Horarios (id_tutor, id_materia, fecha, id_turno, aula, cupo)
                 VALUES (:tutor, :mat, :fecha, :turno, :aula, :cupo)'
            );
            $stmt->execute([
                ':tutor' => $idTutor,
                ':mat'   => $idMateria,
                ':fecha' => $fecha,
                ':turno' => $idTurno,
                ':aula'  => $aula,
                ':cupo'  => $cupo,
            ]);
            jsonResponse([
                'ok'         => true,
                'mensaje'    => 'Horario publicado correctamente.',
                'id_horario' => (int) $pdo->lastInsertId(),
            ], 201);
        }

        if ($idHorario <= 0) {
            jsonError('ID de horario inválido.', 422);
        }

        $stmtInsc = $pdo->prepare('SELECT COUNT(*) FROM Citas WHERE id_horario = :id');
        $stmtInsc->execute([':id' => $idHorario]);
        if ($cupo < (int) $stmtInsc->fetchColumn()) {
            jsonError('El cupo no puede ser menor al número de alumnos ya inscritos.', 409);
        }

        $stmt = $pdo->prepare(
            'UPDATE Horarios
             SET id_materia = :mat, fecha = :fecha, id_turno = :turno, aula = :aula, cupo = :cupo
             WHERE id_horario = :id AND id_tutor = :tutor'
        );
        $stmt->execute([
            ':mat'   => $idMateria,
            ':fecha' => $fecha,
            ':turno' => $idTurno,
            ':aula'  => $aula,
            ':cupo'  => $cupo,
            ':id'    => $idHorario,
            ':tutor' => $idTutor,
        ]);
        jsonResponse(['ok' => true, 'mensaje' => 'Horario actualizado.']);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            $msg = $e->getMessage();
            if (str_contains($msg, 'uq_fecha_turno_aula')) {
                jsonError('El aula seleccionada ya está ocupada en esa fecha y turno.', 409);
            }
            if (str_contains($msg, 'uq_tutor_fecha_turno')) {
                jsonError('Ya tienes un horario asignado en esa misma fecha y turno.', 409);
            }
            jsonError('Conflicto de horario o turno no válido.', 409);
        }
        jsonError('Error al guardar el horario.', 500);
    }
}

// DELETE
$idHorario = (int) ($data['id_horario'] ?? 0);
if ($idHorario <= 0) {
    jsonError('ID de horario inválido.', 422);
}

$stmt = $pdo->prepare('DELETE FROM Horarios WHERE id_horario = :id AND id_tutor = :tutor');
$stmt->execute([':id' => $idHorario, ':tutor' => $idTutor]);
if ($stmt->rowCount() === 0) {
    jsonError('Horario no encontrado.', 404);
}
jsonResponse(['ok' => true, 'mensaje' => 'Horario eliminado correctamente.']);
