<?php
/**
 * Proxy de tasa USD->CUP.
 * Prioridad: ElToque (si hay token) -> CyberForja -> fallback fijo.
 * Configura el token en api/eltoque-token.local.php (no versionar) devolviendo el token:
 *   <?php return 'TU_TOKEN';
 */
header('Content-Type: application/json; charset=utf-8');
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
function exrate_http_json(string $url, array $headers = [], int $timeout = 6): ?array {
    $h = array_merge(['Accept: application/json'], $headers);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $h,
            CURLOPT_USERAGENT => 'TransNuBeT-rate-proxy/1.0',
        ]);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($raw === false || $code < 200 || $code >= 300) return null;
    } else {
        $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'ignore_errors' => true, 'header' => implode("\r\n", $h)], 'https' => ['timeout' => $timeout, 'ignore_errors' => true, 'header' => implode("\r\n", $h)]]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) return null;
    }
    $j = json_decode($raw, true);
    return is_array($j) ? $j : null;
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
function exrate_from_cyberforja(): ?float {
    $j = exrate_http_json('https://cyberforja.com/api/exchange-rate');
    if (is_array($j) && !empty($j['success']) && isset($j['data']['rate']) && is_numeric($j['data']['rate'])) {
        $r = (float)$j['data']['rate'];
        return $r > 0 ? $r : null;
    }
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

$cached = exrate_read_cache(21600);
$rate = null; $source = 'fallback';
$token = exrate_token();
if ($token) {
    $r = exrate_from_eltoque($token);
    if ($r) { $rate = $r; $source = 'eltoque'; }
}
if ($rate === null) {
    $r = exrate_from_cyberforja();
    if ($r) { $rate = $r; $source = 'cyberforja'; }
}
if ($rate === null && $cached) { $rate = $cached; $source = 'cache'; }
if ($rate === null) { $rate = 960.0; $source = 'default'; }
if ($rate > 0) exrate_write_cache($rate);

echo json_encode(['success' => true, 'data' => ['rate' => $rate, 'currency_from' => 'USD', 'currency_to' => 'CUP', 'source' => $source]], JSON_UNESCAPED_UNICODE);
