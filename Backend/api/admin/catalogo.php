<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth_guard.php';

$method = requireMethod('GET', 'POST', 'PUT', 'DELETE');
$pdo    = getPDO();
exigirRol($pdo, ROL_ADMIN);

if ($method === 'GET') {
    $stmt = $pdo->query('SELECT id_materia, clave, nombre, descripcion FROM Materias ORDER BY clave ASC');
    jsonResponse(['ok' => true, 'materias' => $stmt->fetchAll()]);
}

$data = readJsonBody();

if ($method === 'POST' || $method === 'PUT') {
    $idMateria   = (int) ($data['id_materia'] ?? 0);
    $clave       = mb_strtoupper(trim((string) ($data['clave'] ?? '')));
    $nombre      = trim((string) ($data['nombre'] ?? ''));
    $descripcion = trim((string) ($data['descripcion'] ?? ''));

    if ($clave === '' || mb_strlen($clave) > 10) {
        jsonError('Ingresa una clave válida (máx. 10 caracteres).', 422);
    }
    if ($nombre === '' || mb_strlen($nombre) > 100) {
        jsonError('Ingresa un nombre válido (máx. 100 caracteres).', 422);
    }
    if (mb_strlen($descripcion) > 200) {
        jsonError('La descripción no debe superar 200 caracteres.', 422);
    }

    try {
        if ($method === 'POST') {
            $stmt = $pdo->prepare(
                'INSERT INTO Materias (clave, nombre, descripcion) VALUES (:clave, :nombre, :descripcion)'
            );
            $stmt->execute([
                ':clave'       => $clave,
                ':nombre'      => $nombre,
                ':descripcion' => $descripcion !== '' ? $descripcion : null,
            ]);
            jsonResponse([
                'ok'         => true,
                'mensaje'    => 'Materia registrada en el catálogo.',
                'id_materia' => (int) $pdo->lastInsertId(),
            ], 201);
        }

        if ($idMateria <= 0) {
            jsonError('ID de materia inválido.', 422);
        }
        $stmt = $pdo->prepare(
            'UPDATE Materias SET clave = :clave, nombre = :nombre, descripcion = :descripcion WHERE id_materia = :id'
        );
        $stmt->execute([
            ':clave'       => $clave,
            ':nombre'      => $nombre,
            ':descripcion' => $descripcion !== '' ? $descripcion : null,
            ':id'          => $idMateria,
        ]);
        jsonResponse(['ok' => true, 'mensaje' => 'Materia actualizada correctamente.']);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            jsonError('Ya existe una materia con esa clave.', 409);
        }
        jsonError('Error al guardar la materia.', 500);
    }
}

// DELETE
$idMateria = (int) ($data['id_materia'] ?? 0);
if ($idMateria <= 0) {
    jsonError('ID de materia inválido.', 422);
}

try {
    $stmt = $pdo->prepare('DELETE FROM Materias WHERE id_materia = :id');
    $stmt->execute([':id' => $idMateria]);
    jsonResponse(['ok' => true, 'mensaje' => 'Materia eliminada del catálogo.']);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        jsonError('No se puede eliminar: existen tutores o solicitudes ligadas a esta materia.', 409);
    }
    jsonError('Error al eliminar la materia.', 500);
}
