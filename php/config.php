<?php
// Конфигурация приложения KazP

// Путь к базе данных SQLite
$DB_PATH = __DIR__ . DIRECTORY_SEPARATOR . 'data.sqlite';

// Источник погоды по умолчанию: Open-Meteo (без API-ключа)
// Провайдер погоды: openweather | open-meteo
$WEATHER_PROVIDER = 'open-meteo';

// API-ключ OpenWeatherMap. Задайте через переменную окружения OWM_API_KEY или здесь.
$OWM_API_KEY = getenv('OWM_API_KEY') ?: '';

// Ограничение на количество элементов в «Недавно просмотренные»
$RECENT_LIMIT = 20;

// Разрешить запись и создание таблиц при первом запуске
$ALLOW_MIGRATIONS = true;

/**
 * Возвращает подключение к SQLite (PDO)
 */
function db(): PDO {
	global $DB_PATH, $ALLOW_MIGRATIONS;
	$pdo = new PDO('sqlite:' . $DB_PATH);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$pdo->exec('PRAGMA journal_mode=WAL;');
	$pdo->exec('PRAGMA foreign_keys=ON;');
	if ($ALLOW_MIGRATIONS) {
		migrate($pdo);
	}
	return $pdo;
}

/**
 * Создаёт таблицы при первом запуске
 */
function migrate(PDO $pdo): void {
	$pdo->exec(
		'CREATE TABLE IF NOT EXISTS points (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			lat REAL NOT NULL,
			lng REAL NOT NULL,
			temperature REAL,
			humidity REAL,
			description TEXT,
			created_at TEXT NOT NULL
		)'
	);

	// Пользователи
	$pdo->exec(
		'CREATE TABLE IF NOT EXISTS users (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			email TEXT NOT NULL UNIQUE,
			password_hash TEXT NOT NULL,
			created_at TEXT NOT NULL,
			updated_at TEXT NOT NULL
		)'
	);

	// Токены доступа (Bearer)
	$pdo->exec(
		'CREATE TABLE IF NOT EXISTS tokens (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			user_id INTEGER NOT NULL,
			token TEXT NOT NULL UNIQUE,
			expires_at TEXT NOT NULL,
			created_at TEXT NOT NULL,
			FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
		)'
	);
	$pdo->exec('CREATE INDEX IF NOT EXISTS idx_tokens_user_id ON tokens(user_id)');
	$pdo->exec('CREATE INDEX IF NOT EXISTS idx_tokens_expires_at ON tokens(expires_at)');

	// История запросов
	$pdo->exec(
		'CREATE TABLE IF NOT EXISTS history (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			user_id INTEGER NOT NULL,
			endpoint TEXT NOT NULL,
			params_json TEXT NOT NULL,
			result_meta_json TEXT,
			created_at TEXT NOT NULL,
			FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
		)'
	);
	$pdo->exec('CREATE INDEX IF NOT EXISTS idx_history_user_id ON history(user_id)');
	$pdo->exec('CREATE INDEX IF NOT EXISTS idx_history_created_at ON history(created_at)');

	// Закладки (сохранённые места)
	$pdo->exec(
		'CREATE TABLE IF NOT EXISTS bookmarks (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			user_id INTEGER NOT NULL,
			name TEXT NOT NULL,
			lat REAL NOT NULL,
			lng REAL NOT NULL,
			created_at TEXT NOT NULL,
			FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
		)'
	);
	$pdo->exec('CREATE INDEX IF NOT EXISTS idx_bookmarks_user_id ON bookmarks(user_id)');
}

/**
 * Ответ JSON и выход
 */
function json_response($data, int $status = 200): void {
	header('Content-Type: application/json; charset=utf-8');
	http_response_code($status);
	echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}



