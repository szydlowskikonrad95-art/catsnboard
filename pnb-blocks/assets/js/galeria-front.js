/* Galeria PNB wg wzorca działu: taśma kinowa (scrub) + rzeki + lightbox z kadru.
   Współżyje z motywem klienta: nie tworzy własnego Lenis (używa window.lenis jeśli jest). */
(function () {
	'use strict';
	var strip = document.querySelector('.pnb-strip');
	var ghero = document.querySelector('.pnb-ghero');
	if (!strip && !ghero) return;
	var rm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	// ⚠️ ODPORNOŚĆ NA DUBEL GSAP (motyw klienta może załadować DRUGĄ kopię gsap+ScrollTrigger PO nas i
	// nadpisać window.* — wtedy nasze triggery zostają osierocone, taśma nie dojeżdża / znika). Dlatego:
	// (1) przechwytujemy WŁASNE referencje TERAZ (zanim ktokolwiek nadpisze window),
	// (2) na window.load wołamy refresh TEJ instancji (nie window.ScrollTrigger, który może być już czyjś),
	//     żeby dist taśmy przeliczył się po doładowaniu lazy-obrazów.
	var gsap = window.gsap;
	var ScrollTrigger = window.ScrollTrigger;
	var maGsap = (typeof gsap !== 'undefined') && (typeof ScrollTrigger !== 'undefined');
	if (maGsap) {
		gsap.registerPlugin(ScrollTrigger);
		// przelicz taśmę gdy strona w pełni gotowa (lazy-obrazy mają wymiary → scrollWidth pełny).
		// Wołamy na PRZECHWYCONEJ instancji, więc dubel motywu tego nie ruszy.
		window.addEventListener('load', function () { try { ScrollTrigger.refresh(); } catch (e) {} });
	}

	/* nagłówek: słowa-schodki robi CZYSTY CSS (galeria.css) — GSAP bywa zdublowany przez motyw */

	/* ── HERO: paralaksa zdjęcia (wzór kalendarza .pnb-evh) — kadr płynie wolniej niż scroll.
	   scale 1.13 daje margines na ±6% ruchu bez odsłaniania krawędzi. Tylko gdy jest zdjęcie hero. ── */
	var gheroImg = ghero ? ghero.querySelector('.pnb-ghero-img img') : null;
	if (maGsap && !rm && gheroImg) {
		gsap.fromTo(gheroImg, { yPercent: -6, scale: 1.13 }, { yPercent: 6, scale: 1.13, ease: 'none',
			scrollTrigger: { trigger: '.pnb-ghero', start: 'top top', end: 'bottom top', scrub: 0.8 } });
	}

	/* TAŚMA: scroll pionowy przewija kadry poziomo + GŁĘBIA (warstwy w różnych tempach) */
	var track = document.getElementById('pnbTrack');
	if (maGsap && !rm && strip && track) {
		var licznik = document.getElementById('pnbCount');
		var kadry = document.querySelectorAll('.pnb-shot'); // cache — NIE odpytuj DOM w onUpdate (reflow co tick)
		var ileKadrow = kadry.length;
		var capBar = document.getElementById('pnbCapBar');
		// PERF: dist zależy od scrollWidth (wymusza reflow). Zamiast czytać go NA KAŻDY tick scrolla,
		// liczymy RAZ przy refresh (onRefresh woła się na init i przy zmianie layoutu). onUpdate używa cache.
		var distCache = 0;
		function przeliczDist() { distCache = Math.max( 0, track.scrollWidth - window.innerWidth ); }
		// r5: taśma kończy jazdę przy 88% zakresu (nie 100%) — ostatni kadr i licznik „08/08" ZDĄŻĄ się
		// pokazać zanim sticky się odepnie (wcześniej end:'bottom bottom' odpinał panel dokładnie w progress=1).
		var META = 0.88;
		// x steruje WYŁĄCZNIE onUpdate (zmapowany postęp) — bez własnego tweena x, żeby set() nie walczył z tweenem.
		var qsetX = gsap.quickSetter(track, 'x', 'px');
		ScrollTrigger.create({
			trigger: strip, start: 'top top', end: 'bottom bottom', scrub: 1, invalidateOnRefresh: true,
			onRefreshInit: przeliczDist, onRefresh: przeliczDist, // policz dist raz na (re)layout, nie co tick
			onUpdate: function (self) {
					var p = Math.min(1, self.progress / META); // domknij jazdę + licznik przed odpięciem
					qsetX(-distCache * p);
					if (licznik && ileKadrow) {
						var n = Math.min(ileKadrow, Math.max(1, Math.round(p * (ileKadrow - 1)) + 1));
						licznik.textContent = ('0' + n).slice(-2) + ' / ' + ('0' + ileKadrow).slice(-2);
						// mobilny pasek podpisu: pokaż podpis aktywnego kadru (data-cap już z numerem „0N — …")
						if (capBar) {
							var akt = kadry[n - 1];
							if (akt) capBar.textContent = akt.getAttribute('data-cap') || '';
						}
					}
				}
		});
		przeliczDist(); // pierwszy pomiar (na wypadek gdyby onRefreshInit nie odpalił przed pierwszym scroll)
		// DUBEL-FIX (2): dist zależy od scrollWidth tracka = od realnych szerokości kadrów. Lazy-obrazy
		// dostają wymiary DOPIERO po załadowaniu → jeśli któryś kadr doładuje się po init, scrollWidth rośnie
		// i taśma musi przeliczyć zakres, inaczej nie dojedzie do ostatniego kadru. Refresh po każdym
		// doładowanym obrazie taśmy (i raz zbiorczo). Wołamy na PRZECHWYCONEJ instancji ScrollTrigger.
		// ⚡ WYDAJNOŚĆ (2026-07-09): PRZEDTEM refresh() na load KAŻDEGO obrazu = 12-15× ciężkie
		// przeliczenie gdy obrazy się doczytują → strona ZACINAŁA pierwsze sekundy po wejściu (user:
		// „zjeżdżam od razu i zacina, po chwili już nie"). Teraz DEBOUNCE: refresh RAZ po 150ms od
		// ostatniego load-a (taśma dostanie poprawny zakres bez kilkunastu refreshy).
		var refreshTimer;
		var refreshDebounced = function () {
			clearTimeout(refreshTimer);
			refreshTimer = setTimeout(function () { try { ScrollTrigger.refresh(); } catch (e) {} }, 150);
		};
		Array.prototype.forEach.call(track.querySelectorAll('img'), function (im) {
			if (!im.complete) {
				im.addEventListener('load', refreshDebounced, { once: true });
			}
		});
		// plan 0: watermark jedzie wolniej niż kadry (kontr-tempo = głębia)
		var word = strip.querySelector('.pnb-strip-word');
		if (word) {
			gsap.fromTo(word, { xPercent: 6 }, { xPercent: -14, ease: 'none',
				scrollTrigger: { trigger: strip, start: 'top top', end: 'bottom bottom', scrub: 1.4 } });
		}
		// ⛔ USUNIĘTO animację plam (blob) — 2026-07-09 WYDAJNOŚĆ. Blob-y mają filter:blur(70px),
		// a scrub-animacja przeliczała to ROZMYCIE 70px NA KAŻDEJ KLATCE scrolla = zabójstwo GPU
		// (galeria lagowała mocno, user zgłaszał). Plamy zostają STATYCZNE (ładne tło, tanie bo blur
		// liczony raz). Ruch blobów był ledwo widoczny — płynność ważniejsza.
		// wejście kadrów: fade + delikatny unos (raz, przy dojściu do taśmy)
		// ⚡ WYDAJNOŚĆ (2026-07-09): PRZEDTEM odsłona maską clipPath (inset) — clipPath animowany to
		// jeden z NAJDROŻSZYCH efektów (przeglądarka przemalowuje CAŁY obraz co klatkę, bez pomocy GPU) →
		// galeria zacinała przy wejściu na kadry (user pokazał na zrzucie). Teraz opacity + transform Y:
		// oba SĄ na warstwie GPU (compositor), zero re-paintu → płynne. Efekt „wjazdu” zostaje, ładnie.
		gsap.utils.toArray('.pnb-shot').forEach(function (fig, i) {
			gsap.from(fig.querySelector('img'), {
				opacity: 0, y: (i % 2) ? 40 : -40, // naprzemienny kierunek (jak było przy masce) — z dołu/góry
				duration: 0.9, ease: 'expo.out', delay: (i % 4) * 0.08,
				scrollTrigger: { trigger: strip, start: 'top 78%', once: true }
			});
		});
		// ⛔ USUNIĘTO „oddychanie” kadrów (2026-07-09 WYDAJNOŚĆ). Było gsap.to(...repeat:-1) na KAŻDYM
		// zdjęciu = ~12 NIESKOŃCZONYCH animacji chodzących NON-STOP (nawet bez scrolla) → galeria lagowała
		// mocno (zmierzone: 60 aktywnych tweenów, 12 z repeat:-1). Zdjęcia są teraz statyczne — płynność
		// ważniejsza niż subtelne pulsowanie. Zostaje reveal (odsłona przy wejściu) + tilt pod kursorem.
		// tilt 3D pod kursorem (rotationX/Y na IMG — inny kanał niż oddychanie y; perspektywa z CSS na figure)
		if (window.matchMedia('(pointer: fine)').matches) {
			gsap.utils.toArray('.pnb-shot').forEach(function (fig) {
				var img = fig.querySelector('img');
				var rx = gsap.quickTo(img, 'rotationX', { duration: 0.5, ease: 'power2' });
				var ry = gsap.quickTo(img, 'rotationY', { duration: 0.5, ease: 'power2' });
				fig.addEventListener('mousemove', function (e) {
					var r = fig.getBoundingClientRect();
					ry(((e.clientX - r.left) / r.width - 0.5) * 10);
					rx(-((e.clientY - r.top) / r.height - 0.5) * 8);
				});
				fig.addEventListener('mouseleave', function () { rx(0); ry(0); });
			});
		}
	}

	/* KURSOR "View" — krążek nad kadrami taśmy i rzek */
	if (!rm && window.matchMedia('(pointer: fine)').matches && maGsap) {
		var cur = document.createElement('div');
		cur.className = 'pnb-cursor';
		cur.textContent = 'View';
		document.body.appendChild(cur);
		var qx = gsap.quickTo(cur, 'x', { duration: 0.3, ease: 'power3' });
		var qy = gsap.quickTo(cur, 'y', { duration: 0.3, ease: 'power3' });
		document.addEventListener('mousemove', function (e) {
			qx(e.clientX); qy(e.clientY);
			cur.classList.toggle('is-on', !!(e.target.closest && e.target.closest('.pnb-shot, .pnb-river img')));
		});
	}

	/* RZEKI „Moments that stay": OSOBNY zestaw (momentsPool). Gdy klient nie wybrał osobnych,
	   PHP podaje w momentsPool tę samą pulę co taśma (fallback) — więc rzeki nigdy nie są puste. */
	var dane = window.pnbGaleriaData && Array.isArray(window.pnbGaleriaData.momentsPool) ? window.pnbGaleriaData.momentsPool
		: (window.pnbGaleriaData && Array.isArray(window.pnbGaleriaData.pool) ? window.pnbGaleriaData.pool : []);
	/* r5: przy małej puli te same zdjęcia sąsiadowały (sędzia). Rozsuń duplikaty po src — greedy:
	   buduj sekwencję zawsze wybierając kadr o innym src niż poprzedni, dopóki się da. */
	function rozsun(kadry) {
		var pula = kadry.slice(), wynik = [], ostatni = null, bezpiecznik = 0;
		while (pula.length && bezpiecznik++ < 999) {
			var i = 0;
			if (ostatni) { while (i < pula.length && pula[i].src === ostatni && pula.length > 1) i++; }
			if (i >= pula.length) i = 0;
			ostatni = pula[i].src;
			wynik.push(pula.splice(i, 1)[0]);
		}
		return wynik;
	}
	// Escapowanie wartości do atrybutu HTML (rzeki budujemy przez innerHTML). Bez tego podpis załącznika
	// z cudzysłowem " (pisze go klient w panelu mediów) łamie atrybut → zepsuty markup rzeki. Escapujemy
	// & " < > (minimum dla wartości w cudzysłowach).
	function esc(v) {
		return String( v == null ? '' : v )
			.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
	}
	function html1(k) {
		var cap = k.cap || '';
		// srcset+sizes: kadr rzeki ma stałą szer. ~clamp(200..300px) → przeglądarka weźmie mały wariant
		// zamiast 'large'. sizes = realna szerokość kadru (WYDAJNOŚĆ — mniejszy transfer na wszystkich ekranach).
		var srcsetAttr = k.srcset ? ' srcset="' + esc(k.srcset) + '" sizes="(max-width:768px) 200px, 300px"' : '';
		// decoding="async" (WYDAJNOŚĆ 2026-07-09): rzeki to ~48 obrazów budowanych innerHTML. Bez tego
		// przeglądarka dekodowała je SYNCHRONICZNIE na głównym wątku przy wejściu → scroll zacinał
		// pierwsze ~1.5s (zmierzone: img.decode() na kilkunastu obrazach zawieszał renderer 45s+).
		// async = dekodowanie na wątku bocznym, główny wątek wolny dla scrolla.
		return '<img src="' + esc(k.src) + '"' + srcsetAttr + ' data-full="' + esc(k.full || k.src) + '" data-cap="' + esc(cap) + '" alt="' + esc(cap || 'Cats’N’Board') + '" loading="lazy" decoding="async">';
	}
	function zbudujRzeke(id, kadry) {
		var el = document.getElementById(id);
		if (!el || !kadry.length) return;
		var seq = rozsun(kadry);
		// Animacja pnbriv przesuwa pas o -50% → pętla jest bezszwowa TYLKO gdy druga połowa = kopia pierwszej.
		// Przy MAŁEJ puli (np. 3 zdjęcia „Moments") jedna kopia seq jest WĘŻSZA niż ekran → widać pustkę
		// z prawej, kafle tkwią w rogu. Dlatego POWIELAMY bazę (seq) aż będzie szersza niż ekran, DOPIERO ×2.
		// Kadr rzeki ma STAŁĄ szerokość w CSS (width:clamp(200..300) + object-fit:cover), więc szerokość pasa
		// jest DETERMINISTYCZNA od wstawienia (niezależna od załadowania obrazu). Liczymy pewnie: ~215px kadr
		// (dolny clamp 200 + trochę) + 26px gap ≈ 240px. Bierzemy 230 z zapasem (raczej za dużo kopii = OK).
		var naKadrPx = 230;
		var szerSeq  = seq.length * naKadrPx;
		var kopiiBazy = Math.max( 1, Math.ceil( ( window.innerWidth * 1.2 ) / szerSeq ) );
		var maxKopii  = Math.max( 1, Math.ceil( 60 / seq.length ) ); // nie rozdymaj DOM w nieskończoność
		if ( kopiiBazy > maxKopii ) { kopiiBazy = maxKopii; }
		var baza = [];
		for ( var i = 0; i < kopiiBazy; i++ ) { baza = baza.concat( seq ); }
		el.innerHTML = baza.concat( baza ).map( html1 ).join(''); // ×2 = bezszwowa pętla (pnbriv -50%)
	}
	if (dane.length) {
		var pol = Math.ceil(dane.length / 2);
		// dwie rzeki z PRZESUNIĘTĄ pulą (rzeka 2 zaczyna od innego kadru) — mniej powtórek między pasami
		zbudujRzeke('pnbRiv1', dane.slice(0, pol));
		zbudujRzeke('pnbRiv2', dane.slice(pol).length ? dane.slice(pol) : dane.slice(0, pol));
	}

	/* LIGHTBOX: klik na kadr taśmy lub rzeki = rośnie; strzałki/Esc/klik tła */
	var lb = document.getElementById('pnbLb'), lbImg = document.getElementById('pnbLbImg'), lbCap = document.getElementById('pnbLbCap'), lbNr = document.getElementById('pnbLbNr');
	if (!lb) return;
	var items = [], idx = 0;
	/* items = kadry TAŚMY + zdjęcia RZEK których NIE ma w taśmie (osobny zestaw „Moments" może mieć inne
	   zdjęcia niż taśma). Deduplikacja po data-full: taśma daje stabilną numerację, a klik w rzekę spoza
	   taśmy trafia we WŁAŚCIWE zdjęcie (wcześniej: fallback do 0 = zły obraz przy rozdzielonych zestawach).
	   Rzeki są zapętlone (pula ×2) → bierzemy pierwsze wystąpienie każdego data-full. */
	function kadryTasmy() { return Array.prototype.slice.call(document.querySelectorAll('.pnb-shot img')); }
	function kluczKadru(im) { return im.getAttribute('data-full') || im.src; }
	function zbierz() {
		items = kadryTasmy();
		var znane = {};
		items.forEach(function (im) { znane[kluczKadru(im)] = true; });
		// dołącz zdjęcia rzek, których taśma nie zawiera (unikalne)
		Array.prototype.slice.call(document.querySelectorAll('.pnb-river img')).forEach(function (im) {
			var k = kluczKadru(im);
			if (!znane[k]) { znane[k] = true; items.push(im); }
		});
		if (!items.length) items = Array.prototype.slice.call(document.querySelectorAll('.pnb-river img'));
	}
	function scrollStop(on) {
		if (window.lenis && window.lenis.stop) { on ? window.lenis.stop() : window.lenis.start(); }
		document.documentElement.style.overflow = on ? 'hidden' : '';
	}
	function pokaz(i) {
		idx = (i + items.length) % items.length;
		var el = items[idx];
		var fig = el.closest('[data-cap]');
		lbImg.src = el.getAttribute('data-full') || (fig && fig.getAttribute('data-full')) || el.src;
		lbImg.style.filter = getComputedStyle(el).filter; // zachowaj grade klikniętego kadru
		lbCap.textContent = (fig && fig.getAttribute('data-cap')) || el.getAttribute('data-cap') || '';
		if (lbNr) lbNr.textContent = (idx + 1) + ' / ' + items.length; // r5: licznik pozycji
		lb.classList.add('on'); lb.setAttribute('aria-hidden', 'false');
		scrollStop(true);
	}
	function schowaj() { lb.classList.remove('on'); lb.setAttribute('aria-hidden', 'true'); scrollStop(false); }
	// klik w kadr: mapujemy po źródle (data-full) na indeks w items (taśma + unikalne rzeki) — spójny licznik
	function indeksKlikniętego(im) {
		zbierz();
		var bezpo = items.indexOf(im);
		if (bezpo >= 0) return bezpo;
		var full = kluczKadru(im);
		for (var j = 0; j < items.length; j++) {
			if (kluczKadru(items[j]) === full) return j;
		}
		return 0;
	}
	document.addEventListener('click', function (e) {
		var im = e.target.closest && e.target.closest('.pnb-shot img, .pnb-river img');
		if (im) { pokaz(indeksKlikniętego(im)); return; }
		if (e.target.id === 'pnbLbX' || e.target === lb) schowaj();
		if (e.target.id === 'pnbLbP') pokaz(idx - 1);
		if (e.target.id === 'pnbLbN') pokaz(idx + 1);
	});
	window.addEventListener('keydown', function (e) {
		if (!lb.classList.contains('on')) return;
		if (e.key === 'Escape') schowaj();
		if (e.key === 'ArrowLeft') pokaz(idx - 1);
		if (e.key === 'ArrowRight') pokaz(idx + 1);
	});
})();
