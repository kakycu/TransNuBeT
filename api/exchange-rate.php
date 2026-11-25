<?php
/**
 * Proxy de tasas elTOQUE (mercado informal, CUP).
 *
 * - /api/exchange-rate.php        -> tasa USD->CUP (compatibilidad con las cards)
 * - /api/exchange-rate.php?all=1  -> todas las tasas + variaciones (modal de precios)
 *
 * Prioridad: elTOQUE -> cache local -> fallback fijo.
 * El token se lee de api/eltoque-token.local.php (no versionado) o de ELTOQUE_API_TOKEN.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=300');

function exrate_cache_path(): string { return sys_get_temp_dir() . '/transnubet_usd_cup_rate.json'; }
function exrate_read_cache(int $maxAge = 21600): ?float {
    $f = exrate_cache_path();
    if (!is_file($f)) return null;
    $raw = @file_get_contents($f);
    $j = $raw ? json_decode($raw, true) : null;
    if (!is_array($j) || !is_numeric($j['rate'] ?? null) || !is_numeric($j['ts'] ?? null)) return null;
    if ((time() - (int)$j['ts']) > $maxAge) return null;
    $r = (float)$j['rate'];
    return $r > 0 ? $r : null;
}
function exrate_write_cache(float $rate): void {
    @file_put_contents(exrate_cache_path(), json_encode(['rate' => $rate, 'ts' => time()]), LOCK_EX);
}

function exrate_http_raw(string $url, array $headers = [], int $timeout = 15): ?string {
    $h = array_merge(['Accept: */*'], $headers);
    $cacert = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
    $hasCa = $cacert && is_file($cacert);

    if (!function_exists('curl_init')) {
        $ctx = stream_context_create([
            'http'  => ['timeout' => $timeout, 'ignore_errors' => true, 'header' => implode("\r\n", $h)],
            'https' => ['timeout' => $timeout, 'ignore_errors' => true, 'header' => implode("\r\n", $h)],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        return $raw === false ? null : $raw;
    }

    // Reintenta: en Windows/USBWebServer algunos handshakes TLS fallan de forma
    // intermitente ("wrong version number") antes de negociar.
    for ($attempt = 0; $attempt < 4; $attempt++) {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_FOLLOWLOCATION  => true,
            CURLOPT_TIMEOUT         => $timeout,
            CURLOPT_CONNECTTIMEOUT  => $timeout,
            CURLOPT_ENCODING        => '',
            CURLOPT_HTTPHEADER      => $h,
            CURLOPT_USERAGENT       => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',
            CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
            CURLOPT_FRESH_CONNECT   => true,
            CURLOPT_FORBID_REUSE    => true,
            CURLOPT_SSL_VERIFYPEER  => $hasCa,
        ];
        if (!$hasCa) {
            // Entorno local sin CA bundle válido: solo desactiva verificación aquí.
            $opts[CURLOPT_SSL_VERIFYHOST] = 0;
        }
        curl_setopt_array($ch, $opts);
        $raw   = curl_exec($ch);
        $code  = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errno = (int)curl_errno($ch);
        curl_close($ch);
        if ($raw !== false && $code >= 200 && $code < 300) return (string)$raw;
        if ($errno === 0 && $code >= 400 && $code < 500) return null; // 4xx no se reintenta
        if ($attempt < 3) usleep(350000);
    }
    return null;
}

function exrate_http_json(string $url, array $headers = [], int $timeout = 10): ?array {
    $raw = exrate_http_raw($url, array_merge(['Accept: application/json'], $headers), $timeout);
    if ($raw === null) return null;
    $j = json_decode($raw, true);
    return is_array($j) ? $j : null;
}

function exrate_num(?string $s): ?float {
    if ($s === null) return null;
    $s = str_replace(["\xc2\xa0", ' '], '', trim($s));
    $s = str_replace(',', '', $s);
    if ($s === '' || !is_numeric($s)) return null;
    return (float)$s;
}

function exrate_from_eltoque(string $token): ?float {
    $j = exrate_http_json('https://tasas.eltoque.com/v1/trmi', ['Authorization: Bearer ' . $token]);
    if (!$j) return null;
    // Formato defensivo: la doc no expone schema 200; buscamos USD en estructuras comunes.
    $candidates = [];
    $walk = function ($v) use (&$walk, &$candidates) {
        if (is_array($v)) {
            foreach ($v as $k => $vv) {
                if (is_string($k) && strtoupper($k) === 'USD') {
                    if (is_numeric($vv)) $candidates[] = (float)$vv;
                    elseif (is_array($vv)) {
                        foreach (['rate','mid','value','sell','compra','venta','precio','usd'] as $fk) {
                            if (isset($vv[$fk]) && is_numeric($vv[$fk])) $candidates[] = (float)$vv[$fk];
                        }
                    }
                }
                $walk($vv);
            }
        }
    };
    $walk($j);
    foreach ($candidates as $c) { if ($c > 0) return $c; }
    return null;
}

function exrate_token(): ?string {
    $env = getenv('ELTOQUE_API_TOKEN');
    if (is_string($env) && trim($env) !== '') return trim($env);
    $f = __DIR__ . '/eltoque-token.local.php';
    if (is_file($f)) {
        $v = require $f;
        if (is_string($v) && trim($v) !== '') return trim($v);
    }
    return null;
}

/* -------------------------------------------------------------------------
 * Modo completo (modal de precios): mercado informal + monedas digitales.
 * ---------------------------------------------------------------------- */

function exrate_all_cache_path(): string { return sys_get_temp_dir() . '/transnubet_eltoque_all.json'; }
function exrate_read_all_cache(int $maxAge = 600): ?array {
    $f = exrate_all_cache_path();
    if (!is_file($f)) return null;
    $raw = @file_get_contents($f);
    $j = $raw ? json_decode($raw, true) : null;
    if (!is_array($j) || !is_array($j['data'] ?? null) || !is_numeric($j['ts'] ?? null)) return null;
    if ((time() - (int)$j['ts']) > $maxAge) return null;
    return $j;
}
function exrate_write_all_cache(array $data): void {
    @file_put_contents(exrate_all_cache_path(), json_encode(['data' => $data, 'ts' => time()]), LOCK_EX);
}

function exrate_eltoque_all(string $token): ?array {
    $j = exrate_http_json('https://tasas.eltoque.com/v1/trmi', ['Authorization: Bearer ' . $token]);
    if (!$j || !isset($j['tasas']) || !is_array($j['tasas'])) return null;
    return $j;
}

function exrate_eltoque_day(string $token, string $day): ?array {
    $url = 'https://tasas.eltoque.com/v1/trmi'
        . '?date_from=' . rawurlencode($day . ' 00:00:01')
        . '&date_to=' . rawurlencode($day . ' 23:59:01');
    $j = exrate_http_json($url, ['Authorization: Bearer ' . $token]);
    if (!$j || !isset($j['tasas']) || !is_array($j['tasas'])) return null;
    return $j;
}

function exrate_prev_cache_path(): string { return sys_get_temp_dir() . '/transnubet_eltoque_prev_day.json'; }
function exrate_read_prev_cache(string $day, int $maxAge = 21600): ?array {
    $f = exrate_prev_cache_path();
    if (!is_file($f)) return null;
    $raw = @file_get_contents($f);
    $j = $raw ? json_decode($raw, true) : null;
    if (!is_array($j) || ($j['day'] ?? '') !== $day || !is_array($j['payload'] ?? null) || !is_numeric($j['ts'] ?? null)) return null;
    if ((time() - (int)$j['ts']) > $maxAge) return null;
    return $j['payload'];
}
function exrate_write_prev_cache(string $day, array $payload): void {
    @file_put_contents(exrate_prev_cache_path(), json_encode(['day' => $day, 'payload' => $payload, 'ts' => time()]), LOCK_EX);
}

/**
 * Scrapea la página pública de elTOQUE (a través del proxy de Google Translate,
 * porque el sitio bloquea peticiones directas con Cloudflare). Devuelve las tasas
 * del mercado informal y su variación de las últimas 24 h.
 */
function exrate_scrape_informal(): ?array {
    $url = 'https://eltoque-com.translate.goog/tasas-de-cambio-cuba?_x_tr_sl=es&_x_tr_tl=en&_x_tr_hl=es';
    $html = exrate_http_raw($url, ['Accept: text/html,application/xhtml+xml'], 25);
    if ($html === null) return null;

    $wanted = ['USD', 'EUR', 'MLC', 'CAD', 'MXN', 'ZELLE', 'CLA'];
    $rates = [];
    if (preg_match_all('~<tr\b[^>]*>(.*?)</tr>~is', $html, $rows)) {
        foreach ($rows[1] as $row) {
            if (!preg_match('~1&nbsp;([A-Z]{2,10})~', $row, $cm)) continue;
            $code = strtoupper($cm[1]);
            if (!in_array($code, $wanted, true)) continue;
            if (!preg_match('~font-extrabold text-lg[^>]*>\s*([0-9][0-9.,]*)\s*&nbsp;CUP~i', $row, $pm)) continue;
            $rate = exrate_num($pm[1]);
            if (!$rate || $rate <= 0) continue;

            $change = null;
            $dir = 'flat';
            if (preg_match('~border-b-(?:red|emerald)-600~', $row)) $dir = 'up';
            elseif (preg_match('~border-t-(?:red|emerald)-600~', $row)) $dir = 'down';
            if (preg_match('~<span data-astro-cid-p2ynkxt5>\s*([+-]?[0-9][0-9.,]*)\s*</span>~', $row, $vm)) {
                $change = exrate_num($vm[1]);
                if ($change !== null && strpos($vm[1], '-') !== false) $change = -abs($change);
            }

            $name = '';
            if (preg_match('~tooltip-bubble[^>]*>(.*?)</span>~is', $row, $dm)) {
                $name = trim(html_entity_decode(strip_tags($dm[1]), ENT_QUOTES, 'UTF-8'));
            }
            $rates[$code] = ['rate' => $rate, 'change' => $change, 'dir' => $dir, 'name' => $name];
        }
    }
    if (count($rates) < 5) return null;

    $time = null;
    $meses = ['enero'=>1,'febrero'=>2,'marzo'=>3,'abril'=>4,'mayo'=>5,'junio'=>6,'julio'=>7,'agosto'=>8,'septiembre'=>9,'octubre'=>10,'noviembre'=>11,'diciembre'=>12];
    if (preg_match('~(\d{1,2}) de ([a-záéíóú]+) de (\d{4}) a las (\d{1,2}):(\d{2})\s*([ap])\.\s*m\.~iu', $html, $tm)) {
        $mes = $meses[strtolower($tm[2])] ?? null;
        if ($mes) {
            $h = ((int)$tm[4]) % 12;
            if (strtolower($tm[6]) === 'p') $h += 12;
            $time = [
                'date'    => sprintf('%04d-%02d-%02d', (int)$tm[3], $mes, (int)$tm[1]),
                'hour'    => $h,
                'minutes' => (int)$tm[5],
            ];
        }
    }

    return ['rates' => $rates, 'time' => $time];
}

function exrate_build_all(): ?array {
    $token   = exrate_token();
    $today   = $token ? exrate_eltoque_all($token) : null;
    $page    = exrate_scrape_informal();
    $prevDayDate = date('Y-m-d', strtotime('-1 day'));
    $prevDay = null;
    if ($token) {
        $prevDay = exrate_read_prev_cache($prevDayDate, 21600);
        if ($prevDay === null) {
            $prevDay = exrate_eltoque_day($token, $prevDayDate);
            if ($prevDay) exrate_write_prev_cache($prevDayDate, $prevDay);
        }
    }

    $rates = [];
    $changes = [];

    if ($page) {
        foreach ($page['rates'] as $code => $r) {
            $rates[$code] = $r['rate'];
            if ($r['change'] !== null) $changes[$code] = $r['change'];
        }
    }

    // Completa con el TRMI oficial (USD/EUR/MLC) y añade monedas digitales.
    if ($today && isset($today['tasas']) && is_array($today['tasas'])) {
        $map = [
            'USD'         => 'USD',
            'EUR'         => 'ECU',
            'MLC'         => 'MLC',
            'USDT_TRC20'  => 'USDT_TRC20',
            'BTC'         => 'BTC',
            'BNB'         => 'BNB',
            'TRX'         => 'TRX',
        ];
        foreach ($map as $code => $apiKey) {
            if (isset($rates[$code])) continue;
            if (!isset($today['tasas'][$apiKey]) || !is_numeric($today['tasas'][$apiKey])) continue;
            $rates[$code] = (float)$today['tasas'][$apiKey];
        }
    }

    // Variaciones para las que no vienen de la página (monedas digitales).
    if ($prevDay && isset($prevDay['tasas']) && is_array($prevDay['tasas']) && $today) {
        foreach ($rates as $code => $rate) {
            if (isset($changes[$code])) continue;
            $apiKey = $code === 'EUR' ? 'ECU' : $code;
            if (isset($today['tasas'][$apiKey], $prevDay['tasas'][$apiKey])
                && is_numeric($today['tasas'][$apiKey]) && is_numeric($prevDay['tasas'][$apiKey])) {
                $changes[$code] = round((float)$today['tasas'][$apiKey] - (float)$prevDay['tasas'][$apiKey], 2);
            }
        }
    }

    if (count($rates) < 3) return null;

    $when = $page['time'] ?? null;
    if (!$when && $today) {
        $when = [
            'date'    => (string)($today['date'] ?? date('Y-m-d')),
            'hour'    => isset($today['hour']) ? (int)$today['hour'] : null,
            'minutes' => isset($today['minutes']) ? (int)$today['minutes'] : null,
        ];
    }

    return [
        'rates'      => $rates,
        'changes'    => $changes,
        'currency'   => 'CUP',
        'date'       => $when['date'] ?? date('Y-m-d'),
        'hour'       => $when['hour'] ?? null,
        'minutes'    => $when['minutes'] ?? null,
        'updated_at' => date('c'),
        'source'     => 'eltoque',
        'provider'   => 'elTOQUE',
        'market'     => 'Mercado Informal de Divisas',
    ];
}

/* -------------------------------------------------------------------------
 * Modo completo: ?all=1
 * ---------------------------------------------------------------------- */
$wantAll = isset($_GET['all']) && in_array(strtolower((string)$_GET['all']), ['1', 'true', 'yes'], true);
if ($wantAll) {
    $force = isset($_GET['force']) && in_array(strtolower((string)$_GET['force']), ['1', 'true', 'yes'], true);

    if (!$force) {
        $fresh = exrate_read_all_cache(600); // 10 min
        if ($fresh) {
            echo json_encode(['success' => true, 'data' => $fresh['data']], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $built = exrate_build_all();
    if ($built === null) {
        $stale = exrate_read_all_cache(21600); // hasta 6 h
        if ($stale) {
            $data = $stale['data'];
            $data['stale'] = true;
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;
        }
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'No se pudieron obtener las tasas en este momento.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    exrate_write_all_cache($built);
    echo json_encode(['success' => true, 'data' => $built], JSON_UNESCAPED_UNICODE);
    exit;
}

/* -------------------------------------------------------------------------
 * Modo simple: tasa USD -> CUP para las cards de precios.
 * ---------------------------------------------------------------------- */
$cached = exrate_read_cache(21600);
$rate = null; $source = 'fallback';
$token = exrate_token();
if ($token) {
    $r = exrate_from_eltoque($token);
    if ($r) { $rate = $r; $source = 'eltoque'; }
}
if ($rate === null && $cached) { $rate = $cached; $source = 'cache'; }
if ($rate === null) { $rate = 960.0; $source = 'default'; }
if ($rate > 0) exrate_write_cache($rate);

echo json_encode(['success' => true, 'data' => ['rate' => $rate, 'currency_from' => 'USD', 'currency_to' => 'CUP', 'source' => $source]], JSON_UNESCAPED_UNICODE);
