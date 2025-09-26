<!DOCTYPE html>
<html lang="ru">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>KazP • Карта погоды</title>
	<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
	<link rel="stylesheet" href="assets/style.css"/>
	<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
</head>
<body>
	<header class="kp-header">
		<div class="kp-container">
			<div class="kp-brand">Kazp</div>
			<nav class="kp-nav">
				<a href="#" class="active">Главная</a>
				<a href="#forecast">Прогнозы</a>
				<a href="#map">Динамическая карта</a>
				<a href="#disasters">Катаклизмы</a>
				<a href="#news">Новости погоды</a>
				<a href="login.html" id="navLogin">Вход</a>
				<a href="register.html" id="navRegister">Регистрация</a>
				<a href="#" id="navLogout" style="display:none;" onclick="window.kpLogout&&window.kpLogout();return false;">Выйти</a>
			</nav>
		</div>
	</header>

	<main class="kp-container">
		<section class="kp-hero">
			<div class="kp-glass">
				<h1>Kazp — погодная карта</h1>
				<p>Кликните по карте, чтобы получить текущую погоду и сохранить точку.</p>
			</div>
		</section>

		<section class="kp-grid">
			<div class="kp-card kp-map" id="mapSection">
				<div id="map"></div>
			</div>
			<div class="kp-card kp-form">
				<h2>Данные точки</h2>
				<form id="pointForm">
					<div class="kp-row">
						<input type="text" id="lat" placeholder="Широта" readonly required>
						<input type="text" id="lng" placeholder="Долгота" readonly required>
					</div>
					<div class="kp-row">
						<input type="number" id="temperature" placeholder="Температура (°C)" required>
						<input type="number" id="humidity" placeholder="Влажность (%)" required>
					</div>
					<textarea id="description" placeholder="Комментарий (необязательно)" rows="3"></textarea>
					<div class="kp-actions">
						<button type="button" id="saveBtn">Сохранить</button>
						<button type="button" id="clearBtn" class="secondary">Очистить</button>
					</div>
				</form>
				<div id="status" class="kp-status" aria-live="polite"></div>
			</div>
		</section>

		<section id="recent" class="kp-card kp-recent">
			<h2>Недавно просмотренные</h2>
			<div id="recentList" class="kp-list"></div>
		</section>

		<section id="forecast" class="kp-card">
			<h2>Прогнозы</h2>
			<p>Раздел в разработке. Здесь будут краткосрочные и долгосрочные прогнозы.</p>
		</section>

		<section id="disasters" class="kp-card">
			<h2>Катаклизмы</h2>
			<p>Отслеживание штормов, шквалов, пыльных бурь и аномалий.</p>
		</section>

		<section id="news" class="kp-card">
			<h2>Новости погоды</h2>
			<p>Актуальные заметки синоптиков и обновления проекта Kazp.</p>
		</section>
	</main>

	<footer class="kp-footer">
		<div class="kp-container">© <span id="year"></span> KazP Team. Сделано с ❤️.</div>
	</footer>

	<script src="assets/app.js"></script>
	<script src="assets/user.js"></script>
</body>
</html>


