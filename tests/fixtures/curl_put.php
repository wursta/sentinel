<?php

declare(strict_types=1);

/** @var string $baseUrl */
$ch = curl_init($baseUrl . '/put');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: text/plain', 'X-Test: put-header']);
curl_setopt($ch, CURLOPT_POSTFIELDS, 'put-body');
$response = curl_exec($ch);
curl_close($ch);

return $response;
