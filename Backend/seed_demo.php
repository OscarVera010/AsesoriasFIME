<?php
declare(strict_types=1);

/**
 * Script idempotente para cargar usuarios, materias, horarios y citas de prueba
 * con contraseñas reales generadas mediante password_hash().
 *
 * Ejecutar una sola vez en entorno local:
 *   php Backend/seed_demo.php
 * o abrir en navegador:
 *   http://localhost/AsesoriasFIME/Backend/seed_demo.php
 *
 * Contraseña común de prueba para todas las cuentas: Fime2026!
 */

require_once __DIR__ . '/config/db.php';

try {
    $pdo = getPDO();
    $pdo->beginTransaction();

    // 1. Materias base
    $materias = [
        ['ITS101', 'Sistemas Digitales', 'Sistemas numéricos, álgebra booleana, mapas de Karnaugh y circuitos combinacionales.'],
        ['ITS105', 'Bases de Datos', 'Modelo entidad-relación, normalización en 3FN y consultas SQL relacionales.'],
        ['ITS204', 'Programación Web', 'Desarrollo front-end con HTML5, CSS3, JavaScript y back-end PHP conectado a MySQL.'],
        ['MAT103', 'Álgebra Lineal', 'Matrices, determinantes, espacios vectoriales y sistemas de ecuaciones lineales.'],
    ];

    $stmtMat = $pdo->prepare(
        'INSERT INTO Materias (clave, nombre, descripcion)
         VALUES (:clave, :nombre, :descripcion)
         ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), descripcion = VALUES(descripcion)'
    );
    foreach ($materias as [$clave, $nombre, $desc]) {
        $stmtMat->execute([':clave' => $clave, ':nombre' => $nombre, ':descripcion' => $desc]);
    }

    $mapMaterias = $pdo->query('SELECT clave, id_materia FROM Materias')->fetchAll(PDO::FETCH_KEY_PAIR);

    // 2. Usuarios demo (Admin, Tutores y Estudiantes)
    $hash = password_hash('Fime2026!', PASSWORD_DEFAULT);
    $usuarios = [
        ['Jefatura FIME',      '1000001', 1, 'admin@uanl.edu.mx',      3],
        ['Tutor Prueba Uno',   '2160859', 1, 'tutor1@uanl.edu.mx',     2],
        ['Tutor Prueba Dos',   '2158224', 2, 'tutor2@uanl.edu.mx',     2],
        ['Estudiante Uno',     '2105894', 1, 'alumno1@uanl.edu.mx',    1],
        ['Estudiante Dos',     '2226653', 1, 'alumno2@uanl.edu.mx',    1],
        ['Estudiante Tres',    '2200111', 2, 'alumno3@uanl.edu.mx',    1],
    ];

    $stmtUser = $pdo->prepare(
        'INSERT INTO Usuarios (nombre, matricula, id_carrera, correo, password_hash, id_rol, activo)
         VALUES (:nombre, :matricula, :id_carrera, :correo, :hash, :id_rol, 1)
         ON DUPLICATE KEY UPDATE nombre = VALUES(nombre)'
    );
    foreach ($usuarios as [$nombre, $mat, $idCarrera, $correo, $idRol]) {
        $stmtUser->execute([
            ':nombre'     => $nombre,
            ':matricula'  => $mat,
            ':id_carrera' => $idCarrera,
            ':correo'     => $correo,
            ':hash'       => $hash,
            ':id_rol'     => $idRol,
        ]);
    }

    $mapUsers = $pdo->query('SELECT matricula, id_usuario FROM Usuarios')->fetchAll(PDO::FETCH_KEY_PAIR);

    // 3. Materias autorizadas y publicadas para los tutores
    $stmtTM = $pdo->prepare(
        'INSERT IGNORE INTO TutorMaterias (id_tutor, id_materia, publicada) VALUES (:tutor, :mat, 1)'
    );
    $stmtTM->execute([':tutor' => $mapUsers['2160859'], ':mat' => $mapMaterias['ITS105']]);
    $stmtTM->execute([':tutor' => $mapUsers['2160859'], ':mat' => $mapMaterias['ITS204']]);
    $stmtTM->execute([':tutor' => $mapUsers['2158224'], ':mat' => $mapMaterias['ITS101']]);
    $stmtTM->execute([':tutor' => $mapUsers['2158224'], ':mat' => $mapMaterias['MAT103']]);

    // 4. Horarios de ejemplo (fechas próximas)
    $lunes   = date('Y-m-d', strtotime('next monday'));
    $martes  = date('Y-m-d', strtotime('next tuesday'));
    $viernes = date('Y-m-d', strtotime('next friday'));

    $stmtHor = $pdo->prepare(
        'INSERT IGNORE INTO Horarios (id_tutor, id_materia, fecha, id_turno, aula, cupo)
         VALUES (:tutor, :mat, :fecha, :turno, :aula, :cupo)'
    );
    $stmtHor->execute([
        ':tutor' => $mapUsers['2160859'],
        ':mat'   => $mapMaterias['ITS105'],
        ':fecha' => $viernes,
        ':turno' => 'V3',
        ':aula'  => 'Aula 1102',
        ':cupo'  => 2,
    ]);
    $stmtHor->execute([
        ':tutor' => $mapUsers['2158224'],
        ':mat'   => $mapMaterias['ITS101'],
        ':fecha' => $lunes,
        ':turno' => 'M1',
        ':aula'  => 'Aula 9203',
        ':cupo'  => 3,
    ]);
    $stmtHor->execute([
        ':tutor' => $mapUsers['2160859'],
        ':mat'   => $mapMaterias['ITS204'],
        ':fecha' => $martes,
        ':turno' => 'N2',
        ':aula'  => 'Aula 2204',
        ':cupo'  => 3,
    ]);

    // 5. Cita inicial para dejar un horario casi lleno y probar cupo/calificación
    $idHorarioBD = (int) $pdo->query(
        "SELECT id_horario FROM Horarios WHERE aula = 'Aula 1102' AND id_turno = 'V3' LIMIT 1"
    )->fetchColumn();
    if ($idHorarioBD > 0) {
        $stmtCita = $pdo->prepare(
            'INSERT IGNORE INTO Citas (id_horario, id_estudiante, asistio) VALUES (:h, :e, 1)'
        );
        $stmtCita->execute([':h' => $idHorarioBD, ':e' => $mapUsers['2226653']]);

        $stmtCal = $pdo->prepare(
            'INSERT IGNORE INTO Calificaciones (id_horario, id_estudiante, puntuacion) VALUES (:h, :e, 5)'
        );
        $stmtCal->execute([':h' => $idHorarioBD, ':e' => $mapUsers['2226653']]);
    }

    // 6. Petición de materia de ejemplo si la tabla está vacía
    if ((int) $pdo->query('SELECT COUNT(*) FROM PeticionesMateria')->fetchColumn() === 0) {
        $stmtPet = $pdo->prepare(
            'INSERT INTO PeticionesMateria (id_estudiante, nombre_materia, semestre, id_urgencia, comentarios, id_estado)
             VALUES (:est, :mat, :sem, 2, :com, 1)'
        );
        $stmtPet->execute([
            ':est' => $mapUsers['2105894'],
            ':mat' => 'Ecuaciones Diferenciales',
            ':sem' => '4to semestre',
            ':com' => 'Refuerzo en transformada de Laplace.',
        ]);
    }

    $pdo->commit();

    header('Content-Type: text/plain; charset=utf-8');
    echo "Seed de prueba cargado correctamente.\n";
    echo "Contraseña para todas las cuentas: Fime2026!\n";
    echo "- Admin:      admin@uanl.edu.mx   (matrícula 1000001)\n";
    echo "- Tutor 1:    tutor1@uanl.edu.mx  (matrícula 2160859)\n";
    echo "- Tutor 2:    tutor2@uanl.edu.mx  (matrícula 2158224)\n";
    echo "- Estudiante: alumno1@uanl.edu.mx (matrícula 2105894)\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Error al cargar seed_demo: ' . $e->getMessage() . "\n";
}
