<?php
declare(strict_types=1);

/**
 * Envía una respuesta JSON y detiene la ejecución.
 */
function jsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Envía una respuesta de error estándar en JSON.
 */
function jsonError(string $message, int $status = 400): never
{
    jsonResponse(['ok' => false, 'error' => $message], $status);
}

/**
 * Valida que el método HTTP coincida con alguno de los permitidos.
 */
function requireMethod(string ...$allowed): string
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $allowedUpper = array_map('strtoupper', $allowed);
    if (!in_array($method, $allowedUpper, true)) {
        header('Allow: ' . implode(', ', $allowedUpper));
        jsonError('Método HTTP no permitido.', 405);
    }
    return $method;
}

/**
 * Lee el cuerpo JSON de la petición o usa $_POST como respaldo.
 */
function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return $_POST;
    }

    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        jsonError('Cuerpo JSON inválido.', 400);
    }
    return $data;
}
