// KazP Auth
(function(){
	function byId(id){ return document.getElementById(id); }
	var form = byId('authForm');
	var mode = form ? (form.getAttribute('data-mode') || 'login') : 'login';
	var statusEl = byId('authStatus');
	function setStatus(msg, err){ if(statusEl){ statusEl.textContent = msg||''; statusEl.style.color = err?'#fda4af':'#94a3b8'; } }
	if (!form) return;
	form.addEventListener('submit', function(e){
		e.preventDefault();
		var email = byId('email').value.trim();
		var password = byId('password').value;
		if (!email || !password) { setStatus('Заполните email и пароль', true); return; }
		setStatus('Отправка…');
		var url = mode === 'register' ? 'api.php?route=/api/auth/register' : 'api.php?route=/api/auth/login';
		fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email: email, password: password }) })
			.then(function(r){ return r.json(); })
			.then(function(res){
				if (res && res.token) {
					localStorage.setItem('kp_token', res.token);
					localStorage.setItem('kp_user', JSON.stringify(res.user||{}));
					setStatus('Готово');
					setTimeout(function(){ window.location.href = 'index.php'; }, 300);
				} else if (res && res.error) {
					setStatus(res.error, true);
				} else {
					setStatus('Неизвестный ответ', true);
				}
			})
			.catch(function(){ setStatus('Ошибка сети', true); });
	});
})();


