<?php
require_once __DIR__ . '/config.php';
// JSON-файл для fallback-хранилища, если SQLite недоступен
$JSON_PATH = __DIR__ . '/points.json';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Нормализация: поддержка подкаталогов
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
if ($scriptDir && $scriptDir !== '/') {
	$path = preg_replace('#^' . preg_quote($scriptDir, '#') . '#', '', $path);
}
// Поддержка путей вида /api.php/api/points
$path = preg_replace('#^/api\.php#', '', $path);
// Поддержка query-параметра ?route=/api/points
if (empty($path) || $path === '/' ) {
	if (!empty($_GET['route'])) {
		$path = (string)$_GET['route'];
	}
}

// Маршрутизация

// Аутентификация
if ($path === '/api/auth/register' && $method === 'POST') {
	auth_register();
} elseif ($path === '/api/auth/login' && $method === 'POST') {
	auth_login();

// Точки / погода
} elseif ($path === '/api/points' && $method === 'GET') {
	list_points();
} elseif ($path === '/api/points' && $method === 'POST') {
	create_point();
} elseif ($path === '/api/weather' && $method === 'GET') {
	get_weather();

// История
} elseif ($path === '/api/history' && $method === 'POST') {
	auth_required();
	create_history();
} elseif ($path === '/api/history' && $method === 'GET') {
	auth_required();
	list_history_current();
} elseif (preg_match('#^/api/history/(\d+)$#', $path, $m) && $method === 'GET') {
	list_history_by_user((int)$m[1]);

// Закладки
} elseif ($path === '/api/bookmarks' && $method === 'GET') {
	auth_required();
	list_bookmarks();
} elseif ($path === '/api/bookmarks' && $method === 'POST') {
	auth_required();
	create_bookmark();

// Заглушки NASA/CPTEC
} elseif ($path === '/api/nasa/opendap' && $method === 'GET') {
	nasa_proxy('opendap');
} elseif ($path === '/api/nasa/giovanni' && $method === 'GET') {
	nasa_proxy('giovanni');
} elseif ($path === '/api/nasa/datarods' && $method === 'GET') {
	nasa_proxy('datarods');
} elseif ($path === '/api/nasa/worldview' && $method === 'GET') {
	nasa_proxy('worldview');
} elseif ($path === '/api/nasa/earthdata' && $method === 'GET') {
	nasa_proxy('earthdata');
} else {
	json_response(['error' => 'Not Found', 'path' => $path], 404);
}

function list_points(): void {
	global $JSON_PATH;
	try {
		$pdo = db();
		$stmt = $pdo->query('SELECT id, lat, lng, temperature, humidity, description, created_at FROM points ORDER BY id DESC LIMIT 200');
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		json_response(['items' => $rows]);
		return;
	} catch (Throwable $e) {
		// Fallback: JSON
		if (!file_exists($JSON_PATH)) {
			json_response(['items' => []]);
		}
		$raw = file_get_contents($JSON_PATH);
		$data = json_decode($raw, true);
		if (!is_array($data)) { $data = []; }
		json_response(['items' => array_reverse(array_values($data))]);
	}
}

function create_point(): void {
	global $JSON_PATH;
	$input = json_decode(file_get_contents('php://input'), true);
	if (!is_array($input)) {
		$input = $_POST ?: [];
	}
	$lat = isset($input['lat']) ? (float)$input['lat'] : null;
	$lng = isset($input['lng']) ? (float)$input['lng'] : null;
	$temperature = isset($input['temperature']) ? (float)$input['temperature'] : null;
	$humidity = isset($input['humidity']) ? (float)$input['humidity'] : null;
	$description = isset($input['description']) ? trim((string)$input['description']) : null;
	if ($lat === null || $lng === null) {
		json_response(['error' => 'lat and lng are required'], 422);
	}
	try {
		$pdo = db();
		$stmt = $pdo->prepare('INSERT INTO points (lat, lng, temperature, humidity, description, created_at) VALUES (:lat, :lng, :temperature, :humidity, :description, :created_at)');
		$stmt->execute([
			':lat' => $lat,
			':lng' => $lng,
			':temperature' => $temperature,
			':humidity' => $humidity,
			':description' => $description,
			':created_at' => date('Y-m-d H:i:s'),
		]);
		json_response(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
		return;
	} catch (Throwable $e) {
		// Fallback: JSON append
		$items = [];
		if (file_exists($JSON_PATH)) {
			$items = json_decode((string)file_get_contents($JSON_PATH), true);
			if (!is_array($items)) { $items = []; }
		}
		$items[] = [
			'id' => count($items) + 1,
			'lat' => $lat,
			'lng' => $lng,
			'temperature' => $temperature,
			'humidity' => $humidity,
			'description' => $description,
			'created_at' => date('Y-m-d H:i:s'),
		];
		file_put_contents($JSON_PATH, json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
		json_response(['ok' => true, 'id' => count($items)]);
	}
}

function get_weather(): void {
    global $WEATHER_PROVIDER, $OWM_API_KEY;
    $lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
    $lng = isset($_GET['lng']) ? (float)$_GET['lng'] : null;
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    if ($lat === null || $lng === null) {
        if ($q !== '') {
            $geo = geocode_nominatim($q);
            if ($geo) { $lat = $geo['lat']; $lng = $geo['lng']; }
        }
    }
    if ($lat === null || $lng === null) {
        json_response(['error' => 'lat and lng are required or set q'], 422);
    }

    // Простое файловое кэширование на 5 секунд
    $cacheKey = 'w_' . md5($lat . '_' . $lng . '_' . $WEATHER_PROVIDER);
    $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $cacheKey . '.json';
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 5) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached)) { json_response($cached); }
    }

    if ($WEATHER_PROVIDER === 'openweather') {
        if ($OWM_API_KEY === '') {
            json_response(['error' => 'OWM API key is missing. Set OWM_API_KEY env or config.php'], 500);
        }
        $url = 'https://api.openweathermap.org/data/2.5/weather?lat=' . rawurlencode((string)$lat) . '&lon=' . rawurlencode((string)$lng) . '&appid=' . rawurlencode($OWM_API_KEY) . '&units=metric&lang=ru';
        $resp = http_get($url, 8);
        if ($resp === null) { json_response(['error' => 'weather provider unavailable (owm)'], 502); }
        $data = json_decode($resp, true);
        $main = $data['main'] ?? [];
        $weather0 = isset($data['weather'][0]) ? $data['weather'][0] : [];
        $out = [
            'lat' => $lat,
            'lng' => $lng,
            'temperature' => isset($main['temp']) ? (float)$main['temp'] : null,
            'humidity' => isset($main['humidity']) ? (float)$main['humidity'] : null,
            'weather_code' => $weather0['id'] ?? null,
            'description' => $weather0['description'] ?? null,
            'time' => isset($data['dt']) ? date('c', (int)$data['dt']) : null,
        ];
        @file_put_contents($cacheFile, json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        json_response($out);
        return;
    }

    // Fallback на Open-Meteо (попросим и hourly как резерв)
    $url = 'https://api.open-meteo.com/v1/forecast?latitude=' . rawurlencode((string)$lat)
        . '&longitude=' . rawurlencode((string)$lng)
        . '&current=temperature_2m,relative_humidity_2m,weather_code'
        . '&hourly=temperature_2m,relative_humidity_2m'
        . '&forecast_days=1&timezone=auto';
    $resp = http_get($url, 8);
    if ($resp === null) {
        // Авто‑fallback на OpenWeatherMap, если доступен API ключ
        if (!empty($OWM_API_KEY)) {
            $owmUrl = 'https://api.openweathermap.org/data/2.5/weather?lat=' . rawurlencode((string)$lat) . '&lon=' . rawurlencode((string)$lng) . '&appid=' . rawurlencode($OWM_API_KEY) . '&units=metric&lang=ru';
            $owmResp = http_get($owmUrl, 8);
            if ($owmResp !== null) {
                $owm = json_decode($owmResp, true);
                $main = $owm['main'] ?? [];
                $weather0 = isset($owm['weather'][0]) ? $owm['weather'][0] : [];
                $out = [
                    'lat' => $lat,
                    'lng' => $lng,
                    'temperature' => isset($main['temp']) ? (float)$main['temp'] : null,
                    'humidity' => isset($main['humidity']) ? (float)$main['humidity'] : null,
                    'weather_code' => $weather0['id'] ?? null,
                    'description' => $weather0['description'] ?? null,
                    'time' => isset($owm['dt']) ? date('c', (int)$owm['dt']) : null,
                ];
                @file_put_contents($cacheFile, json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
                json_response($out);
                return;
            }
        }
        json_response(['error' => 'weather provider unavailable (open-meteo)'], 502);
    }
    $data = json_decode($resp, true);
    $current = $data['current'] ?? null;
    // Попробуем получить из current, иначе возьмём из hourly ближайший к текущему часу
    $temperature = $current['temperature_2m'] ?? null;
    $humidity = $current['relative_humidity_2m'] ?? null;
    if ($temperature === null || $humidity === null) {
        $hourly = $data['hourly'] ?? null;
        if (is_array($hourly) && isset($hourly['time']) && isset($hourly['temperature_2m']) && isset($hourly['relative_humidity_2m'])) {
            $idx = 0;
            // найдём индекс времени, ближайший к now
            $nowIso = isset($current['time']) ? $current['time'] : gmdate('Y-m-d\TH:00');
            $times = $hourly['time'];
            for ($i = 0; $i < count($times); $i++) { if ($times[$i] === $nowIso) { $idx = $i; break; } }
            $temperature = $temperature ?? ($hourly['temperature_2m'][$idx] ?? null);
            $humidity = $humidity ?? ($hourly['relative_humidity_2m'][$idx] ?? null);
        }
    }
    if ($temperature === null && $humidity === null) { json_response(['error' => 'invalid weather data (no temp/humidity)'], 502); }
    $out = [
        'lat' => $lat,
        'lng' => $lng,
        'temperature' => $temperature,
        'humidity' => $humidity,
        'weather_code' => $current['weather_code'] ?? null,
        'time' => $current['time'] ?? null,
    ];
    @file_put_contents($cacheFile, json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    json_response($out);
}

/**
 * Универсальный HTTP GET с приоритетом cURL (Windows-friendly)
 */
function http_get(string $url, int $timeout = 10): ?string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'KazP/1.0',
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code >= 400) {
            return null;
        }
        return $body;
    }
    $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'header' => "User-Agent: KazP/1.0\r\n"]]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) { return null; }
    return $resp;
}


// ===== Аутентификация =====
function auth_register(): void {
	$input = json_input();
	$email = strtolower(trim((string)($input['email'] ?? '')));
	$password = (string)($input['password'] ?? '');
	if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
		json_response(['error' => 'invalid email or password too short'], 422);
	}
	$pdo = db();
	$stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
	$stmt->execute([':email' => $email]);
	if ($stmt->fetch()) { json_response(['error' => 'email already registered'], 409); }
	$now = date('Y-m-d H:i:s');
	$hash = password_hash($password, PASSWORD_DEFAULT);
	$pdo->prepare('INSERT INTO users (email, password_hash, created_at, updated_at) VALUES (:email, :hash, :c, :u)')
		->execute([':email' => $email, ':hash' => $hash, ':c' => $now, ':u' => $now]);
	$userId = (int)$pdo->lastInsertId();
	$token = issue_token($pdo, $userId);
	json_response(['ok' => true, 'user' => ['id' => $userId, 'email' => $email], 'token' => $token]);
}

function auth_login(): void {
	$input = json_input();
	$email = strtolower(trim((string)($input['email'] ?? '')));
	$password = (string)($input['password'] ?? '');
	$pdo = db();
	$stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = :email');
	$stmt->execute([':email' => $email]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$row || !password_verify($password, $row['password_hash'])) {
		json_response(['error' => 'invalid credentials'], 401);
	}
	$token = issue_token($pdo, (int)$row['id']);
	json_response(['ok' => true, 'user' => ['id' => (int)$row['id'], 'email' => $email], 'token' => $token]);
}

function issue_token(PDO $pdo, int $userId): string {
	$token = bin2hex(random_bytes(32));
	$now = date('Y-m-d H:i:s');
	$exp = date('Y-m-d H:i:s', time() + 60*60*24*30); // 30 дней
	$pdo->prepare('INSERT INTO tokens (user_id, token, expires_at, created_at) VALUES (:u, :t, :e, :c)')
		->execute([':u' => $userId, ':t' => $token, ':e' => $exp, ':c' => $now]);
	return $token;
}

function auth_required(): array {
	$hdr = auth_header_bearer();
	if (!$hdr) { json_response(['error' => 'authorization required'], 401); }
	$pdo = db();
	$stmt = $pdo->prepare('SELECT tokens.user_id as uid, users.email as email FROM tokens JOIN users ON users.id = tokens.user_id WHERE token = :t AND expires_at > :now');
	$stmt->execute([':t' => $hdr, ':now' => date('Y-m-d H:i:s')]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$row) { json_response(['error' => 'invalid or expired token'], 401); }
	return ['id' => (int)$row['uid'], 'email' => $row['email']];
}

function auth_header_bearer(): ?string {
	$headers = function_exists('getallheaders') ? getallheaders() : [];
	$auth = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
	if (preg_match('/^Bearer\s+([A-Za-z0-9]+)$/', trim((string)$auth), $m)) { return $m[1]; }
	return null;
}

function json_input(): array {
	$raw = file_get_contents('php://input');
	$data = json_decode($raw, true);
	if (!is_array($data) || !$data) { $data = $_POST ?: []; }
	return $data;
}

// ===== История =====
function create_history(): void {
	$user = auth_required();
	$input = json_input();
	$endpoint = (string)($input['endpoint'] ?? '');
	$params = isset($input['params']) ? $input['params'] : [];
	$resultMeta = isset($input['result']) ? $input['result'] : null;
	if ($endpoint === '') { json_response(['error' => 'endpoint is required'], 422); }
	$pdo = db();
	$pdo->prepare('INSERT INTO history (user_id, endpoint, params_json, result_meta_json, created_at) VALUES (:u, :e, :p, :r, :c)')
		->execute([
			':u' => $user['id'],
			':e' => $endpoint,
			':p' => json_encode($params, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
			':r' => $resultMeta !== null ? json_encode($resultMeta, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,
			':c' => date('Y-m-d H:i:s'),
		]);
	json_response(['ok' => true]);
}

function list_history_current(): void {
	$user = auth_required();
	list_history_by_user($user['id']);
}

function list_history_by_user(int $userId): void {
	$pdo = db();
	$stmt = $pdo->prepare('SELECT id, endpoint, params_json, result_meta_json, created_at FROM history WHERE user_id = :u ORDER BY id DESC LIMIT 200');
	$stmt->execute([':u' => $userId]);
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	json_response(['items' => $rows]);
}

// ===== Закладки =====
function list_bookmarks(): void {
	$user = auth_required();
	$pdo = db();
	$stmt = $pdo->prepare('SELECT id, name, lat, lng, created_at FROM bookmarks WHERE user_id = :u ORDER BY id DESC');
	$stmt->execute([':u' => $user['id']]);
	json_response(['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function create_bookmark(): void {
	$user = auth_required();
	$input = json_input();
	$name = trim((string)($input['name'] ?? ''));
	$lat = isset($input['lat']) ? (float)$input['lat'] : null;
	$lng = isset($input['lng']) ? (float)$input['lng'] : null;
	if ($name === '' || $lat === null || $lng === null) { json_response(['error' => 'name, lat, lng are required'], 422); }
	$pdo = db();
	$pdo->prepare('INSERT INTO bookmarks (user_id, name, lat, lng, created_at) VALUES (:u, :n, :lat, :lng, :c)')
		->execute([':u' => $user['id'], ':n' => $name, ':lat' => $lat, ':lng' => $lng, ':c' => date('Y-m-d H:i:s')]);
	json_response(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
}

// ===== NASA/CPTEC заглушки/прокси =====
function nasa_proxy(string $kind): void {
	$target = (string)($_GET['url'] ?? '');
	if ($target === '') {
		json_response(['kind' => $kind, 'hint' => 'pass ?url=... to proxy; this is a lightweight helper for client-side fetch avoiding CORS'], 200);
	}
	$ctx = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
	$resp = @file_get_contents($target, false, $ctx);
	if ($resp === false) { json_response(['error' => 'upstream failed', 'url' => $target], 502); }
	$ctype = 'application/octet-stream';
	foreach ($http_response_header ?? [] as $h) { if (stripos($h, 'Content-Type:') === 0) { $ctype = trim(substr($h, 13)); break; } }
	header('Content-Type: ' . $ctype);
	echo $resp;
	exit;
}

// ===== Геокодер (Nominatim) =====
function geocode_nominatim(string $q): ?array {
	$url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' . rawurlencode($q);
	$ctx = stream_context_create(['http' => ['timeout' => 8, 'header' => "User-Agent: KazP/1.0\r\n"]]);
	$resp = @file_get_contents($url, false, $ctx);
	if ($resp === false) { return null; }
	$arr = json_decode($resp, true);
	if (!is_array($arr) || empty($arr)) { return null; }
	$it = $arr[0];
	return ['lat' => isset($it['lat']) ? (float)$it['lat'] : null, 'lng' => isset($it['lon']) ? (float)$it['lon'] : null];
}
