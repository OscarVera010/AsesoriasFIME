<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth_guard.php';

requireMethod('POST');

$pdo     = getPDO();
$usuario = exigirRol($pdo, ROL_ESTUDIANTE);
$idEst   = $usuario['id_usuario'];

$data       = readJsonBody();
$idHorario  = (int) ($data['id_horario'] ?? 0);
$puntuacion = (int) ($data['puntuacion'] ?? 0);

if ($idHorario <= 0 || $puntuacion < 1 || $puntuacion > 5) {
    jsonError('Selecciona una puntuación válida de 1 a 5 estrellas.', 422);
}

try {
    $stmtCita = $pdo->prepare(
        'SELECT asistio FROM Citas WHERE id_horario = :h AND id_estudiante = :e LIMIT 1'
    );
    $stmtCita->execute([':h' => $idHorario, ':e' => $idEst]);
    $cita = $stmtCita->fetch();

    if (!$cita) {
        jsonError('Debes estar inscrito en esta asesoría para poder calificarla.', 403);
    }

    $stmtIns = $pdo->prepare(
        'INSERT INTO Calificaciones (id_horario, id_estudiante, puntuacion)
         VALUES (:h, :e, :p)
         ON DUPLICATE KEY UPDATE puntuacion = VALUES(puntuacion)'
    );
    $stmtIns->execute([':h' => $idHorario, ':e' => $idEst, ':p' => $puntuacion]);

    jsonResponse(['ok' => true, 'mensaje' => '¡Calificación de la asesoría guardada!'], 200);
} catch (PDOException $e) {
    jsonError('Error al guardar la calificación.', 500);
}
