<?php

declare(strict_types=1);

/** @var string $baseUrl */
$ch = curl_init($baseUrl . '/delete');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Test: delete-header']);
$response = curl_exec($ch);
curl_close($ch);

return $response;
