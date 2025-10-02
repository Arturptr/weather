<?php
// Получаем токен из переменной окружения
$token = getenv("NASA_TOKEN");
if (!$token) {
    die("❌ Ошибка: переменная окружения NASA_TOKEN не установлена.\n");
}

// Координаты Нью-Йорка
$latitude = 40.7128;
$longitude = -74.0060;

// Даты (последние 10 дней)
$startDate = date('Ymd', strtotime('-10 days'));
$endDate   = date('Ymd', strtotime('-1 days'));

$url = "https://power.larc.nasa.gov/api/temporal/daily/point"
    . "?parameters=T2M,PRECTOT,WS10M"
    . "&community=AG"
    . "&longitude=$longitude"
    . "&latitude=$latitude"
    . "&start=$startDate"
    . "&end=$endDate"
    . "&format=JSON";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Добавляем авторизацию
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $token"
]);

$response = curl_exec($ch);

if ($response === false) {
    die("❌ Ошибка cURL: " . curl_error($ch) . "\n");
}

curl_close($ch);

// Сохраняем JSON в файл nasa_meteo.json
file_put_contents(__DIR__ . '/nasa_meteo.json', $response);

echo "✅ Данные сохранены в nasa_meteo.json\n";
?>
