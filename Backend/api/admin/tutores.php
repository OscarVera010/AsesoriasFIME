<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth_guard.php';

$method = requireMethod('GET', 'PUT', 'DELETE');
$pdo    = getPDO();
exigirRol($pdo, ROL_ADMIN);

if ($method === 'GET') {
    try {
        $stmt = $pdo->query(
            "SELECT u.id_usuario, u.nombre, u.matricula, u.activo,
                    GROUP_CONCAT(m.nombre ORDER BY m.nombre SEPARATOR '||') AS materias_str,
                    GROUP_CONCAT(m.id_materia ORDER BY m.nombre SEPARATOR ',') AS materias_ids_str
             FROM Usuarios u
             LEFT JOIN TutorMaterias tm ON tm.id_tutor = u.id_usuario
             LEFT JOIN Materias m ON m.id_materia = tm.id_materia
             WHERE u.id_rol = 2
             GROUP BY u.id_usuario, u.nombre, u.matricula, u.activo
             ORDER BY u.activo DESC, u.nombre ASC"
        );
        $tutores = $stmt->fetchAll();
        foreach ($tutores as &$t) {
            $t['id_usuario']   = (int) $t['id_usuario'];
            $t['activo']       = (int) $t['activo'];
            $t['materias']     = $t['materias_str'] ? explode('||', $t['materias_str']) : [];
            $t['materias_ids'] = $t['materias_ids_str']
                ? array_map('intval', explode(',', $t['materias_ids_str']))
                : [];
            unset($t['materias_str'], $t['materias_ids_str']);
        }
        jsonResponse(['ok' => true, 'tutores' => $tutores]);
    } catch (Throwable $e) {
        jsonError('Error al consultar tutores.', 500);
    }
}

$data    = readJsonBody();
$idTutor = (int) ($data['id_tutor'] ?? 0);
if ($idTutor <= 0) {
    jsonError('ID de tutor inválido.', 422);
}

if ($method === 'PUT') {
    $accion = (string) ($data['accion'] ?? '');

    if ($accion === 'estado') {
        $activo = (int) ($data['activo'] ?? 0) ? 1 : 0;
        $stmt = $pdo->prepare('UPDATE Usuarios SET activo = :activo WHERE id_usuario = :id AND id_rol = 2');
        $stmt->execute([':activo' => $activo, ':id' => $idTutor]);
        jsonResponse([
            'ok'      => true,
            'mensaje' => $activo ? 'Tutor reactivado.' : 'Tutor suspendido.',
        ]);
    }

    if ($accion === 'permisos') {
        $materiasRaw = $data['materias'] ?? [];
        if (!is_array($materiasRaw)) {
            jsonError('Formato de materias inválido.', 422);
        }
        $nuevasIds = array_values(array_unique(array_filter(
            array_map('intval', $materiasRaw),
            static fn(int $id): bool => $id > 0
        )));

        try {
            $pdo->beginTransaction();

            $stmtCheck = $pdo->prepare('SELECT id_usuario FROM Usuarios WHERE id_usuario = :id AND id_rol = 2 LIMIT 1');
            $stmtCheck->execute([':id' => $idTutor]);
            if (!$stmtCheck->fetch()) {
                $pdo->rollBack();
                jsonError('Tutor no encontrado.', 404);
            }

            if (count($nuevasIds) === 0) {
                $pdo->prepare('DELETE FROM TutorMaterias WHERE id_tutor = :tutor')->execute([':tutor' => $idTutor]);
            } else {
                $placeholders = implode(',', array_fill(0, count($nuevasIds), '?'));
                $stmtDel = $pdo->prepare("DELETE FROM TutorMaterias WHERE id_tutor = ? AND id_materia NOT IN ($placeholders)");
                $stmtDel->execute(array_merge([$idTutor], $nuevasIds));

                $stmtIns = $pdo->prepare('INSERT IGNORE INTO TutorMaterias (id_tutor, id_materia, publicada) VALUES (:t, :m, 0)');
                foreach ($nuevasIds as $idMat) {
                    $stmtIns->execute([':t' => $idTutor, ':m' => $idMat]);
                }
            }

            $pdo->commit();
            jsonResponse(['ok' => true, 'mensaje' => 'Permisos actualizados correctamente.']);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            jsonError('Error al actualizar permisos.', 500);
        }
    }

    jsonError('Acción no reconocida.', 422);
}

// DELETE: Dar de baja al tutor (regresa a rol Estudiante y limpia sus materias/horarios)
try {
    $pdo->beginTransaction();

    $stmtUpd = $pdo->prepare('UPDATE Usuarios SET id_rol = :rol, activo = 1 WHERE id_usuario = :id AND id_rol = 2');
    $stmtUpd->execute([':rol' => ROL_ESTUDIANTE, ':id' => $idTutor]);

    if ($stmtUpd->rowCount() === 0) {
        $pdo->rollBack();
        jsonError('Tutor no encontrado.', 404);
    }

    $pdo->prepare('DELETE FROM TutorMaterias WHERE id_tutor = :id')->execute([':id' => $idTutor]);

    $pdo->commit();
    jsonResponse(['ok' => true, 'mensaje' => 'Tutor dado de baja. Su cuenta regresó a rol Estudiante.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonError('Error al dar de baja al tutor.', 500);
}
