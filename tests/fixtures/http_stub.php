<?php

declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$body = file_get_contents('php://input');

header('Content-Type: application/json');
http_response_code(200);

echo json_encode([
    'method' => $method,
    'path' => $path,
    'body' => $body,
    'headers' => [
        'X-Test' => $_SERVER['HTTP_X_TEST'] ?? null,
        'Content-Type' => $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? null),
    ],
]);
