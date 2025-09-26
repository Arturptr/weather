(function(){
	var navLogin = document.getElementById('navLogin');
	var navRegister = document.getElementById('navRegister');
	var navLogout = document.getElementById('navLogout');
	function refresh(){
		var token = localStorage.getItem('kp_token');
		if (token) {
			if (navLogin) navLogin.style.display = 'none';
			if (navRegister) navRegister.style.display = 'none';
			if (navLogout) navLogout.style.display = '';
		} else {
			if (navLogin) navLogin.style.display = '';
			if (navRegister) navRegister.style.display = '';
			if (navLogout) navLogout.style.display = 'none';
		}
	}
	window.kpLogout = function(){
		localStorage.removeItem('kp_token');
		localStorage.removeItem('kp_user');
		refresh();
	};
	refresh();
})();


