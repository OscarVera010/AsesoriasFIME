<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth_guard.php';

requireMethod('GET');

$pdo = getPDO();
exigirRol($pdo, ROL_ESTUDIANTE);

try {
    $stmt = $pdo->query(
        "SELECT u.id_usuario, u.nombre,
                c.nombre AS carrera_nombre, c.clave AS carrera_clave,
                GROUP_CONCAT(DISTINCT CASE WHEN tm.publicada = 1 THEN m.nombre END ORDER BY m.nombre SEPARATOR '||') AS materias_str
         FROM Usuarios u
         LEFT JOIN Carreras c ON c.id_carrera = u.id_carrera
         LEFT JOIN TutorMaterias tm ON tm.id_tutor = u.id_usuario
         LEFT JOIN Materias m ON m.id_materia = tm.id_materia
         WHERE u.id_rol = 2 AND u.activo = 1
         GROUP BY u.id_usuario, u.nombre, c.nombre, c.clave
         ORDER BY u.nombre ASC"
    );
    $tutores = $stmt->fetchAll();

    foreach ($tutores as &$t) {
        $t['id_usuario'] = (int) $t['id_usuario'];
        $t['carrera']    = $t['carrera_nombre']
            ? sprintf('%s (%s)', $t['carrera_nombre'], $t['carrera_clave'])
            : 'Tutor FIME';
        $t['materias']   = $t['materias_str'] ? explode('||', $t['materias_str']) : [];
        unset($t['carrera_nombre'], $t['carrera_clave'], $t['materias_str']);
    }

    jsonResponse(['ok' => true, 'tutores' => $tutores]);
} catch (Throwable $e) {
    jsonError('Error al consultar tutores.', 500);
}
