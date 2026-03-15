<?php
/**
 * DominioRdapService — v4 WHOIS
 *
 * El servidor no puede conectar a rdap.nic.ec (WAF bloquea IP del hosting).
 * SOLUCIÓN: WHOIS via socket TCP puerto 43 — funciona perfectamente.
 *
 * Servidores WHOIS:
 *   - .ec → whois.nic.ec (200.12.196.35)
 *   - .com/.net/.org → whois.verisign-grs.com
 *   - .io → whois.nic.io
 *   - fallback → whois.iana.org (para encontrar el servidor correcto)
 */
class DominioRdapService
{
    private const TIMEOUT = 12;

    // Mapa TLD → servidor WHOIS
    private const WHOIS_SERVERS = [
        'ec'   => 'whois.nic.ec',
        'com'  => 'whois.verisign-grs.com',
        'net'  => 'whois.verisign-grs.com',
        'org'  => 'whois.pir.org',
        'io'   => 'whois.nic.io',
        'co'   => 'whois.nic.co',
        'info' => 'whois.afilias.net',
        'biz'  => 'whois.biz',
        'us'   => 'whois.nic.us',
        'pe'   => 'kero.yachay.pe',
        'co'   => 'whois.nic.co',
    ];

    /**
     * Consulta WHOIS y devuelve array normalizado
     */
    public static function consultar(string $dominioCompleto): ?array
    {
        $dominioCompleto = strtolower(trim($dominioCompleto));
        if (empty($dominioCompleto)) return null;

        $tld    = self::extraerTld($dominioCompleto);
        $server = self::WHOIS_SERVERS[$tld] ?? null;

        // Si no tenemos servidor conocido, preguntar a IANA
        if (!$server) {
            $server = self::buscarServidorWhois($tld);
        }
        if (!$server) return null;

        $raw = self::consultarWhois($server, $dominioCompleto);
        if (!$raw) return null;

        return self::parsearWhois($raw, $tld);
    }

    /**
     * Extrae el TLD de un dominio
     * cocaprode.gob.ec → ec
     * miempresa.com → com
     */
    private static function extraerTld(string $dominio): string
    {
        $partes = explode('.', $dominio);
        return end($partes);
    }

    /**
     * Consulta WHOIS via socket TCP puerto 43
     */
    private static function consultarWhois(string $server, string $dominio): ?string
    {
        // Resolver IP del servidor WHOIS
        $ip = gethostbyname($server);
        if ($ip === $server) {
            // DNS no resolvió — intentar con IP conocida de whois.nic.ec
            if ($server === 'whois.nic.ec') {
                $ip = '200.12.196.35';
            } else {
                return null;
            }
        }

        $fp = @fsockopen($ip, 43, $errno, $errstr, self::TIMEOUT);
        if (!$fp) return null;

        stream_set_timeout($fp, self::TIMEOUT);
        fwrite($fp, "{$dominio}\r\n");

        $response = '';
        while (!feof($fp)) {
            $line = fgets($fp, 1024);
            if ($line === false) break;
            $response .= $line;
        }
        fclose($fp);

        return empty($response) ? null : $response;
    }

    /**
     * Consulta IANA para encontrar el servidor WHOIS de un TLD desconocido
     */
    private static function buscarServidorWhois(string $tld): ?string
    {
        $raw = self::consultarWhois('whois.iana.org', $tld);
        if (!$raw) return null;

        // Buscar "whois:" en la respuesta de IANA
        if (preg_match('/whois:\s+(\S+)/i', $raw, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Parsea la respuesta WHOIS y extrae campos relevantes
     */
    public static function parsearWhois(string $raw, string $tld = ''): array
    {
        $r = [
            'fecha_registro'      => null,
            'fecha_caducidad'     => null,
            'fecha_ultimo_cambio' => null,
            'estado_rdap'         => null,
            'titular'             => null,
            'registrador'         => null,
            'nameservers'         => [],
            'rdap_raw'            => substr($raw, 0, 8000),
        ];

        $lines = explode("\n", $raw);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '%' || $line[0] === '#') continue;

            // Separar campo: valor
            if (!str_contains($line, ':')) continue;
            [$key, $val] = array_map('trim', explode(':', $line, 2));
            $key = strtolower($key);
            $val = trim($val);
            if (empty($val)) continue;

            // Fechas — pueden venir en varios formatos
            switch ($key) {
                case 'creation date':
                case 'created':
                case 'domain registered':
                    $r['fecha_registro'] = self::parsearFecha($val);
                    break;

                case 'registry expiry date':
                case 'registrar registration expiration date':
                case 'expiry date':
                case 'expires':
                case 'expire':
                case 'expiration date':
                    $r['fecha_caducidad'] = self::parsearFecha($val);
                    break;

                case 'updated date':
                case 'last updated':
                case 'last-update':
                case 'changed':
                    $r['fecha_ultimo_cambio'] = self::parsearFecha($val);
                    break;

                case 'domain status':
                    if ($r['estado_rdap']) {
                        $r['estado_rdap'] .= ', ' . strtok($val, ' ');
                    } else {
                        $r['estado_rdap'] = strtok($val, ' '); // Solo el código, sin URL
                    }
                    break;

                case 'registrant':
                case 'registrant name':
                case 'registrant organization':
                    if (empty($r['titular'])) $r['titular'] = $val;
                    break;

                case 'registrar':
                    $r['registrador'] = $val;
                    break;

                case 'name server':
                case 'nserver':
                    $ns = strtolower(strtok($val, ' '));
                    if ($ns && !in_array($ns, $r['nameservers'])) {
                        $r['nameservers'][] = $ns;
                    }
                    break;
            }
        }

        return $r;
    }

    /**
     * Convierte fechas WHOIS a formato YYYY-MM-DD
     * Formatos comunes: 2026-12-04T19:12:39Z, 04/12/2026, 2026-12-04
     */
    private static function parsearFecha(string $val): ?string
    {
        $val = trim($val);
        if (empty($val)) return null;

        // ISO 8601: 2026-12-04T19:12:39Z
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $val, $m)) {
            return $m[1];
        }

        // DD/MM/YYYY
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $val, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        // Intentar strtotime como fallback
        $ts = strtotime($val);
        if ($ts && $ts > 0) {
            return date('Y-m-d', $ts);
        }

        return null;
    }

    public static function calcularEstado(?string $fecha, int $dias = 30): string
    {
        if (empty($fecha)) return 'activo';
        $hoy  = new \DateTime();
        $vcto = new \DateTime($fecha);
        $diff = (int)$hoy->diff($vcto)->format('%r%a');
        if ($diff < 0)      return 'vencido';
        if ($diff <= $dias) return 'por_vencer';
        return 'activo';
    }

    public static function diasHastaVencimiento(?string $fecha): ?int
    {
        if (empty($fecha)) return null;
        return (int)(new \DateTime())->diff(new \DateTime($fecha))->format('%r%a');
    }

    /**
     * Diagnóstico
     */
    public static function diagnostico(string $dominio = 'cocaprode.gob.ec'): array
    {
        $tld    = self::extraerTld($dominio);
        $server = self::WHOIS_SERVERS[$tld] ?? 'whois.iana.org';
        $ip     = gethostbyname($server);
        $raw    = self::consultarWhois($server, $dominio);
        $parsed = $raw ? self::parsearWhois($raw, $tld) : null;

        return [
            'dominio'       => $dominio,
            'tld'           => $tld,
            'whois_server'  => $server,
            'whois_ip'      => $ip,
            'whois_ok'      => !empty($raw),
            'whois_raw'     => substr($raw ?? '', 0, 1000),
            'parsed'        => $parsed,
        ];
    }
}