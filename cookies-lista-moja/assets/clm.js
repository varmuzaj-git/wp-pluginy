/* Cookies lišta moja */
(function () {
	'use strict';
	var d = document, cfgEl = d.getElementById('clm-config'), C;
	if (!cfgEl) { return; }
	try { C = JSON.parse(cfgEl.textContent); } catch (e) { return; }

	var NAME = 'clm_consent', OPTIONAL = ['statistics', 'marketing'];
	var root, banner, modal, lastFocus, current = readConsent();

	/* ---------- souhlas v cookie ---------- */
	function readConsent() {
		var m = d.cookie.match(/(?:^|; )clm_consent=([^;]*)/), p;
		if (!m) { return null; }
		try { p = decodeURIComponent(m[1]).split('.'); } catch (e) { return null; } // poškozená cookie = bez souhlasu
		if (p[0] !== String(C.v)) { return null; }
		return (p[1] ? p[1].split('-') : []).filter(function (c) { return OPTIONAL.indexOf(c) > -1; });
	}
	function writeConsent(cats) {
		var v = C.v + '.' + cats.join('-') + '.' + Math.floor(Date.now() / 1000);
		var exp = new Date(Date.now() + (C.exp || 180) * 864e5).toUTCString();
		d.cookie = NAME + '=' + encodeURIComponent(v) + '; expires=' + exp + '; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
	}

	/* ---------- smazání cookies odvolaných kategorií ---------- */
	function deleteCookies(cat) {
		var pats = (C.cookies && C.cookies[cat]) || [];
		if (!pats.length) { return; }
		var parts = location.hostname.split('.'), domains = [''], paths = ['/'], i;
		for (i = 0; i < parts.length - 1; i++) {
			var dm = parts.slice(i).join('.');
			domains.push(dm, '.' + dm);
		}
		var segs = location.pathname.split('/').filter(Boolean), acc = '';
		for (i = 0; i < segs.length; i++) { acc += '/' + segs[i]; paths.push(acc, acc + '/'); }
		d.cookie.split(/;\s*/).forEach(function (pair) {
			var name = pair.split('=')[0];
			if (!name) { return; }
			var hit = pats.some(function (p) {
				return p.slice(-1) === '*' ? name.indexOf(p.slice(0, -1)) === 0 : name === p;
			});
			if (!hit) { return; }
			domains.forEach(function (dm) {
				paths.forEach(function (pt) {
					d.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=' + pt + (dm ? '; domain=' + dm : '');
				});
			});
		});
	}

	/* ---------- odblokování skriptů a iframů ---------- */
	function unblock(cats) {
		var list = [].slice.call(d.querySelectorAll('script[type="text/plain"][data-clm-cat]')).filter(function (s) {
			return cats.indexOf(s.getAttribute('data-clm-cat')) > -1;
		});
		var noModule = 'noModule' in d.createElement('script');
		(function next(i) {
			if (i >= list.length) { return; }
			var o = list[i], s = d.createElement('script');
			// Prohlížeč s podporou modulů skript "nomodule" nespustí a nevyvolá ani load/error.
			if (noModule && o.hasAttribute('nomodule')) { o.parentNode.removeChild(o); next(i + 1); return; }
			[].slice.call(o.attributes).forEach(function (a) {
				if (a.name !== 'type' && a.name.indexOf('data-clm') !== 0) { s.setAttribute(a.name, a.value); }
			});
			if (o.getAttribute('data-clm-type')) { s.type = o.getAttribute('data-clm-type'); }
			if (o.hasAttribute('src')) {
				var done = false, go = function () { if (!done) { done = true; next(i + 1); } };
				s.onload = s.onerror = go;
				setTimeout(go, 4000); // pojistka, aby jeden pomalý skript nezastavil ostatní
				o.parentNode.replaceChild(s, o);
			} else {
				s.text = o.text;
				o.parentNode.replaceChild(s, o);
				next(i + 1);
			}
		})(0);

		[].slice.call(d.querySelectorAll('iframe[data-clm-src], iframe[data-clm-srcdoc]')).forEach(function (f) {
			if (cats.indexOf(f.getAttribute('data-clm-cat')) < 0) { return; }
			if (f.hasAttribute('data-clm-srcdoc')) { f.setAttribute('srcdoc', f.getAttribute('data-clm-srcdoc')); f.removeAttribute('data-clm-srcdoc'); }
			if (f.hasAttribute('data-clm-src')) { f.setAttribute('src', f.getAttribute('data-clm-src')); f.removeAttribute('data-clm-src'); }
			f.style.display = '';
			var ph = f.previousElementSibling;
			if (ph && ph.className === 'clm-ph') { ph.parentNode.removeChild(ph); }
		});
	}

	function placeholders() {
		[].slice.call(d.querySelectorAll('iframe[data-clm-src], iframe[data-clm-srcdoc]')).forEach(function (f) {
			var prev = f.previousElementSibling;
			if (prev && prev.className === 'clm-ph') { return; }
			var cat = f.getAttribute('data-clm-cat');
			if (current && current.indexOf(cat) > -1) { return; }
			var ph = el('div', 'clm-ph');
			var h = parseInt(f.getAttribute('height'), 10);
			ph.style.minHeight = (h > 80 ? Math.min(h, 600) : 200) + 'px';
			ph.appendChild(el('p', '', (C.t.ph || '').replace('%s', f.getAttribute('data-clm-name') || '')));
			var b = el('button', 'clm-btn clm-btn-pri', C.t.phbtn);
			b.type = 'button';
			b.addEventListener('click', function () {
				var cats = (current || []).slice();
				if (cats.indexOf(cat) < 0) { cats.push(cat); }
				decide(cats);
			});
			ph.appendChild(b);
			f.style.display = 'none';
			f.parentNode.insertBefore(ph, f);
		});
	}

	function apply(cats) {
		if (typeof window.gtag === 'function' && d.getElementById('clm-gcm')) {
			var s = cats.indexOf('statistics') > -1 ? 'granted' : 'denied', m = cats.indexOf('marketing') > -1 ? 'granted' : 'denied';
			window.gtag('consent', 'update', { analytics_storage: s, ad_storage: m, ad_user_data: m, ad_personalization: m });
		}
		unblock(cats);
		window.clmConsent = cats;
		try { d.dispatchEvent(new CustomEvent('clm:consent', { detail: cats })); } catch (e) {}
	}

	function decide(cats) {
		var prev = current;
		writeConsent(cats);
		current = cats;
		hideBanner();
		closeModal();
		showReopen();
		// Cookies všech neudělených kategorií smazat vždy (i při prvním odmítnutí a po resetu souhlasu).
		OPTIONAL.forEach(function (c) { if (cats.indexOf(c) < 0) { deleteCookies(c); } });
		var revoked = (prev || []).filter(function (c) { return cats.indexOf(c) < 0; });
		if (revoked.length) {
			location.reload(); // už spuštěné skripty nejde vypnout jinak
			return;
		}
		apply(cats);
	}

	/* ---------- UI ---------- */
	function el(tag, cls, text) {
		var e = d.createElement(tag);
		if (cls) { e.className = cls; }
		if (text) { e.textContent = text; }
		return e;
	}
	function btn(cls, text, fn) {
		var b = el('button', 'clm-btn ' + cls, text);
		b.type = 'button';
		b.addEventListener('click', fn);
		return b;
	}
	function ensureRoot() {
		if (root) { return; }
		root = el('div', 'clm-root');
		var st = C.style || {};
		root.style.setProperty('--clm-bg', st.bg);
		root.style.setProperty('--clm-text', st.text);
		root.style.setProperty('--clm-accent', st.accent);
		root.style.setProperty('--clm-atext', st.atext);
		// i pro zástupné bloky vloženého obsahu (jsou mimo .clm-root)
		document.documentElement.style.setProperty('--clm-accent', st.accent);
		document.documentElement.style.setProperty('--clm-atext', st.atext);
		root.style.setProperty('--clm-radius', (st.radius || 0) + 'px');
		root.style.setProperty('--clm-font', (st.font || 15) + 'px');
		d.body.appendChild(root);
	}
	function acceptAll() { decide(OPTIONAL.slice()); }
	function rejectAll() { decide([]); }

	function showBanner() {
		ensureRoot();
		if (banner) { banner.hidden = false; return; }
		banner = el('div', 'clm-banner clm-pos-' + (C.pos || 'box-left'));
		banner.setAttribute('role', 'region');
		banner.setAttribute('aria-label', C.t.title);
		var inner = el('div', 'clm-inner');
		if (C.t.title) { inner.appendChild(el('p', 'clm-title', C.t.title)); }
		var msg = el('p', 'clm-msg', C.t.msg + ' ');
		if (C.policy) {
			var a = el('a', '', C.t.policy);
			a.href = C.policy;
			msg.appendChild(a);
		}
		inner.appendChild(msg);
		var bs = el('div', 'clm-btns');
		bs.appendChild(btn('clm-btn-sec', C.t.settings, openModal));
		bs.appendChild(btn('clm-btn-sec', C.t.reject, rejectAll));
		bs.appendChild(btn('clm-btn-pri', C.t.accept, acceptAll));
		inner.appendChild(bs);
		banner.appendChild(inner);
		root.appendChild(banner);
		if (C.pos === 'center') { banner.classList.add('clm-has-overlay'); }
	}
	function hideBanner() { if (banner) { banner.hidden = true; } }

	function openModal() {
		ensureRoot();
		lastFocus = d.activeElement;
		if (modal) { root.removeChild(modal); }
		modal = el('div', 'clm-modal');
		modal.setAttribute('role', 'dialog');
		modal.setAttribute('aria-modal', 'true');
		modal.setAttribute('aria-label', C.t.mtitle || C.t.settings);
		var panel = el('div', 'clm-panel');
		var close = btn('clm-close', '×', closeModal);
		close.setAttribute('aria-label', 'Zavřít');
		panel.appendChild(close);
		panel.appendChild(el('p', 'clm-title', C.t.mtitle || C.t.settings));
		var cats = current || [];
		Object.keys(C.cats).forEach(function (key) {
			var c = C.cats[key], box = el('div', 'clm-cat');
			var head = el('label', 'clm-cat-head');
			var cb = el('input');
			cb.type = 'checkbox';
			cb.setAttribute('data-cat', key);
			if (key === 'necessary') { cb.checked = true; cb.disabled = true; } else { cb.checked = cats.indexOf(key) > -1; }
			head.appendChild(cb);
			head.appendChild(el('span', 'clm-sw'));
			head.appendChild(el('strong', '', c.name));
			box.appendChild(head);
			box.appendChild(el('p', 'clm-desc', c.desc));
			if (c.services && c.services.length) {
				var det = el('details'), ul = el('ul');
				det.appendChild(el('summary', '', 'Služby (' + c.services.length + ')'));
				c.services.forEach(function (s) {
					var li = el('li', '', s[0]);
					if (s[1]) { li.appendChild(el('small', '', ' – ' + s[1])); }
					ul.appendChild(li);
				});
				det.appendChild(ul);
				box.appendChild(det);
			}
			panel.appendChild(box);
		});
		var bs = el('div', 'clm-btns');
		bs.appendChild(btn('clm-btn-sec', C.t.reject, rejectAll));
		bs.appendChild(btn('clm-btn-sec', C.t.save, function () {
			var sel = [].slice.call(modal.querySelectorAll('input[data-cat]')).filter(function (i) {
				return i.checked && !i.disabled;
			}).map(function (i) { return i.getAttribute('data-cat'); });
			decide(sel);
		}));
		bs.appendChild(btn('clm-btn-pri', C.t.accept, acceptAll));
		panel.appendChild(bs);
		modal.appendChild(panel);
		modal.addEventListener('click', function (e) { if (e.target === modal) { closeModal(); } });
		root.appendChild(modal);
		close.focus();
	}
	function closeModal() {
		if (!modal) { return; }
		root.removeChild(modal);
		modal = null;
		if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
	}

	var reopenBtn;
	function showReopen() {
		if (C.reopen === 'none' || reopenBtn) { return; }
		ensureRoot();
		reopenBtn = btn('clm-reopen clm-reopen-' + (C.reopen === 'right' ? 'right' : 'left'), '', openModal);
		reopenBtn.setAttribute('aria-label', C.t.settings + ' cookies');
		reopenBtn.title = C.t.settings + ' cookies';
		reopenBtn.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5zm-3.5 7a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm-1 5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm6 1a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm3-3.5a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>';
		root.appendChild(reopenBtn);
	}

	d.addEventListener('keydown', function (e) {
		if (!modal) { return; }
		if (e.key === 'Escape') { closeModal(); return; }
		if (e.key !== 'Tab') { return; }
		var f = [].slice.call(modal.querySelectorAll('button, input:not([disabled]), summary, a[href]'));
		if (!f.length) { return; }
		var first = f[0], last = f[f.length - 1];
		if (e.shiftKey && d.activeElement === first) { e.preventDefault(); last.focus(); }
		else if (!e.shiftKey && (d.activeElement === last || !modal.contains(d.activeElement))) { e.preventDefault(); first.focus(); }
	});
	d.addEventListener('click', function (e) {
		var t = e.target.closest && e.target.closest('.clm-open, a[href="#clm-nastaveni"]');
		if (t) { e.preventDefault(); openModal(); }
	});

	/* ---------- start ---------- */
	function init() {
		placeholders();
		var preview = /[?&]clm_preview=1/.test(location.search);
		if (current && !preview) {
			apply(current);
			showReopen();
		} else {
			showBanner();
		}
	}
	window.clmOpen = openModal;
	if (d.readyState === 'loading') { d.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
