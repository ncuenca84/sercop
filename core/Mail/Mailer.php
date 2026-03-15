<?php

declare(strict_types=1);

/**
 * Mailer — Envío de correos via SMTP usando sockets PHP puro
 * Compatible con cPanel SMTP sin dependencias externas
 */
class Mailer
{
    // ── Enviar correo simple ──────────────────────────────────────────────
    public static function send(string $to, string $subject, string $htmlBody, array $options = []): bool
    {
        try {
            // Usar mail() de PHP si no hay SMTP configurado (cPanel lo gestiona)
            if (empty(MAIL_HOST) || MAIL_HOST === 'localhost') {
                return self::sendWithMailFunction($to, $subject, $htmlBody, $options);
            }
            return self::sendWithSmtp($to, $subject, $htmlBody, $options);
        } catch (\Throwable $e) {
            error_log('[MAILER ERROR] ' . $e->getMessage());
            return false;
        }
    }

    // ── mail() de PHP (cPanel básico) ─────────────────────────────────────
    private static function sendWithMailFunction(string $to, string $subject, string $html, array $opts): bool
    {
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
        $headers .= "Reply-To: " . MAIL_FROM . "\r\n";
        $headers .= "X-Mailer: ContratosPúblicosEC/1.0\r\n";
        return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers);
    }

    // ── SMTP directo ──────────────────────────────────────────────────────
    private static function sendWithSmtp(string $to, string $subject, string $html, array $opts): bool
    {
        $host = (MAIL_ENCRYPTION === 'ssl' ? 'ssl://' : '') . MAIL_HOST;
        $sock = @fsockopen($host, MAIL_PORT, $errno, $errstr, 10);
        if (!$sock) throw new \RuntimeException("SMTP connect failed: {$errstr}");

        $read = fn() => fgets($sock, 512);
        $send = function(string $cmd) use ($sock, $read): string {
            fwrite($sock, $cmd . "\r\n");
            return $read();
        };

        $read(); // Greeting
        $send('EHLO ' . gethostname());

        if (MAIL_ENCRYPTION === 'tls') {
            $send('STARTTLS');
            stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $send('EHLO ' . gethostname());
        }

        if (MAIL_USER) {
            $send('AUTH LOGIN');
            $send(base64_encode(MAIL_USER));
            $send(base64_encode(MAIL_PASS));
        }

        $send('MAIL FROM: <' . MAIL_FROM . '>');
        $send('RCPT TO: <' . $to . '>');
        $send('DATA');

        $boundary = md5(uniqid());
        $msg  = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
        $msg .= "To: {$to}\r\n";
        $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
        $msg .= "Content-Transfer-Encoding: base64\r\n";
        $msg .= "\r\n";
        $msg .= chunk_split(base64_encode($html));
        $msg .= "\r\n.";
        $send($msg);
        $send('QUIT');
        fclose($sock);
        return true;
    }

    // ── Templates de correo ───────────────────────────────────────────────
    public static function template(string $titulo, string $cuerpo, string $btnTexto = '', string $btnUrl = ''): string
    {
        $btn = $btnTexto ? "<div style='text-align:center;margin:30px 0'>
            <a href='{$btnUrl}' style='background:#1B4F72;color:#fff;padding:12px 28px;
            border-radius:6px;text-decoration:none;font-weight:bold;font-size:15px'>{$btnTexto}</a></div>" : '';

        return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head>
        <body style='font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:20px'>
        <div style='max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.1)'>
          <div style='background:#1B4F72;padding:25px 30px'>
            <h1 style='color:#fff;margin:0;font-size:22px'>" . APP_NAME . "</h1>
          </div>
          <div style='padding:30px'>
            <h2 style='color:#1B4F72;margin-top:0'>{$titulo}</h2>
            {$cuerpo}
            {$btn}
          </div>
          <div style='background:#f8f9fa;padding:15px 30px;font-size:12px;color:#666;text-align:center'>
            Este es un mensaje automático. Por favor no responda a este correo.<br>
            &copy; " . date('Y') . " " . APP_NAME . " — Sistema de Contratación Pública Ecuador
          </div>
        </div></body></html>";
    }

    // ── Notificaciones específicas ────────────────────────────────────────
    public static function notifyDocumentoPorVencer(string $to, string $nombre,
                                                     string $tipoDoc, int $diasRestantes): bool
    {
        $cuerpo = "<p>Estimado/a <strong>{$nombre}</strong>,</p>
        <p>Le informamos que su documento <strong>{$tipoDoc}</strong> vencerá en 
        <strong style='color:#e74c3c'>{$diasRestantes} días</strong>.</p>
        <p>Por favor renueve este documento a la brevedad para evitar inconvenientes 
        en sus procesos de contratación pública.</p>";

        return self::send($to, "⚠️ Documento por vencer: {$tipoDoc}",
            self::template("Documento Próximo a Vencer", $cuerpo, 'Ver mis documentos', APP_URL . '/documentos-habilitantes'));
    }

    public static function notifyPagoPendiente(string $to, string $nombre,
                                                string $proceso, float $monto, int $diasTranscurridos): bool
    {
        $cuerpo = "<p>Estimado/a <strong>{$nombre}</strong>,</p>
        <p>La factura del proceso <strong>{$proceso}</strong> por valor de 
        <strong>$" . number_format($monto, 2) . "</strong> lleva 
        <strong style='color:#e74c3c'>{$diasTranscurridos} días</strong> sin ser cancelada.</p>
        <p>Revise el estado del pago en el sistema.</p>";

        return self::send($to, "💰 Pago pendiente: {$proceso}",
            self::template("Pago Pendiente de Cobro", $cuerpo, 'Ver factura', APP_URL . '/facturas'));
    }

    public static function notifyEntregaProxima(string $to, string $nombre,
                                                  string $proceso, string $entregable, int $diasRestantes): bool
    {
        $cuerpo = "<p>Estimado/a <strong>{$nombre}</strong>,</p>
        <p>El entregable <strong>{$entregable}</strong> del proceso <strong>{$proceso}</strong> 
        debe entregarse en <strong style='color:#e67e22'>{$diasRestantes} días</strong>.</p>
        <p>Asegúrese de tener toda la documentación lista.</p>";

        return self::send($to, "📋 Entrega próxima: {$entregable}",
            self::template("Recordatorio de Entrega", $cuerpo, 'Ver proceso', APP_URL . '/procesos'));
    }
}
