<?php
// get_forecast.php
declare(strict_types=1);

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

// Если preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- (dev) для отладки можно временно включить ошибки
// error_reporting(E_ALL); ini_set('display_errors', 1);

// Получаем данные из POST или JSON body
$input = [];
// form-urlencoded / multipart
if (!empty($_POST)) {
    $input = $_POST;
} else {
    // возможно JSON в body
    $raw = trim(file_get_contents('php://input'));
    if ($raw !== '') {
        $json = json_decode($raw, true);
        if (is_array($json)) $input = $json;
    }
}

$city  = isset($input['city'])  ? trim((string)$input['city'])  : '';
$day   = isset($input['day'])   ? trim((string)$input['day'])   : '';
$month = isset($input['month']) ? trim((string)$input['month']) : '';
$year  = isset($input['year'])  ? trim((string)$input['year'])  : '';

// простая валидация
if ($city === '' || $day === '' || $month === '' || $year === '') {
    echo json_encode(['status'=>0,'message'=>'Недостаточно данных. Передайте city, day, month, year'], JSON_UNESCAPED_UNICODE);
    exit;
}

// нормализаторы
function lower(string $s): string {
    if (function_exists('mb_strtolower')) return mb_strtolower($s, 'UTF-8');
    return strtolower($s);
}
function normInt(string $s): int {
    // убираем ведущие нули, нечисловые символы
    return intval(preg_replace('/\D+/', '', $s));
}

// путь к БД
$dbFile = __DIR__ . '/weather_db.json';
if (!file_exists($dbFile)) {
    // создаём пустой файл (при желании можно заполнить демонстрацией)
    file_put_contents($dbFile, json_encode(new stdClass(), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
}

$raw = file_get_contents($dbFile);
$data = json_decode($raw, true);
if (!is_array($data)) $data = [];

// целевые нормализованные значения для поиска
$city_l = lower($city);
$day_i  = normInt($day);
$month_i= normInt($month);
$year_i = normInt($year);

// Функция разбора ключа "city day month year", где city может иметь пробелы
function parse_key(string $k): array {
    $parts = preg_split('/\s+/', trim($k));
    if (count($parts) < 4) return ['city'=>'','day'=>0,'month'=>0,'year'=>0];
    $year = array_pop($parts);
    $month = array_pop($parts);
    $day = array_pop($parts);
    $city = implode(' ', $parts);
    return ['city'=>$city, 'day'=>$day, 'month'=>$month, 'year'=>$year];
}

// Поиск (регистронезависимый, игнор ведущих нулей)
$found = null;
foreach ($data as $k => $v) {
    $parts = parse_key($k);
    if ($parts['city'] === '') continue;
    if (lower($parts['city']) === $city_l
        && normInt($parts['day']) === $day_i
        && normInt($parts['month']) === $month_i
        && normInt($parts['year']) === $year_i) {
        $found = $v;
        break;
    }
}

if ($found !== null) {
    echo json_encode(['status'=>1, 'exists'=>1, 'message'=>'Прогноз найден', 'data'=>$found], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['status'=>1, 'exists'=>0, 'message'=>'Данных в базе для этой даты нет'], JSON_UNESCAPED_UNICODE);
}
