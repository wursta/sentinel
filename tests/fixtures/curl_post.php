<?php

declare(strict_types=1);

/** @var string $baseUrl */
$ch = curl_init($baseUrl . '/post');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Test: post-header'],
    CURLOPT_POSTFIELDS => '{"hello":"world"}',
]);
$response = curl_exec($ch);
curl_close($ch);

return $response;
