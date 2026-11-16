<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth_guard.php';

$method = requireMethod('GET', 'POST');
$pdo    = getPDO();
exigirRol($pdo, ROL_ADMIN);

if ($method === 'GET') {
    try {
        $stmt = $pdo->query(
            "SELECT s.id_solicitud, s.id_usuario, s.kardex_pdf, s.id_estado, s.fecha_envio,
                    u.nombre, u.matricula,
                    c.clave AS carrera,
                    e.nombre AS estado,
                    GROUP_CONCAT(m.nombre ORDER BY m.nombre SEPARATOR '||') AS materias_str
             FROM SolicitudesTutor s
             INNER JOIN Usuarios u ON u.id_usuario = s.id_usuario
             LEFT JOIN Carreras c ON c.id_carrera = u.id_carrera
             INNER JOIN EstadosRevision e ON e.id_estado = s.id_estado
             LEFT JOIN SolicitudMaterias sm ON sm.id_solicitud = s.id_solicitud
             LEFT JOIN Materias m ON m.id_materia = sm.id_materia
             GROUP BY s.id_solicitud, s.id_usuario, s.kardex_pdf, s.id_estado, s.fecha_envio,
                      u.nombre, u.matricula, c.clave, e.nombre
             ORDER BY (s.id_estado = 1) DESC, s.fecha_envio DESC"
        );
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['id_solicitud'] = (int) $row['id_solicitud'];
            $row['id_usuario']   = (int) $row['id_usuario'];
            $row['id_estado']    = (int) $row['id_estado'];
            $row['materias']     = $row['materias_str'] ? explode('||', $row['materias_str']) : [];
            unset($row['materias_str']);
        }
        jsonResponse(['ok' => true, 'solicitudes' => $rows]);
    } catch (Throwable $e) {
        jsonError('Error al consultar solicitudes de tutor.', 500);
    }
}

$data        = readJsonBody();
$idSolicitud = (int) ($data['id_solicitud'] ?? 0);
$accion      = strtolower(trim((string) ($data['accion'] ?? '')));

if ($idSolicitud <= 0 || !in_array($accion, ['aprobar', 'rechazar'], true)) {
    jsonError('Solicitud o acción inválida.', 422);
}

try {
    $pdo->beginTransaction();

    $stmtLock = $pdo->prepare(
        'SELECT id_solicitud, id_usuario, id_estado FROM SolicitudesTutor WHERE id_solicitud = :id FOR UPDATE'
    );
    $stmtLock->execute([':id' => $idSolicitud]);
    $sol = $stmtLock->fetch();

    if (!$sol) {
        $pdo->rollBack();
        jsonError('Solicitud no encontrada.', 404);
    }
    if ((int) $sol['id_estado'] !== 1) {
        $pdo->rollBack();
        jsonError('Esta solicitud ya fue dictaminada.', 409);
    }

    if ($accion === 'aprobar') {
        $pdo->prepare('UPDATE SolicitudesTutor SET id_estado = 2 WHERE id_solicitud = :id')
            ->execute([':id' => $idSolicitud]);

        $pdo->prepare('UPDATE Usuarios SET id_rol = :rol WHERE id_usuario = :id_usuario')
            ->execute([':rol' => ROL_TUTOR, ':id_usuario' => (int) $sol['id_usuario']]);

        $stmtMats = $pdo->prepare('SELECT id_materia FROM SolicitudMaterias WHERE id_solicitud = :id');
        $stmtMats->execute([':id' => $idSolicitud]);
        $materias = $stmtMats->fetchAll(PDO::FETCH_COLUMN);

        $stmtInsertTM = $pdo->prepare(
            'INSERT IGNORE INTO TutorMaterias (id_tutor, id_materia, publicada) VALUES (:tutor, :materia, 0)'
        );
        foreach ($materias as $idMateria) {
            $stmtInsertTM->execute([
                ':tutor'   => (int) $sol['id_usuario'],
                ':materia' => (int) $idMateria,
            ]);
        }
        $mensaje = 'Solicitud aprobada. El estudiante ahora tiene rol de Tutor.';
    } else {
        $pdo->prepare('UPDATE SolicitudesTutor SET id_estado = 3 WHERE id_solicitud = :id')
            ->execute([':id' => $idSolicitud]);
        $mensaje = 'Solicitud rechazada.';
    }

    $pdo->commit();
    jsonResponse(['ok' => true, 'mensaje' => $mensaje]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonError('Error al dictaminar la solicitud.', 500);
}
