<?php

declare(strict_types=1);

/** @var string $baseUrl */
$ch = curl_init($baseUrl . '/get');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Test: get-header']);
$response = curl_exec($ch);
curl_close($ch);

return $response;
