<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth_guard.php';

requireMethod('POST');

$pdo = getPDO();
$usuario = exigirRol($pdo, ROL_ESTUDIANTE);

$materiasRaw = $_POST['materias'] ?? [];
if (!is_array($materiasRaw) || count($materiasRaw) === 0) {
    jsonError('Selecciona al menos una materia para impartir.', 422);
}

$materiasIds = array_values(array_unique(array_filter(
    array_map('intval', $materiasRaw),
    static fn(int $id): bool => $id > 0
)));

if (count($materiasIds) === 0) {
    jsonError('Las materias seleccionadas no son válidas.', 422);
}

if (!isset($_FILES['kardex']) || $_FILES['kardex']['error'] !== UPLOAD_ERR_OK) {
    jsonError('Adjunta tu Kardex en formato PDF.', 422);
}

$archivo = $_FILES['kardex'];
if ($archivo['size'] > 5 * 1024 * 1024) {
    jsonError('El archivo PDF no debe exceder 5 MB.', 422);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($archivo['tmp_name']);
if ($mime !== 'application/pdf') {
    jsonError('El archivo adjunto debe ser un PDF válido.', 422);
}

$uploadDir = __DIR__ . '/../../uploads/kardex';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
    jsonError('No se pudo preparar la carpeta de expedientes.', 500);
}

$nombreArchivo = sprintf('kardex_%d_%s.pdf', $usuario['id_usuario'], bin2hex(random_bytes(8)));
$rutaDestino   = $uploadDir . DIRECTORY_SEPARATOR . $nombreArchivo;

try {
    // Verificar que no exista una solicitud pendiente (id_estado = 1)
    $stmtPend = $pdo->prepare(
        'SELECT id_solicitud FROM SolicitudesTutor WHERE id_usuario = :id AND id_estado = 1 LIMIT 1'
    );
    $stmtPend->execute([':id' => $usuario['id_usuario']]);
    if ($stmtPend->fetch()) {
        jsonError('Ya tienes una solicitud pendiente de revisión por Jefatura.', 409);
    }

    // Verificar que todas las materias existan en el catálogo
    $placeholders = implode(',', array_fill(0, count($materiasIds), '?'));
    $stmtMat = $pdo->prepare("SELECT COUNT(*) FROM Materias WHERE id_materia IN ($placeholders)");
    $stmtMat->execute($materiasIds);
    if ((int) $stmtMat->fetchColumn() !== count($materiasIds)) {
        jsonError('Alguna materia seleccionada no existe en el catálogo.', 422);
    }

    if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
        jsonError('No se pudo guardar el archivo Kardex.', 500);
    }

    $pdo->beginTransaction();

    $stmtSol = $pdo->prepare(
        'INSERT INTO SolicitudesTutor (id_usuario, kardex_pdf, id_estado)
         VALUES (:id_usuario, :kardex_pdf, 1)'
    );
    $stmtSol->execute([
        ':id_usuario' => $usuario['id_usuario'],
        ':kardex_pdf' => $nombreArchivo,
    ]);
    $idSolicitud = (int) $pdo->lastInsertId();

    $stmtSolMat = $pdo->prepare(
        'INSERT INTO SolicitudMaterias (id_solicitud, id_materia) VALUES (:id_solicitud, :id_materia)'
    );
    foreach ($materiasIds as $idMateria) {
        $stmtSolMat->execute([
            ':id_solicitud' => $idSolicitud,
            ':id_materia'   => $idMateria,
        ]);
    }

    $pdo->commit();

    jsonResponse([
        'ok'           => true,
        'mensaje'      => 'Solicitud enviada a Jefatura para revisión.',
        'id_solicitud' => $idSolicitud,
    ], 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (is_file($rutaDestino)) {
        @unlink($rutaDestino);
    }
    jsonError('Error al registrar la solicitud de tutor.', 500);
}
