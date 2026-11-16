<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth_guard.php';

requireMethod('POST');

cerrarSesionActual();
jsonResponse(['ok' => true, 'mensaje' => 'Sesión cerrada.']);
