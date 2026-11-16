<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth_guard.php';

requireMethod('GET');

$pdo     = getPDO();
$usuario = exigirRol($pdo, ROL_ESTUDIANTE);
$idEst   = $usuario['id_usuario'];

try {
    $stmt = $pdo->prepare(
        "SELECT h.id_horario, h.id_tutor, h.id_materia, h.fecha, h.id_turno, h.aula, h.cupo,
                m.nombre AS materia, m.descripcion,
                u.nombre AS tutor,
                DATE_FORMAT(t.hora_inicio, '%h:%i %p') AS hora_inicio,
                DATE_FORMAT(t.hora_fin, '%h:%i %p') AS hora_fin,
                COUNT(DISTINCT c.id_estudiante) AS inscritos,
                MAX(CASE WHEN c.id_estudiante = :yo THEN 1 ELSE 0 END) AS ya_inscrito,
                MAX(cal_yo.puntuacion) AS mi_calificacion,
                COALESCE(rat.promedio, 0) AS promedio_calificacion,
                COALESCE(rat.total_resenas, 0) AS total_resenas
         FROM Horarios h
         INNER JOIN TutorMaterias tm ON tm.id_tutor = h.id_tutor AND tm.id_materia = h.id_materia AND tm.publicada = 1
         INNER JOIN Materias m ON m.id_materia = h.id_materia
         INNER JOIN Usuarios u ON u.id_usuario = h.id_tutor AND u.activo = 1 AND u.id_rol = 2
         INNER JOIN Turnos t ON t.id_turno = h.id_turno
         LEFT JOIN Citas c ON c.id_horario = h.id_horario
         LEFT JOIN Calificaciones cal_yo ON cal_yo.id_horario = h.id_horario AND cal_yo.id_estudiante = :yo2
         LEFT JOIN (
             SELECT cal.id_horario,
                    ROUND(AVG(cal.puntuacion), 1) AS promedio,
                    COUNT(cal.puntuacion) AS total_resenas
             FROM Calificaciones cal
             GROUP BY cal.id_horario
         ) rat ON rat.id_horario = h.id_horario
         WHERE h.fecha >= CURDATE()
         GROUP BY h.id_horario, h.id_tutor, h.id_materia, h.fecha, h.id_turno, h.aula, h.cupo,
                  m.nombre, m.descripcion, u.nombre, t.hora_inicio, t.hora_fin,
                  rat.promedio, rat.total_resenas
         ORDER BY h.fecha ASC, t.hora_inicio ASC"
    );
    $stmt->execute([':yo' => $idEst, ':yo2' => $idEst]);
    $horarios = $stmt->fetchAll();

    foreach ($horarios as &$h) {
        $h['id_horario']            = (int) $h['id_horario'];
        $h['id_tutor']              = (int) $h['id_tutor'];
        $h['cupo']                  = (int) $h['cupo'];
        $h['inscritos']             = (int) $h['inscritos'];
        $h['plazas_disponibles']    = max(0, $h['cupo'] - $h['inscritos']);
        $h['ya_inscrito']           = (int) $h['ya_inscrito'] === 1;
        $h['mi_calificacion']       = $h['mi_calificacion'] !== null ? (int) $h['mi_calificacion'] : null;
        $h['promedio_calificacion'] = (float) $h['promedio_calificacion'];
        $h['total_resenas']         = (int) $h['total_resenas'];
    }

    jsonResponse(['ok' => true, 'horarios' => $horarios]);
} catch (Throwable $e) {
    jsonError('Error al consultar horarios disponibles.', 500);
}
