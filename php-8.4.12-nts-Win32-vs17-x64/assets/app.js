// KazP Frontend
(function() {
    var map = L.map('map').setView([51.16, 71.47], 5);
	L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18 }).addTo(map);

    var marker = null;
    var cityMarkers = null; // отключено
	var latEl = document.getElementById('lat');
	var lngEl = document.getElementById('lng');
	var tEl = document.getElementById('temperature');
	var hEl = document.getElementById('humidity');
	var dEl = document.getElementById('description');
	var saveBtn = document.getElementById('saveBtn');
	var clearBtn = document.getElementById('clearBtn');
	var statusEl = document.getElementById('status');
	var recentEl = document.getElementById('recentList');
	var yearEl = document.getElementById('year');
	if (yearEl) { yearEl.textContent = new Date().getFullYear(); }

	function setStatus(msg, isError) {
		if (!statusEl) return;
		statusEl.textContent = msg || '';
		statusEl.style.color = isError ? '#fda4af' : '#94a3b8';
	}

    function placeMarker(lat, lng, weather) {
        if (marker) { map.removeLayer(marker); }
        marker = L.marker([lat, lng]).addTo(map);
        var popup = '';
        if (weather && (typeof weather.temperature !== 'undefined' || typeof weather.humidity !== 'undefined')) {
            popup = '🌡 ' + (weather.temperature ?? '—') + '°C<br>💧 ' + (weather.humidity ?? '—') + '%';
            if (weather.description) { popup += '<br>' + weather.description; }
        }
        if (popup) { marker.bindPopup(popup).openPopup(); }
        map.setView([lat, lng], 9);
    }

    function renderRecent(items) {
		recentEl.innerHTML = '';
		items.forEach(function(p) {
			var div = document.createElement('div');
			div.className = 'kp-item';
			div.innerHTML = '<div><strong>📍 ' + p.lat.toFixed(4) + ', ' + p.lng.toFixed(4) + '</strong></div>' +
				'<div class="meta">🌡 ' + (p.temperature ?? '—') + '°C · 💧 ' + (p.humidity ?? '—') + '%</div>' +
				(p.description ? ('<div>' + escapeHtml(p.description) + '</div>') : '') +
				'<div class="meta">⏰ ' + p.created_at + '</div>';
			div.addEventListener('click', function() {
				placeMarker(p.lat, p.lng);
			});
			recentEl.appendChild(div);
		});
    }

    // отключаем динамические города и возвращаем базовую логику

	function escapeHtml(s) {
		return (s || '').replace(/[&<>"']/g, function(c){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]); });
	}

	function fetchRecent() {
		return fetch('api.php?route=/api/points').then(function(r){ return r.json(); }).then(function(data){
			renderRecent(data.items || []);
			// Добавим маркеры сохранённых точек
			(data.items || []).forEach(function(p){
				L.marker([p.lat, p.lng]).addTo(map).bindPopup('🌡 ' + (p.temperature ?? '—') + '°C<br>💧 ' + (p.humidity ?? '—') + '%<br>⏰ ' + p.created_at);
			});
		}).catch(function(){ setStatus('Не удалось загрузить список', true); });
	}

    function fetchWeather(lat, lng) {
		setStatus('Загружаю погоду…');
		return fetch('api.php?route=/api/weather&lat=' + encodeURIComponent(lat) + '&lng=' + encodeURIComponent(lng))
            .then(function(r){ return r.json(); })
			.then(function(w){
                if (w.error) { setStatus('Ошибка: ' + w.error, true); return; }
                if (w && typeof w.temperature !== 'undefined') {
					tEl.value = w.temperature;
					tEl.setAttribute('readonly', 'readonly');
				}
				if (w && typeof w.humidity !== 'undefined') {
					hEl.value = w.humidity;
					hEl.setAttribute('readonly', 'readonly');
				}
                placeMarker(lat, lng, w);
				// попытка сохранить историю, если есть токен
				var token = localStorage.getItem('kp_token');
				if (token) {
					fetch('api.php?route=/api/history', {
						method: 'POST',
						headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
						body: JSON.stringify({ endpoint: '/api/weather', params: { lat: lat, lng: lng }, result: { temperature: w.temperature, humidity: w.humidity, time: w.time } })
					}).catch(function(){});
				}
				setStatus('Погода обновлена');
			})
            .catch(function(e){ setStatus('Ошибка запроса: ' + (e && e.message ? e.message : 'unknown'), true); });
	}

	map.on('click', function(e) {
		var lat = +e.latlng.lat.toFixed(6);
		var lng = +e.latlng.lng.toFixed(6);
		latEl.value = lat; lngEl.value = lng;
        fetchWeather(lat, lng);
	});

	saveBtn.addEventListener('click', function() {
		var lat = parseFloat(latEl.value);
		var lng = parseFloat(lngEl.value);
		if (!isFinite(lat) || !isFinite(lng)) { setStatus('Сначала выберите точку на карте', true); return; }
		var payload = {
			lat: lat,
			lng: lng,
			temperature: tEl.value ? parseFloat(tEl.value) : null,
			humidity: hEl.value ? parseFloat(hEl.value) : null,
			description: dEl.value || null
		};
		setStatus('Сохраняю…');
		fetch('api.php?route=/api/points', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
			.then(function(r){ return r.json(); })
			.then(function(){ setStatus('Сохранено'); fetchRecent(); })
			.catch(function(){ setStatus('Ошибка сохранения', true); });
	});

	clearBtn.addEventListener('click', function(){
		latEl.value = ''; lngEl.value = ''; tEl.value = ''; hEl.value = ''; dEl.value = '';
		if (marker) { map.removeLayer(marker); marker = null; }
		setStatus('');
	});

	// Периодическое обновление текущей точки (если выбрана)
    setInterval(function(){
        var lat = parseFloat(latEl.value); var lng = parseFloat(lngEl.value);
        if (isFinite(lat) && isFinite(lng)) { fetchWeather(lat, lng); }
    }, 60 * 1000); // каждые 60 сек

    // первичная отрисовка
    fetchRecent();
})();


