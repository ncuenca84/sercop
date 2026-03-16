<?php

class PdfExtractorService
{
    // ─────────────────────────────────────────────
    //  EXTRACCIÓN DESDE HTML SERCOP
    // ─────────────────────────────────────────────

    private static function parsearHtmlSercop(string $html): array
    {
        $h  = $html;
        $hd = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        /* ── helper: busca el <p class="card-text"> después de un <strong>label</strong> ── */
        $cardText = function (string $label) use ($h, $hd): string {
            $q = preg_quote($label, '/');
            foreach ([$h, $hd] as $src) {
                // Patrón A: strong → tags intermedios → p.card-text
                if (preg_match(
                    '/' . $q . '[^<]*<\/strong>(?:\s*<[^>]*>)*\s*<p[^>]*>\s*([^<]{2,800})\s*<\/p>/si',
                    $src, $m
                )) return trim($m[1]);

                // Patrón B: strong → texto directo (\s* NO \s+, sin requerir mayúscula)
                if (preg_match(
                    '/' . $q . '[^<]*<\/strong>\s*([^\s<][^<]{2,500})/si',
                    $src, $m
                )) return trim($m[1]);
            }
            return '';
        };

        $datos = [];

        // Número de proceso — primero del breadcrumb (estructura real SERCOP)
        if (preg_match('/\bNIC-[\d\w]+-[\d\w]+-[\d\w]+\b/', $hd, $m))
            $datos['numero_proceso'] = $m[0];
        if (empty($datos['numero_proceso']))
            $datos['numero_proceso'] = $cardText('Código Necesidad de Contratación')
                ?: $cardText('Código del Proceso')
                ?: $cardText('Número del Proceso');

        // RUC desde NIC
        if (preg_match('/NIC-(\d{13})-/', $datos['numero_proceso'] ?? '', $m))
            $datos['ruc_institucion'] = $m[1];

        // Institución contratante
        $datos['institucion_contratante'] = $cardText('Nombre Entidad')
            ?: $cardText('Entidad Contratante')
            ?: $cardText('Nombre de la Entidad');

        // Objeto de contratación
        $datos['objeto_contratacion'] = $cardText('Objeto de compra')
            ?: $cardText('Objeto de Contratación')
            ?: $cardText('Descripción del objeto');

        // Tipo de proceso
        $datos['tipo_proceso'] = $cardText('Tipo de Compra')
            ?: $cardText('Tipo de Proceso')
            ?: $cardText('Procedimiento');

        // CPC — viene en tabla: <th>CPC</th> ... <td>CODIGO</td><td>DESCRIPCION</td>
        if (preg_match('/<th[^>]*>CPC<\/th>.*?<td[^>]*>\s*(\d{5,12})\s*<\/td>\s*<td[^>]*>\s*([^<]{5,500})\s*<\/td>/si', $hd, $m)) {
            $datos['cpc']             = trim($m[1]);
            $datos['cpc_descripcion'] = trim($m[2]);
        } elseif (preg_match('/<td[^>]*>\s*(\d{6,12})\s*<\/td>\s*<td[^>]*>\s*(SERVICIOS?|BIENES?|OBRAS?|SUMINISTROS?[^<]{5,400})\s*<\/td>/si', $hd, $m)) {
            $datos['cpc']             = trim($m[1]);
            $datos['cpc_descripcion'] = trim($m[2]);
        } else {
            $datos['cpc'] = $cardText('CPC') ?: $cardText('Código CPC') ?: $cardText('Clasificador');
        }

        // Forma de pago — viene en tabla después de <h2>Forma de Pago</h2>
        if (preg_match('/Forma\s+de\s+Pago.*?<td[^>]*>\s*([^<]{10,800})\s*<\/td>/si', $hd, $m))
            $datos['forma_pago'] = trim($m[1]);

        // Presupuesto referencial
        $raw = $cardText('Presupuesto Referencial')
            ?: $cardText('Monto')
            ?: $cardText('Valor');
        if ($raw) {
            $datos['monto_total'] = (float) preg_replace('/[^\d.]/', '', str_replace(',', '.', $raw));
        }

        // Plazo
        $plazoBruto = $cardText('Plazo de Ejecución')
            ?: $cardText('Plazo')
            ?: $cardText('Tiempo de ejecución');
        if ($plazoBruto && preg_match('/(\d+)/', $plazoBruto, $m))
            $datos['plazo_dias'] = (int)$m[1];

        // Fecha límite proforma
        $datos['fecha_limite_proforma'] = $cardText('Fecha Límite para la entrega de Proformas')
            ?: $cardText('Fecha límite')
            ?: $cardText('Fecha de cierre');
        if ($datos['fecha_limite_proforma']) {
            // Normalizar a Y-m-d H:i:s si viene con formato raro
            $datos['fecha_limite_proforma'] = preg_replace('/(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}).*/', '$1 $2:00', $datos['fecha_limite_proforma']);
        }

        // Funcionario encargado
        if (preg_match('/Funcionario\s+Encargado.*?<strong>Nombre:\s*<\/strong>\s*([^<]{5,200})/si', $h, $m))
            $datos['funcionario'] = trim($m[1]);
        elseif (preg_match('/<strong>Nombre:\s*<\/strong>\s*([^<]{5,200})/si', $h, $m))
            $datos['funcionario'] = trim($m[1]);
        else
            $datos['funcionario'] = $cardText('Nombre del Funcionario')
                ?: $cardText('Responsable');

        // Correo contacto
        $datos['correo_contacto'] = $cardText('Correo Electrónico')
            ?: $cardText('Email')
            ?: $cardText('Correo');

        // Dirección / ciudad
        $datos['direccion'] = $cardText('Dirección') ?: $cardText('Ubicación');
        $datos['canton']    = $cardText('Cantón')    ?: $cardText('Ciudad');
        $datos['provincia'] = $cardText('Provincia');

        // ── ÍTEMS: tabla "Detalle del objeto de compra" ────────────────────
        // Estructura: <td>No</td><td>CPC_COD</td><td>CPC_DESC</td><td>DESC_PRODUCTO</td><td>UNIDAD</td><td>CANTIDAD</td>
        $datos['items'] = self::extraerItemsTabla($hd);

        // Limpiar: trim solo en valores string, preservar arrays y nulls
        $resultado = [];
        foreach ($datos as $k => $v) {
            if (is_string($v)) $resultado[$k] = trim($v);
            elseif ($v !== null && $v !== '') $resultado[$k] = $v;
        }
        return $resultado;
    }

    /**
     * Extrae los ítems de la tabla de detalle SERCOP.
     * Retorna array de arrays con: numero, cpc, cpc_descripcion, descripcion, unidad, cantidad
     */
    private static function extraerItemsTabla(string $hd): array
    {
        $items = [];

        // Buscar el bloque <tbody> dentro de la tabla de ítems
        // La tabla tiene thead con columnas: No. | CPC (colspan=2) | Descripción | Unidad | Cantidad
        // Las filas de datos son: <td>N</td><td>CPC_COD</td><td>CPC_DESC</td><td>DESC</td><td>UNIDAD</td><td>CANTIDAD</td>

        // Capturar todos los <tr> del tbody principal con 6 columnas
        if (!preg_match_all(
            '/<tr[^>]*>\s*<td[^>]*>\s*(\d+)\s*<\/td>\s*' .   // No.
            '<td[^>]*>\s*(\d{5,12})\s*<\/td>\s*' .             // CPC código
            '<td[^>]*>\s*([^<]{2,200})\s*<\/td>\s*' .          // CPC descripción
            '<td[^>]*>\s*([\s\S]{2,2000}?)\s*<\/td>\s*' .      // Descripción producto (multiline)
            '<td[^>]*>\s*([^<]{2,50})\s*<\/td>\s*' .           // Unidad
            '<td[^>]*>\s*([\d.,]+)\s*<\/td>/si',
            $hd, $matches, PREG_SET_ORDER
        )) {
            return $items;
        }

        foreach ($matches as $m) {
            // Limpiar descripción: quitar tags HTML, normalizar espacios
            $descripcion = strip_tags($m[4]);
            $descripcion = preg_replace('/\s{2,}/', "\n", trim($descripcion));

            $cantidad = (float) str_replace(',', '.', preg_replace('/[^\d.,]/', '', $m[6]));

            $items[] = [
                'numero'          => (int) $m[1],
                'cpc'             => trim($m[2]),
                'cpc_descripcion' => trim(strip_tags($m[3])),
                'descripcion'     => trim($descripcion),
                'unidad'          => trim(strip_tags($m[5])),
                'cantidad'        => $cantidad,
                'precio_unitario' => 0.00,
                'precio_total'    => 0.00,
            ];
        }

        return $items;
    }

    /**
     * Wrapper público para el controller — retorna ['datos'=>[...], 'metodo'=>'...', 'aviso'=>'...']
     */
    public static function extraerDeHtml(string $html, string $url = ''): array
    {
        $datos = self::parsearHtmlSercop($html);
        return [
            'datos'  => $datos,
            'metodo' => 'sercop_html',
            'aviso'  => null,
        ];
    }

    /**
     * Fetch desde URL del SERCOP y extrae datos.
     * Lanza RuntimeException con 'SERCOP_BLOCKED' si el servidor no puede conectarse.
     */
    public static function extraerDeUrl(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; BrixsBot/1.0)',
            CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml'],
        ]);
        $html      = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || !$html) {
            throw new \RuntimeException('SERCOP_BLOCKED: ' . $curlError);
        }
        if ($httpCode !== 200) {
            throw new \RuntimeException('SERCOP_BLOCKED: HTTP ' . $httpCode);
        }

        $datos = self::parsearHtmlSercop($html);
        return [
            'datos'  => $datos,
            'metodo' => 'sercop_url',
            'aviso'  => null,
        ];
    }

    // ─────────────────────────────────────────────
    //  EXTRACCIÓN DESDE PDF TDR
    // ─────────────────────────────────────────────

    /**
     * Punto de entrada principal.
     * Devuelve array con claves:
     *   monto_total, plazo_dias, especificaciones_tecnicas, metodologia_trabajo,
     *   cpc_descripcion, plazo_texto, forma_pago, vigencia_oferta, declaracion_cumplimiento
     */
    public static function extraerDatos(string $rutaPdf): array
    {
        $resultado = [
            'numero_proceso'            => null,
            'ruc_institucion'           => null,
            'cpc'                       => null,
            'monto_total'               => null,
            'plazo_dias'                => null,
            'especificaciones_tecnicas' => null,
            'metodologia_trabajo'       => null,
            'cpc_descripcion'           => null,
            'plazo_texto'               => null,
            'forma_pago'                => null,
            'vigencia_oferta'           => null,
            'declaracion_cumplimiento'  => null,
        ];

        // ── 1. Texto con smalot/pdfparser (lineal) ─────────────────────
        $textoPrincipal = self::extraerConSmalot($rutaPdf);

        // ── 1b. Texto posicional smalot (mejor para tablas) ─────────────
        $textoPosicional = self::extraerConSmalotPositional($rutaPdf);

        // ── 2. Texto con pdftotext -layout (mejor para tablas) ─────────
        $textoLayout = self::extraerConPdftotext($rutaPdf);

        // Elegir el candidato más legible de los disponibles
        $candidatos = array_filter(
            [$textoLayout, $textoPosicional, $textoPrincipal],
            fn($t) => mb_strlen(trim($t)) > 50
        );
        $texto = empty($candidatos)
            ? ''
            : array_reduce(
                array_slice($candidatos, 1),
                fn($mejor, $t) => self::elegirMejorTexto($mejor, $t),
                reset($candidatos)
            );

        // ── 3. Extraer campos con regex ─────────────────────────────────
        $resultado['numero_proceso']            = self::extraerNumeroProceso($texto);
        $resultado['ruc_institucion']           = self::extraerRucDesdeNic($resultado['numero_proceso']);
        $resultado['monto_total']               = self::extraerMonto($texto);
        $resultado['plazo_dias']                = self::extraerPlazo($texto);
        $resultado['plazo_texto']               = self::extraerPlazoTexto($texto);
        $resultado['vigencia_oferta']           = self::extraerVigencia($texto);
        $cpcCompleto                            = self::extraerCpc($texto);
        $resultado['cpc_descripcion']           = $cpcCompleto;
        // Separar solo el código numérico CPC
        if ($cpcCompleto && preg_match('/^(\d{5,12})/m', $cpcCompleto, $mc))
            $resultado['cpc'] = $mc[1];
        $resultado['forma_pago']                = self::extraerFormaPago($texto);
        $resultado['especificaciones_tecnicas'] = self::extraerEspecificaciones($texto);
        $resultado['metodologia_trabajo']       = self::extraerMetodologia($texto);
        $resultado['declaracion_cumplimiento']  = self::extraerDeclaracion($texto);

        // ── 4. Fallback IA si campos críticos vacíos ────────────────────
        $vacios = array_filter($resultado, fn($v) => empty($v));
        if (count($vacios) >= 3 && !empty($texto) && defined('OPENROUTER_KEY') && OPENROUTER_KEY) {
            try {
                $iaResult = IaService::analizarDocumento($texto);
                $iaDatos  = $iaResult['datos'] ?? [];
                // Solo rellenar campos vacíos — nunca sobreescribir lo ya extraído
                $mapeo = [
                    'monto_total'               => 'monto_total',
                    'plazo_dias'                => 'plazo_dias',
                    'forma_pago'                => 'forma_de_pago',
                    'numero_proceso'            => 'numero_proceso',
                    'especificaciones_tecnicas' => 'requisitos_tecnicos',
                ];
                foreach ($mapeo as $campoLocal => $campoIa) {
                    if (empty($resultado[$campoLocal]) && !empty($iaDatos[$campoIa])) {
                        $val = $iaDatos[$campoIa];
                        if (is_array($val)) $val = implode("\n", $val);
                        $resultado[$campoLocal] = $val;
                    }
                }
            } catch (\Exception $e) {
                // IA no disponible — continuar con lo extraído por regex
            }
        }

        return $resultado;
    }

    // ─────────────────────────────────────────────
    //  EXTRACTORES DE TEXTO
    // ─────────────────────────────────────────────

    private static function extraerNumeroProceso(string $texto): ?string
    {
        // Buscar NIC-XXXXXXXXXXXXXXXXX-XXXX-XXXXX (formato SERCOP)
        if (preg_match('/\b(NIC-\d{13}-\d{4}-\d{5})\b/', $texto, $m))
            return $m[1];
        // Formato alternativo con letras
        if (preg_match('/\b(NIC-[\dA-Z]+-[\dA-Z]+-[\dA-Z]+)\b/', $texto, $m))
            return $m[1];
        // Buscar "Código del Proceso" o similar en el texto plano
        if (preg_match('/(?:C[oó]digo del Proceso|N[oú]mero del Proceso|N[°º]\s*Proceso)[:\s]+([A-Z0-9\-]{10,50})/i', $texto, $m))
            return trim($m[1]);
        return null;
    }

    private static function extraerRucDesdeNic(?string $nic): ?string
    {
        if (!$nic) return null;
        // RUC son los 13 dígitos después de "NIC-"
        if (preg_match('/NIC-(\d{13})-/', $nic, $m))
            return $m[1];
        return null;
    }

    /**
     * Extrae texto con Ghostscript — maneja fuentes embebidas con encoding personalizado.
     * Disponible en este servidor como /bin/gs
     */
    private static function extraerConGhostscript(string $ruta): string
    {
        $bins = ['/bin/gs', '/usr/bin/gs', '/usr/local/bin/gs'];
        $gs   = '';
        foreach ($bins as $b) {
            if (is_executable($b)) { $gs = $b; break; }
        }
        if (!$gs) return '';

        $tmp = tempnam(sys_get_temp_dir(), 'gs_') . '.txt';
        $cmd = $gs
             . ' -q -dNOPAUSE -dBATCH -dSAFER'
             . ' -sDEVICE=txtwrite'
             . ' -dTextFormat=2'
             . ' -sOutputFile=' . escapeshellarg($tmp)
             . ' ' . escapeshellarg($ruta)
             . ' 2>/dev/null';
        try {
            exec($cmd, $out, $ret);
            $txt = file_exists($tmp) ? (string) file_get_contents($tmp) : '';
        } finally {
            if (file_exists($tmp)) {
                unlink($tmp);
            }
        }
        return $txt ?: '';
    }

    /**
     * Elige el texto más legible entre dos candidatos.
     * Puntúa según palabras españolas reconocibles y penaliza caracteres raros.
     */
    private static function elegirMejorTexto(string $a, string $b): string
    {
        $scoreA = self::puntuarLegibilidad($a);
        $scoreB = self::puntuarLegibilidad($b);
        return $scoreA >= $scoreB ? $a : $b;
    }

    private static function puntuarLegibilidad(string $texto): int
    {
        $t = trim($texto);
        if (mb_strlen($t) < 20) return 0;

        // Palabras clave comunes en TDR ecuatorianos
        $palabras = ['contrato','servicio','entidad','contratista','plazo','pago',
                     'ANTECEDENTES','OBJETIVOS','ALCANCE','PLAZO','PAGO',
                     ' de ',' la ',' el ',' que ',' los '];
        $score = 0;
        foreach ($palabras as $p) {
            if (mb_strpos($t, $p) !== false) $score += 5;
        }

        // Penalizar chars raros (encoding corrupto)
        $total = mb_strlen($t);
        preg_match_all('/[^	

 -~áéíóúñüÁÉÍÓÚÑÜ]/u', $t, $m);
        $raros = count($m[0]);
        $pct   = $total > 0 ? ($raros / $total) : 0;
        if ($pct > 0.2) $score = (int)($score * 0.2);
        elseif ($pct > 0.05) $score = (int)($score * 0.6);

        $score += min(30, (int)($total / 500));
        return $score;
    }

    private static function extraerConSmalot(string $ruta): string
    {
        try {
            if (!class_exists('\Smalot\PdfParser\Parser')) return '';
            $parser   = new \Smalot\PdfParser\Parser();
            $pdf      = $parser->parseFile($ruta);
            return $pdf->getText();
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Extrae texto con datos posicionales de smalot/pdfparser.
     * Usa coordenadas X/Y (transformation matrix) para reconstruir filas reales,
     * detectar columnas y convertir tablas a texto separado por 3 espacios
     * para que detectarTablaGenerica() pueda procesarlo.
     */
    private static function extraerConSmalotPositional(string $ruta): string
    {
        try {
            if (!class_exists('\Smalot\PdfParser\Parser')) return '';
            $parser = new \Smalot\PdfParser\Parser();
            $pdf    = $parser->parseFile($ruta);

            $textoCompleto = '';

            foreach ($pdf->getPages() as $page) {
                $dataTm = $page->getDataTm();

                if (empty($dataTm)) {
                    $textoCompleto .= $page->getText() . "\n\n";
                    continue;
                }

                // Recolectar (x, y, text) de cada elemento posicional
                $elementos = [];
                foreach ($dataTm as $item) {
                    // Estructura smalot v2: [$matrix, $font, $size, $spacing, $text]
                    if (!isset($item[0], $item[4])) continue;
                    $matrix = $item[0];
                    if (!is_array($matrix) || count($matrix) < 6) continue;
                    $x    = (float) $matrix[4];
                    $y    = (float) $matrix[5];
                    $text = (string) $item[4];
                    if (trim($text) === '') continue;
                    $elementos[] = ['x' => $x, 'y' => $y, 'text' => $text];
                }

                if (empty($elementos)) {
                    $textoCompleto .= $page->getText() . "\n\n";
                    continue;
                }

                // Agrupar por Y redondeado a decena (cada 10 unidades = misma fila)
                $filas = [];
                foreach ($elementos as $el) {
                    $yKey = (string) (round($el['y'] / 10) * 10);
                    $filas[$yKey][] = $el;
                }

                // PDF usa Y desde abajo → ordenar descendente para leer de arriba a abajo
                krsort($filas, SORT_NUMERIC);

                // Decidir si renderizar como tabla o como texto lineal
                if (self::detectarTablaDesdeElementos($filas)) {
                    $textoCompleto .= self::renderizarFilasComoTabla($filas) . "\n\n";
                } else {
                    foreach ($filas as $fila) {
                        usort($fila, fn($a, $b) => $a['x'] <=> $b['x']);
                        $textoCompleto .= implode(' ', array_column($fila, 'text')) . "\n";
                    }
                    $textoCompleto .= "\n";
                }
            }

            return $textoCompleto;

        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Determina si los elementos agrupados por fila forman una tabla.
     * Criterio: ≥3 filas con ≥2 elementos cuyas posiciones X son consistentes.
     */
    private static function detectarTablaDesdeElementos(array $filas): bool
    {
        $filasList = array_values($filas);
        if (count($filasList) < 3) return false;

        $filasConColumnas = array_filter($filasList, fn($f) => count($f) >= 2);
        if (count($filasConColumnas) < max(2, count($filasList) * 0.4)) return false;

        // Verificar que al menos 2 columnas tienen posiciones X consistentes
        $xPorColumna = [];
        foreach (array_slice($filasList, 0, 6) as $fila) {
            usort($fila, fn($a, $b) => $a['x'] <=> $b['x']);
            foreach ($fila as $idx => $el) {
                $xPorColumna[$idx][] = $el['x'];
            }
        }

        $columnasBienAlineadas = 0;
        foreach ($xPorColumna as $xs) {
            if (count($xs) >= 2 && (max($xs) - min($xs)) < 40) {
                $columnasBienAlineadas++;
            }
        }

        return $columnasBienAlineadas >= 2;
    }

    /**
     * Convierte filas posicionales a texto con columnas separadas por 3 espacios,
     * listo para que detectarTablaGenerica() lo convierta a <table> HTML.
     */
    private static function renderizarFilasComoTabla(array $filas): string
    {
        $lineas = [];
        foreach ($filas as $fila) {
            usort($fila, fn($a, $b) => $a['x'] <=> $b['x']);
            $lineas[] = implode('   ', array_column($fila, 'text'));
        }
        return implode("\n", $lineas);
    }

    private static function extraerConPdftotext(string $ruta, bool $layout = true): string
    {
        // Buscar el binario en múltiples rutas
        $bins = ['/usr/bin/pdftotext', '/usr/local/bin/pdftotext'];
        $bin  = '';
        foreach ($bins as $b) {
            if (is_executable($b)) { $bin = $b; break; }
        }
        // Intentar con which si no encontró
        if (!$bin) {
            $which = trim(shell_exec('which pdftotext 2>/dev/null') ?: '');
            if ($which && is_executable($which)) $bin = $which;
        }
        if (!$bin) return '';

        $tmp  = tempnam(sys_get_temp_dir(), 'tdr_');
        $flag = $layout ? '-layout ' : '';
        try {
            exec($bin . ' ' . $flag . escapeshellarg($ruta) . ' ' . escapeshellarg($tmp) . ' 2>/dev/null');
            $txt = file_exists($tmp) ? (string) file_get_contents($tmp) : '';
        } finally {
            if (file_exists($tmp)) {
                unlink($tmp);
            }
        }
        return $txt ?: '';
    }

    // ─────────────────────────────────────────────
    //  EXTRACCIÓN DE MONTO
    // ─────────────────────────────────────────────

    private static function extraerMonto(string $texto): ?float
    {
        $patrones = [
            // VALOR TOTAL / PRESUPUESTO REFERENCIAL con $
            '/(?:VALOR\s+TOTAL|PRESUPUESTO\s+REFERENCIAL|MONTO\s+REFERENCIAL|VALOR\s+REFERENCIAL)[:\s]*\$?\s*([\d]{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)/i',
            // Con símbolo $ antes del número
            '/\$\s*([\d]{1,3}(?:[.,]\d{3})*(?:[.,]\d{2}))/i',
            // USD seguido de número
            '/USD\s*([\d]{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)/i',
            // Número seguido de USD/dólares
            '/([\d]{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)\s*(?:USD|dólares?)/i',
            // Tabla: columna con número grande (>100)
            '/(?:TOTAL|VALOR|MONTO)[^\n]{0,60}\n[^\n]{0,20}([\d]{3,}[.,]\d{2})/i',
        ];

        foreach ($patrones as $p) {
            if (preg_match($p, $texto, $m)) {
                $raw = str_replace([' ', '\xc2\xa0'], '', $m[1]);
                // Normalizar separadores: si tiene coma antes de 2 decimales → punto decimal
                if (preg_match('/,\d{2}$/', $raw))
                    $raw = str_replace(['.', ','], ['', '.'], $raw);
                else
                    $raw = str_replace(',', '', $raw);

                $val = (float) $raw;
                if ($val > 0) return $val;
            }
        }
        return null;
    }

    // ─────────────────────────────────────────────
    //  EXTRACCIÓN DE PLAZO
    // ─────────────────────────────────────────────

    private static function extraerPlazo(string $texto): ?int
    {
        $patrones = [
            // PLAZO: 30 días / (30) días
            '/PLAZO\s+(?:DE\s+)?(?:EJECUCI[ÓO]N|ENTREGA)[:\s]*(?:plazo\s+m[aá]ximo\s+de\s+)?(?:\w+\s+)?\(?(\d+)\)?\s*d[ií]as?/si',
            '/TIEMPO\s+DE\s+ENTREGA[:\s]*(?:.*?)\(?(\d+)\)?\s*d[ií]as?/si',
            '/PLAZO[:\s]+(\d+)\s*d[ií]as?/i',
            '/DURACI[ÓO]N[:\s]+(\d+)\s*d[ií]as?/i',
            // "treinta (30) días"
            '/(?:uno|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|veinte|treinta|cuarenta|cincuenta|sesenta|noventa|cien)\s+\((\d+)\)\s*d[ií]as?/i',
            // número seguido de "días calendario/hábiles"
            '/(\d+)\s+d[ií]as?\s+(?:calendario|h[áa]biles?|laborables?)/i',
        ];

        foreach ($patrones as $p) {
            if (preg_match($p, $texto, $m)) {
                $val = (int) $m[1];
                if ($val > 0 && $val <= 3650) return $val;
            }
        }
        return null;
    }

    // ─────────────────────────────────────────────
    //  EXTRACCIÓN DE VIGENCIA OFERTA
    // ─────────────────────────────────────────────

    private static function extraerVigencia(string $texto): ?string
    {
        $patrones = [
            '/VIGENCIA\s+(?:DE\s+)?(?:LA\s+)?OFERTA[:\s]*([^\n]{5,120})/i',
            '/VALIDEZ\s+(?:DE\s+)?(?:LA\s+)?OFERTA[:\s]*([^\n]{5,120})/i',
            '/OFERTA\s+VÁLIDA[:\s]*([^\n]{5,120})/i',
        ];
        foreach ($patrones as $p) {
            if (preg_match($p, $texto, $m))
                return trim($m[1]);
        }
        return null;
    }

    // ─────────────────────────────────────────────
    //  EXTRACCIÓN DE ESPECIFICACIONES TÉCNICAS
    //  (PROBLEMA PRINCIPAL — AMPLIADO)
    // ─────────────────────────────────────────────

    private static function extraerEspecificaciones(string $texto): ?string
    {
        // Patrones de INICIO de sección (orden de prioridad)
        $inicios = [
            // Numeradas: "3. ESPECIFICACIONES TÉCNICAS"
            '\d+[\.\)]\s+ESPECIFICACIONES\s+T[ÉE]CNICAS\.?',
            // Sin número
            'ESPECIFICACIONES\s+T[ÉE]CNICAS\.?',
            // "CARACTERÍSTICAS TÉCNICAS"
            '\d+[\.\)]\s+CARACTER[ÍI]STICAS\s+T[ÉE]CNICAS\.?',
            'CARACTER[ÍI]STICAS\s+T[ÉE]CNICAS\.?',
            // "SERVICIO ESPERADO" — común en TDR de servicios  ← NUEVO
            '\d+[\.\)]\s+SERVICIO\s+ESPERADO\.?',
            'SERVICIO\s+ESPERADO\.?',
            // "DESCRIPCIÓN TÉCNICA"
            'DESCRIPCI[ÓO]N\s+T[ÉE]CNICA\.?',
            // Tabla con cabecera ITEM | CPC | CARACTERÍSTICAS  ← NUEVO
            'ITEM\s+CPC\s+CARACTER[ÍI]STICAS',
            'N[°º]\s+ITEM\s+',
            // "REQUERIMIENTOS TÉCNICOS"
            'REQUERIMIENTOS\s+T[ÉE]CNICOS\.?',
            // "TÉRMINOS DE REFERENCIA" a veces contiene las especificaciones
            'T[ÉE]RMINOS\s+DE\s+REFERENCIA\.?',
        ];

        // Patrones de FIN de sección
        $fines = [
            '\d+[\.\)]\s+METODOLOG[ÍI]A',
            '\d+[\.\)]\s+PLAZO',
            '\d+[\.\)]\s+VIGENCIA',
            '\d+[\.\)]\s+OBLIGACIONES',
            '\d+[\.\)]\s+FORMA\s+DE\s+PAGO',
            '\d+[\.\)]\s+GARANTIA',
            '\d+[\.\)]\s+PENALIDADES',
            '\d+[\.\)]\s+DOCUMENTOS',
            '\d+[\.\)]\s+PRESUPUESTO',
            'METODOLOG[ÍI]A\s+(?:DE\s+TRABAJO|A\s+DESARROLLAR)',
            'FORMA\s+DE\s+PAGO',
            'CRONOGRAMA',
        ];

        $resultado = self::extraerSeccion($texto, $inicios, $fines, 5000);

        // Si encontró tabla ITEM/CPC, convertirla a HTML para CKEditor
        if ($resultado && self::esTablaItemCpc($resultado)) {
            $resultado = self::convertirTablaItemCpc($resultado);
        }

        return $resultado;
    }

    // ─────────────────────────────────────────────
    //  EXTRACCIÓN DE METODOLOGÍA
    // ─────────────────────────────────────────────

    private static function extraerMetodologia(string $texto): ?string
    {
        $inicios = [
            '\d+[\.\)]\s+METODOLOG[ÍI]A\s+(?:A\s+DESARROLLAR|DE\s+TRABAJO|DEL\s+TRABAJO)?\.?',
            'METODOLOG[ÍI]A\s+(?:A\s+DESARROLLAR|DE\s+TRABAJO)?\.?',
            '\d+[\.\)]\s+M[ÉE]TODO\s+DE\s+TRABAJO\.?',
            'M[ÉE]TODO\s+DE\s+TRABAJO\.?',
            // NUEVO: "PROCEDIMIENTO" como alternativa a metodología
            '\d+[\.\)]\s+PROCEDIMIENTO\.?',
            'PROCEDIMIENTO\s+(?:DE\s+)?TRABAJO\.?',
        ];

        $fines = [
            '\d+[\.\)]\s+PLAZO',
            '\d+[\.\)]\s+VIGENCIA',
            '\d+[\.\)]\s+OBLIGACIONES',
            '\d+[\.\)]\s+FORMA\s+DE\s+PAGO',
            '\d+[\.\)]\s+GARANTIA',
            '\d+[\.\)]\s+DOCUMENTOS',
            '\d+[\.\)]\s+PRESUPUESTO',
            '\d+[\.\)]\s+PENALIDADES',
            'FORMA\s+DE\s+PAGO',
            'CRONOGRAMA',
        ];

        return self::extraerSeccion($texto, $inicios, $fines, 3000);
    }

    // ─────────────────────────────────────────────
    //  EXTRACCIÓN DE CPC (código + descripción completa)
    // ─────────────────────────────────────────────

    private static function extraerCpc(string $texto): ?string
    {
        // Buscar CPC: código numérico + descripción en líneas siguientes
        $patrones = [
            // "CPC:\n   842200011\n   SERVICIOS DE..."
            '/CPC[:\s]*\n\s*(\d{5,12})\s*\n\s*([A-ZÁÉÍÓÚÑ][^\n]{10,300})/si',
            // "CPC: 842200011 SERVICIOS DE..."
            '/CPC[:\s]+(\d{5,12})\s+([A-ZÁÉÍÓÚÑ][^\n]{10,300})/si',
            // Línea con solo el código CPC seguido de descripción
            '/\bCPC\b[^:\n]*[:\n]\s*(\d{5,12})[\s\n]+([A-ZÁÉÍÓÚÑ][^\n]{10,}(?:\n(?!\d{1,2}[\.\)]\s+[A-Z])[^\n]{0,300})*)/si',
        ];

        foreach ($patrones as $p) {
            if (preg_match($p, $texto, $m)) {
                $codigo = trim($m[1]);
                $desc   = trim(preg_replace('/\s+/', ' ', $m[2]));
                if (strlen($desc) > 10)
                    return $codigo . "\n" . $desc;
            }
        }

        // Fallback: buscar solo el código CPC
        if (preg_match('/CPC[:\s]+(\d{5,12})/i', $texto, $m))
            return trim($m[1]);

        return null;
    }

    // ─────────────────────────────────────────────
    //  EXTRACCIÓN DE PLAZO TEXTO (oración completa)
    // ─────────────────────────────────────────────

    private static function extraerPlazoTexto(string $texto): ?string
    {
        $patrones = [
            // "El plazo para la ejecución del servicio es de 5 días laborables..."
            '/(El\s+plazo\s+(?:para|de)\s+[^.]{10,300}\.)/si',
            // "El plazo máximo de entrega..."
            '/(El\s+plazo\s+m[aá]ximo[^.]{10,300}\.)/si',
            // Sección PLAZO DE EJECUCIÓN → primera oración completa
            '/PLAZO\s+DE\s+(?:EJECUCI[ÓO]N|ENTREGA)[^\n]*\n\s*([^\n]{20,300}(?:\n(?!\d+[\.\)]\s)[^\n]{0,200})*)/si',
        ];

        foreach ($patrones as $p) {
            if (preg_match($p, $texto, $m)) {
                $val = trim(preg_replace('/\s+/', ' ', $m[1]));
                if (strlen($val) > 20) return $val;
            }
        }
        return null;
    }

    // ─────────────────────────────────────────────
    //  EXTRACCIÓN DE FORMA Y CONDICIONES DE PAGO
    // ─────────────────────────────────────────────

    private static function extraerFormaPago(string $texto): ?string
    {
        $inicios = [
            '\d+[\.\)]\s+FORMA\s+Y\s+CONDICIONES\s+DE\s+PAGO\.?',
            '\d+[\.\)]\s+FORMA\s+DE\s+PAGO\.?',
            'FORMA\s+Y\s+CONDICIONES\s+DE\s+PAGO\.?',
            'FORMA\s+DE\s+PAGO\.?',
            'CONDICIONES\s+DE\s+PAGO\.?',
        ];

        $fines = [
            '\d+[\.\)]\s+VIGENCIA',
            '\d+[\.\)]\s+GARANTIA',
            '\d+[\.\)]\s+PENALIDADES',
            '\d+[\.\)]\s+OBLIGACIONES',
            '\d+[\.\)]\s+DOCUMENTOS',
            '\d+[\.\)]\s+DECLARACI[ÓO]N',
            '\d+[\.\)]\s+PLAZO',
            'VIGENCIA\s+DE\s+LA\s+OFERTA',
        ];

        return self::extraerSeccion($texto, $inicios, $fines, 1000);
    }

    // ─────────────────────────────────────────────
    //  EXTRACCIÓN DE DECLARACIÓN DE CUMPLIMIENTO
    // ─────────────────────────────────────────────

    private static function extraerDeclaracion(string $texto): ?string
    {
        $inicios = [
            '\d+[\.\)]\s+DECLARACI[ÓO]N\s+DE\s+CUMPLIMIENTO\.?',
            'DECLARACI[ÓO]N\s+DE\s+CUMPLIMIENTO\.?',
            '\d+[\.\)]\s+DECLARACI[ÓO]N\.?',
        ];

        $fines = [
            '\d+[\.\)]\s+[A-ZÁÉÍÓÚÑ]{4,}',
            'FIRMA',
            'REPRESENTANTE',
        ];

        $resultado = self::extraerSeccion($texto, $inicios, $fines, 800);

        // Si no hay sección de declaración en el TDR, usar texto por defecto
        if (empty($resultado)) {
            return 'Confirmamos que nuestra oferta cumple completamente con todos los términos y condiciones especificados en los términos de referencia (TDR) proporcionados por su institución.';
        }

        return $resultado;
    }

    // ─────────────────────────────────────────────
    //  HELPER: extraerSeccion()
    //  Busca inicio → copia hasta encontrar fin
    // ─────────────────────────────────────────────

    private static function extraerSeccion(
        string $texto,
        array  $patronesInicio,
        array  $patronesFin,
        int    $maxChars = 4000
    ): ?string {
        $posInicio = false;
        $largoEtiq = 0;

        // Buscar la primera ocurrencia de cualquier patrón de inicio
        foreach ($patronesInicio as $pi) {
            if (preg_match('/' . $pi . '/si', $texto, $m, PREG_OFFSET_CAPTURE)) {
                $pos = $m[0][1];
                if ($posInicio === false || $pos < $posInicio) {
                    $posInicio = $pos;
                    $largoEtiq = strlen($m[0][0]);
                }
            }
        }

        if ($posInicio === false) return null;

        // Texto desde después del encabezado de sección
        $desde    = $posInicio + $largoEtiq;
        $fragmento = mb_substr($texto, $desde, $maxChars);

        // Buscar el primer fin de sección
        $posFin = strlen($fragmento);
        foreach ($patronesFin as $pf) {
            if (preg_match('/' . $pf . '/si', $fragmento, $mf, PREG_OFFSET_CAPTURE)) {
                $pf_pos = $mf[0][1];
                if ($pf_pos < $posFin && $pf_pos > 10)
                    $posFin = $pf_pos;
            }
        }

        $contenido = mb_substr($fragmento, 0, $posFin);
        $contenido = self::limpiarTexto($contenido);

        return mb_strlen($contenido) > 30 ? $contenido : null;
    }

    // ─────────────────────────────────────────────
    //  HELPERS PARA TABLAS ITEM/CPC
    // ─────────────────────────────────────────────

    /**
     * Detecta si el texto extraído contiene una tabla ITEM/CPC (texto plano de pdftotext)
     */
    private static function esTablaItemCpc(string $texto): bool
    {
        return (bool) preg_match(
            '/(?:ITEM|N[°º])\s+(?:CPC|C\.P\.C)\s+(?:CARACTER[ÍI]STICAS?|DESCRIPCI[ÓO]N)/si',
            $texto
        );
    }

    /**
     * Convierte tabla plana de pdftotext en HTML <table> para CKEditor
     * 
     * Entrada típica (pdftotext -layout):
     *   ITEM   CPC        CARACTERÍSTICAS
     *   1      852290     Servicio de consultoría...
     *   2      852290     Elaboración de informes...
     */
    private static function convertirTablaItemCpc(string $texto): string
    {
        $lineas = explode("\n", $texto);
        $filas  = [];
        $enTabla = false;
        $cabeceras = [];

        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if (empty($linea)) continue;

            // Detectar cabecera
            if (preg_match('/(?:ITEM|N[°º])\s+(?:CPC|C\.P\.C)/si', $linea)) {
                $enTabla   = true;
                // Extraer nombres de columna
                $cabeceras = preg_split('/\s{2,}/', $linea);
                continue;
            }

            if (!$enTabla) continue;

            // Detectar fin de tabla (nueva sección numerada)
            if (preg_match('/^\d+[\.\)]\s+[A-ZÁÉÍÓÚÑ]{4,}/', $linea)) {
                break;
            }

            // Dividir columnas por 2+ espacios (pdftotext -layout los separa así)
            $cols = preg_split('/\s{2,}/', $linea);
            if (count($cols) >= 2) {
                $filas[] = $cols;
            }
        }

        if (empty($filas)) {
            // No pudo parsear tabla → devolver como <pre>
            return '<pre>' . htmlspecialchars($texto) . '</pre>';
        }

        // Construir HTML
        $html = '<figure class="table"><table><thead><tr>';
        if (!empty($cabeceras)) {
            foreach ($cabeceras as $cab)
                $html .= '<th>' . htmlspecialchars(trim($cab)) . '</th>';
        } else {
            // Cabeceras genéricas
            $numCols = max(array_map('count', $filas));
            for ($i = 0; $i < $numCols; $i++)
                $html .= '<th>Columna ' . ($i + 1) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($filas as $fila) {
            $html .= '<tr>';
            foreach ($fila as $celda)
                $html .= '<td>' . htmlspecialchars(trim($celda)) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></figure>';
        return $html;
    }

    // ─────────────────────────────────────────────
    //  LIMPIEZA DE TEXTO EXTRAÍDO
    // ─────────────────────────────────────────────

    private static function limpiarTexto(string $texto): string
    {
        // Quitar caracteres no imprimibles excepto saltos de línea
        $texto = preg_replace('/[^\x09\x0A\x0D\x20-\x7E\x80-\xFF]/', ' ', $texto);
        // Colapsar espacios múltiples en la misma línea
        $texto = preg_replace('/[ \t]{3,}/', '  ', $texto);
        // Colapsar líneas en blanco excesivas (más de 2 seguidas → 1)
        $texto = preg_replace('/\n{3,}/', "\n\n", $texto);
        return trim($texto);
    }

    // ─────────────────────────────────────────────
    //  EXTRACCIÓN AJAX (para /ia/extraer-ajax)
    //  Recibe ruta PDF ya subida, devuelve JSON
    // ─────────────────────────────────────────────

    public static function extraerParaAjax(string $rutaPdf): array
    {
        try {
            if (!file_exists($rutaPdf))
                return ['ok' => false, 'error' => 'Archivo no encontrado'];

            $datos = self::extraerDatos($rutaPdf);

            // Formatear monto para el campo HTML (dos decimales, sin símbolo)
            if (!empty($datos['monto_total']))
                $datos['monto_total'] = number_format((float)$datos['monto_total'], 2, '.', '');

            return ['ok' => true, 'datos' => $datos];

        } catch (\Exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ═════════════════════════════════════════════════════════════
    //  EXTRACCIÓN DE TODAS LAS SECCIONES TDR — v2 CORREGIDA
    //  Para el selector visual de secciones en Fase 2
    // ═════════════════════════════════════════════════════════════

    /**
     * Extrae TODAS las secciones del PDF TDR.
     * Retorna: ['ok'=>true, 'secciones'=>[...], 'total'=>N]
     */
    public static function extraerSecciones(string $rutaPdf): array
    {
        // Extraer texto del PDF
        $texto = self::extraerConGhostscript($rutaPdf);
        if (mb_strlen(trim($texto)) < 50) {
            $texto = self::extraerConSmalot($rutaPdf);
        }
        if (mb_strlen(trim($texto)) < 50) {
            return ['ok' => false, 'error' => 'No se pudo extraer texto del PDF.'];
        }

        // Usar IA para detectar y estructurar las secciones
        return self::extraerSeccionesConIA($texto);
    }

    /**
     * Extrae secciones del TDR usando OpenRouter/IA.
     * Funciona con cualquier formato de TDR ecuatoriano.
     */
    private static function extraerSeccionesConIA(string $texto): array
    {
        $apiKey = defined('OPENROUTER_KEY') ? OPENROUTER_KEY : (getenv('OPENROUTER_KEY') ?: '');
        if (!$apiKey) {
            return ['ok' => false, 'error' => 'OPENROUTER_KEY no configurado.'];
        }

        // Enviar hasta 10000 chars a la IA
        $textoTruncado = mb_substr($texto, 0, 10000);

        $prompt = 'Extrae todas las secciones del siguiente TDR ecuatoriano. '
                . 'Para campo_destino usa exactamente uno de estos valores o null: especificaciones_tecnicas, metodologia_trabajo, forma_pago, plazo_texto, vigencia_oferta, declaracion_cumplimiento. '
                . 'Responde SOLO con JSON sin markdown ni texto extra: {"secciones":[{"titulo":"...","campo_destino":null,"contenido":"..."}]}\n\nTDR:\n' . $textoTruncado;


        $payload = json_encode([
            'model'      => defined('OPENROUTER_MODEL') ? OPENROUTER_MODEL : 'openrouter/auto',
            'max_tokens' => 8000,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ]);

        $url = defined('OPENROUTER_URL') ? OPENROUTER_URL : 'https://openrouter.ai/api/v1/chat/completions';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
        ]);
        $resp      = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if (!$resp || $curlError) {
            return ['ok' => false, 'error' => 'Error cURL: ' . $curlError];
        }

        $data       = json_decode($resp, true);
        $iaContent  = $data['choices'][0]['message']['content'] ?? '';

        // Limpiar markdown si viene con ```json ... ```
        $iaContent = trim($iaContent);
        if (substr($iaContent, 0, 7) === '```json') $iaContent = substr($iaContent, 7);
        elseif (substr($iaContent, 0, 3) === '```') $iaContent = substr($iaContent, 3);
        if (substr($iaContent, -3) === '```') $iaContent = substr($iaContent, 0, -3);
        $iaContent = trim($iaContent);

        // json_decode directo
        $parsed = json_decode($iaContent, true);

        // Si falla, buscar el bloque JSON dentro de la respuesta
        if (!is_array($parsed) || !isset($parsed['secciones'])) {
            if (preg_match('/\{[\s\S]*\}/u', $iaContent, $m)) {
                $parsed = json_decode($m[0], true);
            }
        }

        if (!isset($parsed['secciones']) || !is_array($parsed['secciones'])) {
            return ['ok' => false, 'error' => 'JSON inválido. Error=' . json_last_error_msg() . ' Respuesta: ' . mb_substr($iaContent, 0, 400)];
        }

        $secciones = [];
        foreach ($parsed['secciones'] as $i => $sec) {
            $titulo    = trim($sec['titulo']        ?? '');
            $contenido = trim($sec['contenido']     ?? '');
            $campo     = $sec['campo_destino'] ?? null;
            if ($campo === 'null' || $campo === '') $campo = null;
            if (!$titulo || !$contenido) continue;

            $secciones[] = [
                'clave'         => 'seccion_' . ($i + 1),
                'titulo'        => $titulo,
                'titulo_raw'    => $titulo,
                'campo_destino' => $campo,
                'contenido'     => $contenido,
                'html'          => self::textoAHtml($contenido),
                'longitud'      => mb_strlen($contenido),
            ];
        }

        if (empty($secciones)) {
            return ['ok' => false, 'error' => 'La IA no encontró secciones en el TDR.'];
        }

        return [
            'ok'       => true,
            'secciones'=> $secciones,
            'total'    => count($secciones),
            '_metodo'  => 'ia',
        ];
    }


    /**
     * Fallback: extrae texto leyendo el contenido binario del PDF
     * Busca streams de texto en el PDF crudo
     */
    private static function extraerTextoFallback(string $ruta): string
    {
        // Método 1: strings del sistema
        $out = shell_exec('strings ' . escapeshellarg($ruta) . ' 2>/dev/null');
        if ($out && mb_strlen(trim($out)) > 100) {
            // Filtrar solo líneas con texto legible (letras, no solo símbolos)
            $lineas = explode("\n", $out);
            $legibles = array_filter($lineas, fn($l) => preg_match('/[a-záéíóúñA-ZÁÉÍÓÚÑ]{3,}/', $l));
            $resultado = implode("\n", $legibles);
            if (mb_strlen(trim($resultado)) > 100) return $resultado;
        }

        // Método 2: leer el PDF como binario y extraer texto entre BT/ET
        $raw = file_exists($ruta) ? file_get_contents($ruta) : false;
        if (!$raw) return '';

        $texto = '';
        // Buscar bloques de texto PDF entre BT...ET
        preg_match_all('/BT\s*(.*?)\s*ET/s', $raw, $matches);
        foreach ($matches[1] as $bloque) {
            // Extraer strings entre paréntesis ( ) 
            preg_match_all('/\(([^)]{1,500})\)/', $bloque, $strs);
            foreach ($strs[1] as $s) {
                $s = preg_replace('/[^\x20-\x7E\xC0-\xFF]/', ' ', $s);
                if (mb_strlen(trim($s)) > 2) $texto .= $s . ' ';
            }
            $texto .= "\n";
        }

        return $texto;
    }

    /**
     * Detecta secciones del TDR usando el patrón real del PDF Ecuador:
     * "\n1.- NOMBRE DE SECCION" (número + punto + guión + mayúsculas con tildes)
     * Lógica idéntica al script que funciona en el servidor.
     */
    private static function detectarTodasLasSecciones(string $texto): array
    {
        // Normalizar saltos de línea
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);

        // Patrones en orden de especificidad — cubren formatos reales de TDR Ecuador

        $patrones = [
            '/((?:^|\n)[ \t]*\d{1,2}\.[ \t]*-[ \t]+[A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ ]{2,}:?)/u',
            '/((?:^|\n)[ \t]*\d{1,2}\.[ \t]+[A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ ]{2,}:?)/u',
            '/((?:^|\n)[ \t]*\d{1,2}\)[ \t]+[A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ ]{2,})/u',
            '/((?:^|\n)[ \t]*\d{1,2}[.\)\-][ \t]+[A-ZÁÉÍÓÚÑ][^\n]{3,60})/u',
        ];

        $partes = [];
        foreach ($patrones as $patron) {
            $intento = preg_split($patron, $texto, -1, PREG_SPLIT_DELIM_CAPTURE);
            if ($intento !== false && preg_last_error() === PREG_NO_ERROR && count($intento) >= 3) {
                $partes = $intento;
                break;
            }
        }

        if (!is_array($partes) || count($partes) < 3) {
            return self::detectarSeccionesLibres($texto);
        }

        // Mapa de palabras clave → clave semántica y campo destino
        $mapaDestino = [
            'ESPECIFICACIONES'        => ['clave'=>'especificaciones_tecnicas', 'campo'=>'especificaciones_tecnicas'],
            'CARACTER'                => ['clave'=>'especificaciones_tecnicas', 'campo'=>'especificaciones_tecnicas'],
            'METODOLOG'               => ['clave'=>'metodologia_trabajo',       'campo'=>'metodologia_trabajo'],
            'FORMAS DE PAGO'          => ['clave'=>'forma_pago',                'campo'=>'forma_pago'],
            'FORMA DE PAGO'           => ['clave'=>'forma_pago',                'campo'=>'forma_pago'],
            'CONDICIONES DE PAGO'     => ['clave'=>'forma_pago',                'campo'=>'forma_pago'],
            'PLAZO'                   => ['clave'=>'plazo',                     'campo'=>'plazo_texto'],
            'VIGENCIA'                => ['clave'=>'vigencia_oferta',           'campo'=>'vigencia_oferta'],
            'DECLARACI'               => ['clave'=>'declaracion_cumplimiento',  'campo'=>'declaracion_cumplimiento'],
            'ANTECEDENTES'            => ['clave'=>'antecedentes',              'campo'=>null],
            'OBJETO'                  => ['clave'=>'objeto',                    'campo'=>null],
            'OBJETIVOS'               => ['clave'=>'objetivos',                 'campo'=>null],
            'OBJETIVO'                => ['clave'=>'objetivos',                 'campo'=>null],
            'ALCANCE'                 => ['clave'=>'alcance',                   'campo'=>null],
            'BASE LEGAL'              => ['clave'=>'base_legal',                'campo'=>null],
            'PRODUCTOS'               => ['clave'=>'productos_esperados',       'campo'=>null],
            'ENTREGABLE'              => ['clave'=>'productos_esperados',       'campo'=>null],
            'PERSONAL'                => ['clave'=>'personal_tecnico',          'campo'=>null],
            'OBLIGACIONES DEL PROV'   => ['clave'=>'obligaciones_contratista',  'campo'=>null],
            'OBLIGACIONES DEL CONT'   => ['clave'=>'obligaciones_contratista',  'campo'=>null],
            'OBLIGACIONES DE LA ENT'  => ['clave'=>'obligaciones_entidad',      'campo'=>null],
            'OBLIGACIONES DE LA CON'  => ['clave'=>'obligaciones_entidad',      'campo'=>null],
            'GARANT'                  => ['clave'=>'garantia',                  'campo'=>null],
            'PENALIDAD'               => ['clave'=>'penalidades',               'campo'=>null],
            'MULTA'                   => ['clave'=>'penalidades',               'campo'=>null],
            'PROFORMA'                => ['clave'=>'contenido_proforma',        'campo'=>null],
            'INFORMACI'               => ['clave'=>'informacion_entidad',       'campo'=>null],
            'LUGAR DE RECEPCI'        => ['clave'=>'lugar_recepcion',           'campo'=>null],
            'ADMINISTRACI'            => ['clave'=>'administracion',            'campo'=>null],
            'FIRMAS'                  => ['clave'=>'firmas',                    'campo'=>null],
            'TIPO DE COMPRA'          => ['clave'=>'tipo_compra',               'campo'=>null],
            'DESCRIPCI'               => ['clave'=>'descripcion',               'campo'=>null],
        ];

        $secciones    = [];
        $clavesVistas = [];

        for ($i = 1; $i < count($partes); $i += 2) {
            $tituloRaw = trim($partes[$i]);
            $contenido = isset($partes[$i + 1]) ? trim($partes[$i + 1]) : '';

            if (mb_strlen($contenido) < 5) continue;

            // Determinar clave y campo destino buscando palabras clave en el título
            $clave  = 'seccion_' . (int)(($i + 1) / 2);
            $campo  = null;
            $tituloUpper = mb_strtoupper($tituloRaw, 'UTF-8');

            foreach ($mapaDestino as $palabraClave => $info) {
                if (mb_strpos($tituloUpper, mb_strtoupper($palabraClave, 'UTF-8')) !== false) {
                    $claveCandidata = $info['clave'];
                    if (!in_array($claveCandidata, $clavesVistas)) {
                        $clave = $claveCandidata;
                        $campo = $info['campo'];
                        $clavesVistas[] = $clave;
                    }
                    break;
                }
            }

            $secciones[] = [
                'clave'         => $clave,
                'titulo'        => $tituloRaw,
                'titulo_raw'    => $tituloRaw,
                'campo_destino' => $campo,
                'contenido'     => $contenido,
                'html'          => '',
                'longitud'      => mb_strlen($contenido),
            ];
        }

        return $secciones;
    }


    /**
     * Detección libre: cualquier línea corta en MAYÚSCULAS = posible encabezado
     */
    private static function detectarSeccionesLibres(string $texto): array
    {
        $lineas    = explode("\n", $texto);
        $hits      = [];
        $bytePos   = 0;

        foreach ($lineas as $linea) {
            $limpia = trim($linea);
            $largo  = mb_strlen($limpia);

            if ($largo >= 4 && $largo <= 90) {
                // Encabezado: empieza con dígito o letra mayúscula, todo mayúsculas
                $esTitulo = preg_match(
                    '/^(?:\d{1,2}[\.\-\)]\s*)?[A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ\s\.\-\(\)\/]{3,}$/',
                    $limpia
                );
                if ($esTitulo) {
                    $hits[] = [
                        'pos'        => $bytePos,
                        'largo'      => strlen($linea) + 1,
                        'titulo_raw' => $limpia,
                        'clave'      => 'libre_' . count($hits),
                        'titulo'     => ucfirst(strtolower($limpia)),
                        'campo'      => null,
                    ];
                }
            }
            $bytePos += strlen($linea) + 1;
        }

        // Extraer contenido
        $secciones = [];
        $total     = count($hits);
        foreach ($hits as $i => $hit) {
            $desde     = $hit['pos'] + $hit['largo'];
            $hasta     = ($i + 1 < $total) ? $hits[$i + 1]['pos'] : strlen($texto);
            $contenido = self::limpiarTexto(mb_substr($texto, $desde, $hasta - $desde));
            if (mb_strlen($contenido) < 15) continue;

            $secciones[] = [
                'clave'         => $hit['clave'],
                'titulo'        => $hit['titulo'],
                'titulo_raw'    => $hit['titulo_raw'],
                'campo_destino' => null,
                'contenido'     => $contenido,
                'html'          => '',
                'longitud'      => mb_strlen($contenido),
            ];
        }

        return $secciones;
    }

    /**
     * Convierte texto plano de una sección a HTML semántico limpio.
     *
     * Reglas:
     *  - Párrafos separados por línea vacía → <p>
     *  - Bullets • ─ - * → <ul><li>
     *  - Numerados "1." "a)" "i)" al inicio de línea → <ol><li>
     *  - Línea corta TODO MAYÚSCULAS (≤80 chars) → <h4>
     *  - Tablas de texto plano (columnas separadas por 2+ espacios) → <table>
     */
    public static function textoAHtml(string $texto): string
    {
        $texto = trim($texto);
        if (empty($texto)) return '';

        // Detectar tabla tipo pdftotext -layout
        if (self::esTablaItemCpc($texto)) {
            return self::convertirTablaItemCpc($texto);
        }

        // Detectar tabla genérica (≥2 filas con columnas alineadas)
        $tablaGen = self::detectarTablaGenerica($texto);
        if ($tablaGen) return $tablaGen;

        $lineas = explode("\n", $texto);
        $html   = '';
        $enLista   = false;
        $tipoLista = 'ul';
        $buffer    = ''; // Buffer para párrafo

        $flushBuffer = function() use (&$html, &$buffer) {
            $b = trim($buffer);
            if ($b !== '') {
                $html  .= '<p>' . nl2br(htmlspecialchars($b, ENT_QUOTES, 'UTF-8')) . "</p>\n";
                $buffer = '';
            }
        };

        $flushLista = function() use (&$html, &$enLista, &$tipoLista) {
            if ($enLista) {
                $html     .= "</{$tipoLista}>\n";
                $enLista   = false;
            }
        };

        foreach ($lineas as $linea) {
            $l      = rtrim($linea);
            $limpia = trim($l);
            $largo  = mb_strlen($limpia);
            // Detectar nivel de sangría (número de espacios/tabs al inicio)
            $sangria = mb_strlen($l) - mb_strlen(ltrim($l, " \t"));

            // Línea vacía → cerrar párrafo
            if ($largo === 0) {
                $flushBuffer();
                continue;
            }

            // ─── Sub-encabezado: línea corta TODO MAYÚSCULAS ──────────────
            if ($largo <= 80 && $largo >= 4 &&
                preg_match('/^(?:\d{1,2}[\.\-\)]\s*)?[A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ\s\.\-\:\/]{3,}$/', $limpia) &&
                !preg_match('/[a-záéíóúñ]/u', $limpia))
            {
                $flushBuffer();
                $flushLista();
                $html .= '<h4>' . htmlspecialchars($limpia, ENT_QUOTES, 'UTF-8') . "</h4>\n";
                continue;
            }

            // ─── Bullet con sangría ≥4 → sub-lista anidada ───────────────
            if ($sangria >= 4 && preg_match('/^([•\-\*►◆▸▶·]\s+)(.+)/', $limpia, $mb)) {
                $flushBuffer();
                if (!$enLista) {
                    // Abrir lista padre si no hay una
                    $html .= "<ul>\n";
                    $enLista   = true;
                    $tipoLista = 'ul';
                }
                // Ítem anidado como <ul> dentro del último <li>
                $html .= '<li style="list-style-type:circle;margin-left:20px">'
                       . htmlspecialchars(trim($mb[2]), ENT_QUOTES, 'UTF-8')
                       . "</li>\n";
                continue;
            }

            // ─── Bullet normal: •  ─  -  *  ►  ▶  ◆ ────────────────────
            if (preg_match('/^([•\-\*►◆▸▶·]\s+)(.+)/', $limpia, $mb)) {
                $flushBuffer();
                if (!$enLista || $tipoLista !== 'ul') {
                    $flushLista();
                    $html     .= "<ul>\n";
                    $enLista   = true;
                    $tipoLista = 'ul';
                }
                $html .= '<li>' . htmlspecialchars(trim($mb[2]), ENT_QUOTES, 'UTF-8') . "</li>\n";
                continue;
            }

            // ─── Lista ordenada con sangría ≥4 → sub-lista anidada ───────
            if ($sangria >= 4 &&
                preg_match('/^([a-z][\.\)]\s+|[ivxlc]+[\.\)]\s+)(.+)/i', $limpia, $mn) &&
                mb_strlen($limpia) < 250)
            {
                $flushBuffer();
                if (!$enLista) {
                    $html .= "<ol>\n";
                    $enLista   = true;
                    $tipoLista = 'ol';
                }
                $html .= '<li style="list-style-type:lower-alpha;margin-left:20px">'
                       . htmlspecialchars(trim($mn[2]), ENT_QUOTES, 'UTF-8')
                       . "</li>\n";
                continue;
            }

            // ─── Lista ordenada normal: "1." "a)" "i." al inicio ─────────
            if (preg_match('/^(\d{1,2}[\.\)]\s+|[a-z][\.\)]\s+|[ivxlc]+[\.\)]\s+)(.+)/i', $limpia, $mn)
                && mb_strlen($limpia) < 250)
            {
                $flushBuffer();
                if (!$enLista || $tipoLista !== 'ol') {
                    $flushLista();
                    $html     .= "<ol>\n";
                    $enLista   = true;
                    $tipoLista = 'ol';
                }
                $html .= '<li>' . htmlspecialchars(trim($mn[2]), ENT_QUOTES, 'UTF-8') . "</li>\n";
                continue;
            }

            // ─── Texto normal: cerrar listas y acumular párrafo ──────────
            $flushLista();
            if ($buffer !== '') {
                $buffer .= ' ' . $limpia;
            } else {
                $buffer = $limpia;
            }

            // Fin de oración → cerrar párrafo
            if (preg_match('/[\.!\?]\s*$/u', $limpia) && mb_strlen($buffer) > 80) {
                $flushBuffer();
            }
        }

        $flushBuffer();
        $flushLista();

        return $html ?: '<p>' . htmlspecialchars($texto, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    /**
     * Detecta y convierte tablas genéricas de texto plano (columnas por espacios).
     * Retorna HTML <table> o null si no parece tabla.
     *
     * Mejoras v2:
     *  - Detecta automáticamente la cabecera (primera fila en MAYÚSCULAS)
     *  - Usa <thead>/<tbody> para accesibilidad
     *  - Agrega vertical-align:top en celdas de datos
     *  - Umbral bajado a 55% para capturar más tablas reales de TDR
     */
    private static function detectarTablaGenerica(string $texto): ?string
    {
        $lineas = array_filter(explode("\n", $texto), fn($l) => trim($l) !== '');
        $lineas = array_values($lineas);

        if (count($lineas) < 2) return null;

        // Verificar que ≥55% de líneas tienen columnas separadas por 2+ espacios
        $conColumnas      = 0;
        $columnasPorLinea = [];
        foreach ($lineas as $l) {
            $cols = preg_split('/\s{2,}/', trim($l));
            $n    = count($cols);
            if ($n >= 2) $conColumnas++;
            $columnasPorLinea[] = $n;
        }

        $pct = count($lineas) > 0 ? $conColumnas / count($lineas) : 0;
        if ($pct < 0.55) return null;

        // Número de columnas más frecuente
        $frecuencias = array_count_values($columnasPorLinea);
        arsort($frecuencias);
        $nCols = key($frecuencias);
        if ($nCols < 2) return null;

        // La primera fila es cabecera si está completamente en MAYÚSCULAS
        $primeraLinea = trim($lineas[0]);
        $esCabecera   = $primeraLinea !== '' && !preg_match('/[a-záéíóúñ]/u', $primeraLinea);

        $styleTh = ' style="background:#e8e8e8;font-weight:bold;border:1px solid #ccc;padding:4px 8px;text-align:left"';
        $styleTd = ' style="border:1px solid #ccc;padding:4px 8px;vertical-align:top"';

        // Construir tabla HTML con <thead>/<tbody>
        $htmlHead = '';
        $htmlBody = '';

        foreach ($lineas as $idx => $l) {
            $cols = preg_split('/\s{2,}/', trim($l));
            while (count($cols) < $nCols) $cols[] = '';

            $celdasHtml = '';
            foreach (array_slice($cols, 0, $nCols) as $c) {
                $cEsc = htmlspecialchars(trim($c), ENT_QUOTES, 'UTF-8');
                if ($idx === 0 && $esCabecera) {
                    $celdasHtml .= "<th{$styleTh}>{$cEsc}</th>";
                } else {
                    $celdasHtml .= "<td{$styleTd}>{$cEsc}</td>";
                }
            }

            if ($idx === 0 && $esCabecera) {
                $htmlHead .= "<thead><tr>{$celdasHtml}</tr></thead>";
            } else {
                $htmlBody .= "<tr>{$celdasHtml}</tr>";
            }
        }

        return '<table style="border-collapse:collapse;width:100%">'
             . $htmlHead
             . "<tbody>{$htmlBody}</tbody>"
             . '</table>';
    }
}