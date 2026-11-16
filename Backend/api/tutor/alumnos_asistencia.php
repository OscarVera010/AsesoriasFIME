<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth_guard.php';

$method  = requireMethod('GET', 'PATCH');
$pdo     = getPDO();
$usuario = exigirRol($pdo, ROL_TUTOR);
$idTutor = $usuario['id_usuario'];

if ($method === 'GET') {
    $stmtHor = $pdo->prepare(
        "SELECT h.id_horario, h.fecha, h.id_turno, h.aula,
                m.nombre AS materia,
                DATE_FORMAT(t.hora_inicio, '%h:%i %p') AS hora_inicio,
                DATE_FORMAT(t.hora_fin, '%h:%i %p') AS hora_fin
         FROM Horarios h
         INNER JOIN Materias m ON m.id_materia = h.id_materia
         INNER JOIN Turnos t ON t.id_turno = h.id_turno
         WHERE h.id_tutor = :tutor
         ORDER BY h.fecha ASC, t.hora_inicio ASC"
    );
    $stmtHor->execute([':tutor' => $idTutor]);
    $horarios = $stmtHor->fetchAll();

    $stmtAlum = $pdo->prepare(
        'SELECT c.id_horario, c.id_estudiante, c.asistio, u.nombre, u.matricula
         FROM Citas c
         INNER JOIN Horarios h ON h.id_horario = c.id_horario
         INNER JOIN Usuarios u ON u.id_usuario = c.id_estudiante
         WHERE h.id_tutor = :tutor
         ORDER BY u.nombre ASC'
    );
    $stmtAlum->execute([':tutor' => $idTutor]);
    $alumnosPorHorario = [];
    $totalCitas = 0;

    foreach ($stmtAlum->fetchAll() as $a) {
        $totalCitas++;
        $hid = (int) $a['id_horario'];
        $alumnosPorHorario[$hid][] = [
            'id_estudiante' => (int) $a['id_estudiante'],
            'nombre'        => $a['nombre'],
            'matricula'     => $a['matricula'],
            'estado'        => (int) $a['asistio'] === 1 ? 'asistio' : 'pendiente',
        ];
    }

    $clases = [];
    foreach ($horarios as $h) {
        $hid = (int) $h['id_horario'];
        $clases[] = [
            'id_horario' => $hid,
            'materia'    => $h['materia'],
            'dia'        => $h['fecha'],
            'turno'      => $h['id_turno'],
            'hora'       => $h['hora_inicio'] . ' - ' . $h['hora_fin'],
            'aula'       => $h['aula'],
            'alumnos'    => $alumnosPorHorario[$hid] ?? [],
        ];
    }

    jsonResponse([
        'ok'          => true,
        'total_citas' => $totalCitas,
        'clases'      => $clases,
    ]);
}

// PATCH: Marcar asistencia
$data         = readJsonBody();
$idHorario    = (int) ($data['id_horario'] ?? 0);
$idEstudiante = (int) ($data['id_estudiante'] ?? 0);

if ($idHorario <= 0 || $idEstudiante <= 0) {
    jsonError('Datos inválidos para registrar asistencia.', 422);
}

$stmtCheck = $pdo->prepare('SELECT id_horario FROM Horarios WHERE id_horario = :h AND id_tutor = :t LIMIT 1');
$stmtCheck->execute([':h' => $idHorario, ':t' => $idTutor]);
if (!$stmtCheck->fetch()) {
    jsonError('No tienes permiso sobre este horario.', 403);
}

$stmtUpd = $pdo->prepare(
    'UPDATE Citas SET asistio = 1 WHERE id_horario = :h AND id_estudiante = :e'
);
$stmtUpd->execute([':h' => $idHorario, ':e' => $idEstudiante]);

if ($stmtUpd->rowCount() === 0) {
    jsonError('Cita no encontrada o asistencia ya registrada.', 404);
}
jsonResponse(['ok' => true, 'mensaje' => 'Asistencia registrada correctamente.']);
