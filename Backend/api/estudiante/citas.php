<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth_guard.php';

$method  = requireMethod('GET', 'POST', 'DELETE');
$pdo     = getPDO();
$usuario = exigirRol($pdo, ROL_ESTUDIANTE);
$idEst   = $usuario['id_usuario'];

if ($method === 'GET') {
    $stmt = $pdo->prepare(
        "SELECT c.id_horario, c.asistio, c.fecha_agendada,
                h.fecha, h.id_turno, h.aula,
                DAYOFWEEK(h.fecha) AS dia_semana_num,
                m.nombre AS materia,
                u.nombre AS tutor,
                DATE_FORMAT(t.hora_inicio, '%h:%i %p') AS hora_inicio,
                DATE_FORMAT(t.hora_fin, '%h:%i %p') AS hora_fin,
                cal.puntuacion AS mi_calificacion
         FROM Citas c
         INNER JOIN Horarios h ON h.id_horario = c.id_horario
         INNER JOIN Materias m ON m.id_materia = h.id_materia
         INNER JOIN Usuarios u ON u.id_usuario = h.id_tutor
         INNER JOIN Turnos t ON t.id_turno = h.id_turno
         LEFT JOIN Calificaciones cal ON cal.id_horario = c.id_horario AND cal.id_estudiante = c.id_estudiante
         WHERE c.id_estudiante = :est
         ORDER BY h.fecha ASC, t.hora_inicio ASC"
    );
    $stmt->execute([':est' => $idEst]);
    $citas = $stmt->fetchAll();

    $diasMapa = [
        2 => 'Lunes',
        3 => 'Martes',
        4 => 'Miércoles',
        5 => 'Jueves',
        6 => 'Viernes',
        7 => 'Sábado',
        1 => 'Domingo',
    ];

    foreach ($citas as &$cita) {
        $cita['id_horario']      = (int) $cita['id_horario'];
        $cita['asistio']         = (int) $cita['asistio'] === 1;
        $cita['dia']             = $diasMapa[(int) $cita['dia_semana_num']] ?? '';
        $cita['mi_calificacion'] = $cita['mi_calificacion'] !== null ? (int) $cita['mi_calificacion'] : null;
        unset($cita['dia_semana_num']);
    }

    jsonResponse(['ok' => true, 'citas' => $citas]);
}

$data      = readJsonBody();
$idHorario = (int) ($data['id_horario'] ?? 0);
if ($idHorario <= 0) {
    jsonError('Horario inválido.', 422);
}

if ($method === 'POST') {
    try {
        $pdo->beginTransaction();

        // Bloqueo pesimista sobre la fila del horario para evitar sobrecupo concurrente
        $stmtLock = $pdo->prepare(
            'SELECT h.id_horario, h.id_tutor, h.fecha, h.id_turno, h.cupo,
                    tm.publicada, u.activo
             FROM Horarios h
             INNER JOIN TutorMaterias tm ON tm.id_tutor = h.id_tutor AND tm.id_materia = h.id_materia
             INNER JOIN Usuarios u ON u.id_usuario = h.id_tutor
             WHERE h.id_horario = :id
             FOR UPDATE'
        );
        $stmtLock->execute([':id' => $idHorario]);
        $hor = $stmtLock->fetch();

        if (!$hor || (int) $hor['publicada'] !== 1 || (int) $hor['activo'] !== 1) {
            $pdo->rollBack();
            jsonError('Este horario no está disponible.', 404);
        }
        if ((int) $hor['id_tutor'] === $idEst) {
            $pdo->rollBack();
            jsonError('No puedes agendar tu propia asesoría.', 409);
        }

        // Verificar que no esté ya inscrito en este horario
        $stmtDup = $pdo->prepare(
            'SELECT 1 FROM Citas WHERE id_horario = :h AND id_estudiante = :e LIMIT 1'
        );
        $stmtDup->execute([':h' => $idHorario, ':e' => $idEst]);
        if ($stmtDup->fetch()) {
            $pdo->rollBack();
            jsonError('Ya tienes reservada una plaza en este horario.', 409);
        }

        // Verificar cupo disponible
        $stmtCount = $pdo->prepare('SELECT COUNT(*) FROM Citas WHERE id_horario = :h');
        $stmtCount->execute([':h' => $idHorario]);
        if ((int) $stmtCount->fetchColumn() >= (int) $hor['cupo']) {
            $pdo->rollBack();
            jsonError('Cupo agotado para este horario.', 409);
        }

        // Verificar que el estudiante no tenga otra cita en la misma fecha y turno (anti-empalme)
        $stmtEmpalme = $pdo->prepare(
            'SELECT 1
             FROM Citas c
             INNER JOIN Horarios h ON h.id_horario = c.id_horario
             WHERE c.id_estudiante = :e AND h.fecha = :fecha AND h.id_turno = :turno
             LIMIT 1'
        );
        $stmtEmpalme->execute([
            ':e'     => $idEst,
            ':fecha' => $hor['fecha'],
            ':turno' => $hor['id_turno'],
        ]);
        if ($stmtEmpalme->fetch()) {
            $pdo->rollBack();
            jsonError('Ya tienes otra asesoría agendada en esa misma fecha y turno.', 409);
        }

        $pdo->prepare(
            'INSERT INTO Citas (id_horario, id_estudiante, asistio) VALUES (:h, :e, 0)'
        )->execute([':h' => $idHorario, ':e' => $idEst]);

        $pdo->commit();
        jsonResponse(['ok' => true, 'mensaje' => 'Cita agendada con éxito.'], 201);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonError('Error al agendar la cita.', 500);
    }
}

// DELETE: cancelar solo la cita del estudiante actual (libera su plaza)
$stmtDel = $pdo->prepare('DELETE FROM Citas WHERE id_horario = :h AND id_estudiante = :e');
$stmtDel->execute([':h' => $idHorario, ':e' => $idEst]);
if ($stmtDel->rowCount() === 0) {
    jsonError('Cita no encontrada.', 404);
}
jsonResponse(['ok' => true, 'mensaje' => 'Cita cancelada. Tu plaza fue liberada.']);
