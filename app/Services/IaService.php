<?php

declare(strict_types=1);

class IaService
{
    private static string $systemPrompt = <<<PROMPT
Eres un experto en contratación pública ecuatoriana bajo la LOSNCP (Ley Orgánica del Sistema Nacional de Contratación Pública) y su Reglamento.

Analiza el documento proporcionado (puede ser un TDR, pliego, orden de compra, especificaciones técnicas u otro documento de contratación pública ecuatoriana) y extrae TODA la información relevante.

Responde ÚNICAMENTE con un objeto JSON válido con exactamente estos campos (sin texto adicional, sin markdown, sin explicaciones):

{
  "institucion_contratante": "nombre completo oficial de la entidad pública",
  "ruc_institucion": "RUC si aparece, sino null",
  "objeto_contratacion": "descripción completa del bien o servicio",
  "tipo_proceso": "infima_cuantia|catalogo|subasta|licitacion|menor_cuantia|contratacion_directa|otro",
  "monto_total": número_decimal_sin_signo,
  "plazo_dias": número_entero_de_días,
  "fecha_inicio": "YYYY-MM-DD o null",
  "administrador_contrato": "nombre completo del administrador del contrato",
  "cargo_administrador": "cargo del administrador",
  "email_administrador": "email si aparece, sino null",
  "forma_de_pago": "descripción de la forma de pago",
  "penalidades": "descripción de multas o penalidades por incumplimiento",
  "entregables": [
    {"numero": 1, "descripcion": "descripción del entregable", "plazo_dias": número}
  ],
  "requisitos_tecnicos": ["requisito 1", "requisito 2"],
  "documentos_requeridos": ["documento 1", "documento 2"],
  "garantias_requeridas": ["garantía 1"],
  "resumen_ejecutivo": "resumen breve del contrato en 2-3 oraciones"
}

Si un campo no está en el documento, usa null para strings y 0 para números.
PROMPT;

    // ── Analizar documento ────────────────────────────────────────────────
    public static function analizarDocumento(string $textoDocumento, string $tipoDoc = 'tdr'): array
    {
        if (empty(OPENROUTER_KEY)) {
            throw new \RuntimeException('OpenRouter API Key no configurada. Configure OPENROUTER_KEY en su archivo .env');
        }

        $payload = json_encode([
            'model'       => OPENROUTER_MODEL,
            'messages'    => [
                ['role' => 'system', 'content' => self::$systemPrompt],
                ['role' => 'user',   'content' => "Analiza este documento de contratación pública y responde SOLO con el JSON, sin texto adicional, sin markdown, sin bloques de código:\n\n" . substr($textoDocumento, 0, 15000)],
            ],
            'temperature' => 0.1,
            'max_tokens'  => 2000,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init(OPENROUTER_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . OPENROUTER_KEY,
                'HTTP-Referer: ' . APP_URL,
                'X-Title: ' . APP_NAME,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) throw new \RuntimeException("Error cURL: {$curlError}");
        if ($httpCode !== 200) throw new \RuntimeException("OpenRouter error HTTP {$httpCode}: {$response}");

        $decoded = json_decode($response, true);
        if (!isset($decoded['choices'][0]['message']['content'])) {
            throw new \RuntimeException("Respuesta inesperada de OpenRouter: " . substr($response, 0, 300));
        }

        $content = $decoded['choices'][0]['message']['content'];

        // Limpiar bloques markdown que algunos modelos añaden (```json ... ```)
        $content = preg_replace('/^```(?:json)?\s*/i', '', trim($content));
        $content = preg_replace('/\s*```$/', '', $content);
        $content = trim($content);

        $data = json_decode($content, true);

        if (!$data) {
            // Intentar extraer JSON del texto si viene con explicación
            preg_match('/\{.*\}/s', $content, $matches);
            if (!empty($matches[0])) {
                $data = json_decode($matches[0], true);
            }
        }

        if (!$data) {
            throw new \RuntimeException("La IA no retornó JSON válido. Respuesta: " . substr($content, 0, 200));
        }

        return [
            'datos'         => $data,
            'tokens_usados' => $decoded['usage']['total_tokens'] ?? 0,
            'modelo'        => $decoded['model'] ?? OPENROUTER_MODEL,
        ];
    }

    // ── Extraer texto de PDF (si tiene pdftotext en el servidor) ──────────
    public static function extractText(string $filePath): string
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($ext === 'pdf') {
            // Intentar con pdftotext si está disponible en el servidor
            if (shell_exec('which pdftotext 2>/dev/null')) {
                $tmp = tempnam(sys_get_temp_dir(), 'pdf_');
                shell_exec("pdftotext " . escapeshellarg($filePath) . " " . escapeshellarg($tmp) . " 2>/dev/null");
                if (file_exists($tmp)) {
                    $text = file_get_contents($tmp);
                    unlink($tmp);
                    if ($text) return $text;
                }
            }
            // Fallback: leer bytes del PDF (limitado pero funcional)
            return self::extractTextFromPdfBytes(file_get_contents($filePath));
        }

        if (in_array($ext, ['txt', 'html', 'htm'])) {
            return strip_tags(file_get_contents($filePath));
        }

        return file_get_contents($filePath);
    }

    // ── Extracción básica de texto de PDF sin librerías ───────────────────
    private static function extractTextFromPdfBytes(string $content): string
    {
        preg_match_all('/BT\s*(.*?)\s*ET/s', $content, $matches);
        $text = '';
        foreach ($matches[1] as $block) {
            preg_match_all('/\((.*?)\)\s*Tj/', $block, $strings);
            $text .= implode(' ', $strings[1]) . "\n";
        }
        return $text ?: '(No se pudo extraer texto del PDF. Copie el texto manualmente.)';
    }

    // ── Generar texto de documento con IA ─────────────────────────────────
    public static function generarTextoDocumento(string $tipoDoc, array $datosProceso): string
    {
        if (empty(OPENROUTER_KEY)) return '';

        $prompts = [
            'informe_tecnico' => "Redacta un informe técnico profesional de entrega para contratación pública en Ecuador. Usa los siguientes datos del proceso: " . json_encode($datosProceso, JSON_UNESCAPED_UNICODE) . ". El informe debe incluir: antecedentes, objetivo, descripción de trabajos realizados, cumplimiento de especificaciones técnicas, conclusiones y recomendaciones. Formato HTML limpio.",
            'acta_entrega'    => "Redacta un acta de entrega-recepción provisional para contratación pública en Ecuador con estos datos: " . json_encode($datosProceso, JSON_UNESCAPED_UNICODE) . ". Incluye: datos de las partes, objeto, entregables, condiciones, firmas. Formato HTML.",
            'garantia'        => "Redacta un certificado de garantía técnica para Ecuador con estos datos: " . json_encode($datosProceso, JSON_UNESCAPED_UNICODE) . ". Incluye: alcance, vigencia, exclusiones, condiciones. Formato HTML profesional.",
        ];

        $prompt = $prompts[$tipoDoc] ?? "Redacta el documento {$tipoDoc} con estos datos: " . json_encode($datosProceso);

        $payload = json_encode([
            'model'       => OPENROUTER_MODEL,
            'messages'    => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.3,
            'max_tokens'  => 2500,
        ]);

        $ch = curl_init(OPENROUTER_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . OPENROUTER_KEY, 'HTTP-Referer: ' . APP_URL],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode($response, true);
        return $decoded['choices'][0]['message']['content'] ?? '';
    }
}
