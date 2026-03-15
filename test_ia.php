<?php
// ARCHIVO DE DIAGNÓSTICO — ELIMINAR DESPUÉS DE USAR
// Subir a: /home/brixs/public_html/sistema.brixs.cloud/test_ia.php
// Abrir en: https://sistema.brixs.cloud/test_ia.php

// Cargar .env manualmente
$env = [];
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v, " \t\"'");
    }
}

$key   = $env['OPENROUTER_KEY'] ?? 'NO ENCONTRADA';
$model = $env['OPENROUTER_MODEL'] ?? 'NO ENCONTRADO';

echo "<pre>";
echo "KEY encontrada: " . (strpos($key, 'sk-or') === 0 ? substr($key,0,20)."..." : "❌ MAL: $key") . "\n";
echo "MODEL: $model\n\n";

if (strpos($key, 'sk-or') !== 0) {
    echo "❌ La API key no está bien configurada en .env\n";
    exit;
}

// Test directo a OpenRouter
$payload = json_encode([
    'model'    => $model,
    'messages' => [
        ['role' => 'user', 'content' => 'Responde solo con este JSON exacto: {"test":"ok","estado":"funcionando"}']
    ],
    'max_tokens'  => 100,
    'temperature' => 0,
], JSON_UNESCAPED_UNICODE);

echo "Payload enviado:\n$payload\n\n";

$ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $key,
        'HTTP-Referer: https://sistema.brixs.cloud',
        'X-Title: Test',
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($curlError) echo "CURL Error: $curlError\n";
echo "Respuesta:\n$response\n";
echo "</pre>";