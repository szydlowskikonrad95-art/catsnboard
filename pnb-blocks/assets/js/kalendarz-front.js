/* Kalendarz PNB — oś czasu premium: filtry chips (vanilla, działają ZAWSZE) + głębia/reveal (GSAP,
   wzorzec galeria-front.js). Współżyje z motywem klienta: guard na gsap/ScrollTrigger, handle pnb-. */
(function () {
	'use strict';
	var root = document.querySelector('.pnb-events');
	if (!root) return;
	var rm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var maGsap = (typeof gsap !== 'undefined') && (typeof ScrollTrigger !== 'undefined');
	if (maGsap) gsap.registerPlugin(ScrollTrigger);

	var karty = Array.prototype.slice.call(root.querySelectorAll('.pnb-ev-card'));
	var grupy = Array.prototype.slice.call(root.querySelectorAll('.pnb-ev-group'));
	var brak = root.querySelector('.pnb-ev-none');
	var cardwraps = Array.prototype.slice.call(root.querySelectorAll('.pnb-ev-cardwrap')); // dla paginacji (płasko, przez wszystkie grupy)

	/* ── FILTRY CHIPS (vanilla — MUSZĄ działać też bez animacji / przy reduced-motion) ── */
	function ymd(d) {
		return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
	}
	function koniecTygodnia() { // „This week" = do końca niedzieli (ta sama reguła co liczniki w PHP)
		var d = new Date();
		var dow = d.getDay(); // 0 = niedziela
		d.setDate(d.getDate() + (dow === 0 ? 0 : 7 - dow));
		return ymd(d);
	}
	function dokoncz(karta) { // karta pokazana filtrem nie może czekać na scroll-trigger — odsłoń od razu
		if (maGsap && !rm && !karta.classList.contains('is-rev')) {
			karta.classList.add('is-rev');
			gsap.to(karta, { y: 0, opacity: 1, duration: 0.7, ease: 'expo.out', overwrite: 'auto' });
			// części karty też (choreografia wejścia trzyma je schowane do reveala)
			gsap.to(karta.querySelectorAll('.pnb-ev-main > *'), { y: 0, opacity: 1, duration: 0.5, ease: 'expo.out', stagger: 0.05, overwrite: 'auto' });
		}
	}
	var aktywnyFiltr = 'all'; // ostatni wybrany chip — łączy się z wyszukiwarką (AND)
	var szukajFraza = '';      // tekst z pola szukania (lowercase)

	function filtruj(f) {
		if (f) aktywnyFiltr = f;
		var f2 = aktywnyFiltr;
		var dzis = ymd(new Date());
		var eow = koniecTygodnia();
		var mies = dzis.slice(0, 7);
		var widoczne = 0;
		karty.forEach(function (k) {
			var when = k.getAttribute('data-when') || '';
			var kat = k.getAttribute('data-cat') || '';
			var pokaz = true;
			if (f2 === 'week') { pokaz = !!when && when <= eow; }
			else if (f2 === 'month') { pokaz = when.slice(0, 7) === mies; }
			else if (f2.indexOf('cat-') === 0) { pokaz = kat === f2.slice(4); }
			// Wyszukiwarka: karta musi zawierać frazę w tytule/opisie (data-search).
			if (pokaz && szukajFraza) {
				var tekst = (k.getAttribute('data-search') || '').toLowerCase();
				pokaz = tekst.indexOf(szukajFraza) !== -1;
			}
			// Ukrywamy CAŁY wrapper (karta + jej przycisk Sign up/View event są rodzeństwem w .pnb-ev-cardwrap).
			// Ukrycie samej karty zostawiało przycisk-sierotę, który wizualnie kleił się do następnej karty.
			var wrap = k.closest ? k.closest('.pnb-ev-cardwrap') : k.parentNode;
			(wrap || k).classList.toggle('is-hidden', !pokaz);
			if (pokaz) { widoczne++; dokoncz(k); }
		});
		grupy.forEach(function (g) { // nagłówek grupy bez widocznych kart też znika (paginacja doprecyzuje niżej)
			g.classList.toggle('is-hidden', !g.querySelector('.pnb-ev-cardwrap:not(.is-hidden)'));
		});
		if (brak) brak.classList.toggle('is-hidden', widoczne > 0);
		pgPrzelicz(); // liczba WIDOCZNYCH kart się zmieniła → przelicz strony paginacji i wróć na 1 (robi refresh sam)
	}

	/* ── PAGINACJA (vanilla, warstwa NA WIERZCHU filtra) — 10 kart/stronę, numerki DYNAMICZNE liczone
	   z WIDOCZNYCH (po filtrze) cardwrapów, płasko przez wszystkie grupy dat. Osobna klasa .pg-hidden
	   (NIE .is-hidden filtra — inaczej dwie warstwy by się nadpisywały nawzajem); karta realnie
	   widoczna = ani is-hidden (filtr) ani pg-hidden (paginacja). ── */
	var PG_ROZMIAR = 10;
	var pgStrona = 1;
	var pager = root.querySelector('.pnb-ev-pager');

	function pgWidoczne() { // cardwrapy które PRZESZŁY filtr (płasko, przez wszystkie grupy)
		return cardwraps.filter(function (w) { return !w.classList.contains('is-hidden'); });
	}

	function pgRenderNumerki(strony) {
		if (!pager) return;
		if (strony <= 1) { // ≤10 widocznych → paginacja zbędna, chowamy całą belkę
			pager.classList.remove('is-on');
			pager.innerHTML = '';
			return;
		}
		var lbPrev = pager.getAttribute('data-prev') || 'Previous page';
		var lbNext = pager.getAttribute('data-next') || 'Next page';
		pager.classList.add('is-on');
		var html = '<button type="button" class="pnb-ev-pg pnb-ev-pg-arrow" data-pg="prev"'
			+ (pgStrona <= 1 ? ' disabled' : '') + ' aria-label="' + lbPrev + '">‹</button>';
		for (var i = 1; i <= strony; i++) {
			html += '<button type="button" class="pnb-ev-pg' + (i === pgStrona ? ' is-active' : '') + '" data-pg="' + i + '"'
				+ (i === pgStrona ? ' aria-current="page"' : '') + '>' + i + '</button>';
		}
		html += '<button type="button" class="pnb-ev-pg pnb-ev-pg-arrow" data-pg="next"'
			+ (pgStrona >= strony ? ' disabled' : '') + ' aria-label="' + lbNext + '">›</button>';
		pager.innerHTML = html;
	}

	function pgPokazStrone(str) {
		if (!pager) { // brak elementu paginacji w markupie (np. stary cache) → nie ucinaj listy, pokaż wszystko
			cardwraps.forEach(function (w) { w.classList.remove('pg-hidden'); });
			return;
		}
		var wraps = pgWidoczne();
		var strony = Math.max(1, Math.ceil(wraps.length / PG_ROZMIAR));
		if (str < 1) str = 1;
		if (str > strony) str = strony;
		pgStrona = str;
		var od = (str - 1) * PG_ROZMIAR;
		var doW = od + PG_ROZMIAR;
		var pgAnimuj = []; // karty które wchodzą na tę stronę → animujemy je PO renderze (rAF niżej)
		wraps.forEach(function (w, i) {
			var poza = ( i < od || i >= doW );
			w.classList.toggle('pg-hidden', poza );
			// Karta wchodząca na widok (np. strona 2) NIE może czekać na scroll-trigger — startowała
			// jako display:none (pg-hidden), więc ScrollTrigger.batch mógł "wejść" w nią w PRÓŻNI
			// (element 0×0/bez layoutu) i już oznaczyć is-rev bez realnego pokazania. dokoncz() wtedy
			// widzi is-rev i NIC nie robi → karta zostaje na opacity:0/translateY(32px) na zawsze
			// (BUG: karty strony 2+ niewidoczne). NAPRAWA: paginacja wymusza widoczny stan końcowy
			// BEZWARUNKOWO, niezależnie od is-rev — gsap.set (natychmiast), nie gsap.to/dokoncz().
			if ( !poza ) {
				var k = w.querySelector('.pnb-ev-card');
				if ( k ) {
					k.classList.add('is-rev'); // niech dalsze dokoncz() (np. z filtruj()) już jej nie rusza
					if (maGsap && !rm) {
						// KLUCZ (naprawa fade 2026-07-09): clearProps:'all' KASUJE inline opacity/transform
						// które GSAP-reveal zostawił — inaczej inline przebija animację CSS .pg-wejscie i fade
						// był niewidoczny (GSAP i CSS walczyły o opacity). Po wyczyszczeniu inline CSS przejmuje
						// (klasa .pg-wejscie niżej robi fade+unos, `both` trzyma stan końcowy = karta widoczna).
						gsap.set(k, { clearProps: 'all' });
						gsap.set(k.querySelectorAll('.pnb-ev-main > *'), { clearProps: 'all' });
						pgAnimuj.push(k); // KARTA (nie wrapper — wrapper ma pod-scrub GSAP) → CSS fade+unos
					}
				}
			}
		});
		// PŁYNNE, MIĘKKIE wejście przez CSS (fade+unos, keyframes pnbPgIn). Restart animacji: zdejmij
		// klasę → wymuś reflow (void offsetWidth) → dodaj SYNCHRONICZNIE. ⛔ NIE w requestAnimationFrame:
		// callback rAF ginął (ScrollTrigger.refresh niżej wyprzedzał go / anulował paint) → add('pg-wejscie')
		// nie wykonywał się ANI RAZU mimo że pgAnimuj miało karty (potwierdzone pomiarem 2026-07-09:
		// clearProps wołany 20×, add pg-wejscie 0×). Reflow między remove i add sam wystarcza do restartu.
		if (pgAnimuj.length) {
			pgAnimuj.forEach(function (k) { k.classList.remove('pg-wejscie'); });
			void root.offsetWidth; // reflow — reset animacji (żeby odpaliła ponownie przy kolejnej stronie)
			pgAnimuj.forEach(function (k) { k.classList.add('pg-wejscie'); });
		}
		// grupy dat: nagłówek+sekcja widoczne tylko gdy mają ≥1 kartę na TEJ stronie (filtr + paginacja razem)
		grupy.forEach(function (g) {
			g.classList.toggle('is-hidden', !g.querySelector('.pnb-ev-cardwrap:not(.is-hidden):not(.pg-hidden)'));
		});
		pgRenderNumerki(strony);
		if (maGsap && !rm) ScrollTrigger.refresh(); // strona się zmieniła — przelicz triggery (animacje głębi)
	}

	function pgPrzelicz() { // wywoływane po KAŻDYM filtrze/szukaniu — liczba stron mogła się zmienić
		pgPokazStrone(1); // nowy zestaw wyników → zawsze wracamy na stronę 1
	}

	if (pager) {
		pager.addEventListener('click', function (e) {
			var btn = e.target.closest ? e.target.closest('[data-pg]') : null;
			if (!btn || btn.disabled) return;
			var cel = btn.getAttribute('data-pg');
			var nowa = cel === 'prev' ? pgStrona - 1 : (cel === 'next' ? pgStrona + 1 : parseInt(cel, 10));
			// 1. PRZEBUDUJ stronę (pgPokazStrone zmienia które grupy/karty są widoczne → zmienia WYSOKOŚĆ
			//    listy). 2. DOPIERO POTEM scrolluj. ⛔ BUG (zgłoszony 2026-07-09: klik 2→3 wyrzucał w STOPKĘ):
			//    stary kod mierzył pozycję .pnb-ev-tl-in PRZED pgPokazStrone — a przebudowa zmienia layout,
			//    więc zmierzone y było nieaktualne (raz trafiało, raz lądowało w stopce). Naprawa: mierz PO
			//    przebudowie. KOTWICA = pasek chipów (.pnb-ev-chips) — jego pozycja w dokumencie jest STAŁA
			//    (nad listą, nie zależy od liczby kart), więc scroll zawsze ląduje tuż nad listą pod paskiem.
			pgPokazStrone(nowa);
			var kotwica = root.querySelector('.pnb-ev-chips') || root.querySelector('.pnb-ev-tl') || root;
			var adminBar = document.body.classList.contains('admin-bar') ? 32 : 0;
			var y = kotwica.getBoundingClientRect().top + window.pageYOffset - adminBar - 8;
			window.scrollTo({ top: Math.max(0, y), behavior: 'auto' });
		});
		pgPrzelicz(); // stan początkowy (przed dotknięciem filtra) — policz strony, pokaż 1., zbuduj numerki
	}

	var chips = Array.prototype.slice.call(root.querySelectorAll('.pnb-ev-chip'));
	chips.forEach(function (ch) {
		ch.addEventListener('click', function () {
			chips.forEach(function (c) { c.classList.remove('is-active'); c.setAttribute('aria-pressed', 'false'); });
			ch.classList.add('is-active');
			ch.setAttribute('aria-pressed', 'true');
			filtruj(ch.getAttribute('data-filter') || 'all');
		});
	});

	/* ── WYSZUKIWARKA (vanilla) — filtruje karty po tytule/opisie na żywo, łączy się z chipami ── */
	var pole = root.querySelector('.pnb-ev-search-input');
	if (pole) {
		var t;
		pole.addEventListener('input', function () {
			clearTimeout(t); // debounce — nie filtruj przy każdej literze natychmiast
			t = setTimeout(function () {
				szukajFraza = pole.value.trim().toLowerCase();
				filtruj('');
			}, 150);
		});
	}

	/* ── ROZSUWAKI CTA (vanilla — MUSZĄ działać też bez GSAP / przy reduced-motion) ──
	   Guzik „Sign up" / „Event details" ma aria-controls=id-panelu (panel jest RODZEŃSTWEM POZA rzędem,
	   a formularz zapisu POZA kartą). Klik: pokaż/ukryj panel (attr hidden) + przełącz aria-expanded.
	   Panel poza kartą/rzędem = rozwinięcie NIE rusza guzików ani układu kafelka (naprawa v2.12.0). */
	var toggles = Array.prototype.slice.call(root.querySelectorAll('.pnb-ev-toggle[aria-controls]'));
	toggles.forEach(function (btn) {
		var panel = document.getElementById(btn.getAttribute('aria-controls'));
		if (!panel) return;
		btn.addEventListener('click', function () {
			var otw = btn.getAttribute('aria-expanded') === 'true';
			btn.setAttribute('aria-expanded', otw ? 'false' : 'true');
			if (otw) {
				panel.setAttribute('hidden', '');
			} else {
				panel.removeAttribute('hidden');
			}
			if (maGsap && !rm) ScrollTrigger.refresh(); // wysokość osi się zmieniła — przelicz triggery
		});
	});

	/* reduced-motion / brak GSAP: filtry i rozsuwaki już podpięte, animacji nie robimy — wszystko widoczne */
	if (rm || !maGsap) return;

	/* ── HERO: paralaksa zdjęcia — kadr płynie wolniej niż scroll.
	   TWARDA ZASADA (P2, szary pas): |yPercent| ≤ (scale−1)/2, inaczej box obrazu odsłania
	   krawędź i na dole hero wyłazi mętny pas tła (stary bug: ±8% przy scale 1.06 = 32px pasa). ── */
	var heroImg = root.querySelector('.pnb-evh-img img');
	if (heroImg) {
		gsap.fromTo(heroImg, { yPercent: -6, scale: 1.13 }, { yPercent: 6, scale: 1.13, ease: 'none',
			scrollTrigger: { trigger: '.pnb-evh', start: 'top top', end: 'bottom top', scrub: true } });
	}

	/* ── GŁĘBIA (wzorzec galerii): plan -1 plamy · plan 0 watermark · plan 1 karty ──
	   zakresy PODBITE (werdykt P2): ruch ma być widoczny między klatkami, nie homeopatyczny */
	var tl = root.querySelector('.pnb-ev-tl');
	if (tl) {
		var wm = tl.querySelector('.pnb-ev-wm');
		if (wm) { // watermark: kontr-tempo w pionie ±90px + dryf w bok (xPercent -50 = centrowanie z CSS)
			gsap.fromTo(wm, { xPercent: -50, y: 90 }, { xPercent: -70, y: -90, ease: 'none',
				scrollTrigger: { trigger: tl, start: 'top bottom', end: 'bottom top', scrub: 1.4 } });
		}
		[['.pnb-ev-blob-a', 130, 46], ['.pnb-ev-blob-b', -150, -40]].forEach(function (b) {
			var el = tl.querySelector(b[0]);
			if (el) { // plamy dryfują najwolniej, każda w swoją stronę (pion + lekki skos)
				gsap.to(el, { y: b[1], x: b[2], ease: 'none',
					scrollTrigger: { trigger: tl, start: 'top bottom', end: 'bottom top', scrub: 1.8 } });
			}
		});
		var linia = tl.querySelector('.pnb-ev-line');
		if (linia) { // linia osi rośnie ze scrollem — origin TOP, tani efektowny detal
			gsap.fromTo(linia, { scaleY: 0 }, { scaleY: 1, transformOrigin: 'top center', ease: 'none',
				scrollTrigger: { trigger: tl, start: 'top 72%', end: 'bottom 60%', scrub: 1 } });
		}
	}

	/* ── pod-scrub kart: WRAPPER faluje ±10px naprzemiennie (osobny kanał — reveal trzyma transform karty) ── */
	gsap.utils.toArray('.pnb-ev-cardwrap').forEach(function (w, i) {
		var kier = (i % 2 === 0) ? 1 : -1;
		gsap.fromTo(w, { y: 10 * kier }, { y: -10 * kier, ease: 'none',
			scrollTrigger: { trigger: w, start: 'top bottom', end: 'bottom top', scrub: 1.2 } });
	});

	/* ── pod-scrub ZDJĘĆ kart podbity do ~15% zakresu (werdykt P3: było homeopatyczne ~7%).
	   Kanał na .pnb-ev-photo (blok, nie IMG — IMG należy do hover-zoom). Tylko desktop:
	   na mobile zdjęcie stoi NAD tytułem w stacku i scrub by nachodził na tekst. ── */
	gsap.matchMedia().add('(min-width: 881px)', function () {
		gsap.utils.toArray('.pnb-ev-photo').forEach(function (f) {
			// r5: amplituda podbita 7.5→12% — plan zdjęć ma jechać ~1.12× względem strony PRZEZ CAŁY przejazd
			// (sędzia zmierzył plany 1:1; ten kanał daje różnicę temp = głębia kinetyczna, nie malowana)
			gsap.fromTo(f, { yPercent: 12 }, { yPercent: -12, ease: 'none',
				scrollTrigger: { trigger: f, start: 'top bottom', end: 'bottom top', scrub: 1.1 } });
		});
	});

	/* nagłówki grup dat: fade-slide */
	gsap.utils.toArray('.pnb-ev-ghead').forEach(function (h) {
		gsap.from(h, { y: 22, opacity: 0, duration: 0.8, ease: 'expo.out',
			scrollTrigger: { trigger: h, start: 'top 90%', once: true } });
	});

	/* karty: reveal partiami — GSAP jest JEDYNYM właścicielem transform karty (zoom foto żyje na IMG).
	   CHOREOGRAFIA WEJŚCIA (P3): karta nie wjeżdża jednym klockiem — timeline per karta składa treść
	   sekwencją czas→tytuł→meta→opis→CTA (kolejność DOM w .pnb-ev-main), stagger 70 ms, y:14, expo.out. */
	function czesci(k) { return k.querySelectorAll('.pnb-ev-main > *'); }
	if (karty.length) {
		gsap.set(karty, { y: 32, opacity: 0 });
		karty.forEach(function (k) { gsap.set(czesci(k), { y: 14, opacity: 0 }); });
		ScrollTrigger.batch(karty, {
			start: 'top 88%',
			once: true,
			onEnter: function (partia) {
				partia.forEach(function (k, i) {
					k.classList.add('is-rev');
					gsap.timeline({ delay: i * 0.12 })
						.to(k, { y: 0, opacity: 1, duration: 1.1, ease: 'expo.out', overwrite: 'auto' }, 0)
						.to(czesci(k), { y: 0, opacity: 1, duration: 0.7, ease: 'expo.out', stagger: 0.07, overwrite: 'auto' }, 0.1);
				});
			}
		});
	}

	/* kursor „Join" — koralowy krążek nad kartami, tylko precyzyjna mysz; łapie też linki do singla
	   (.pnb-ev-golink: tytuł/zdjęcie/„Event details" prowadzą tam gdzie zapis), omija resztę interakcji */
	if (window.matchMedia('(pointer: fine)').matches) {
		var cur = document.createElement('div');
		cur.className = 'pnb-cursor';
		cur.textContent = 'Join';
		document.body.appendChild(cur);
		var qx = gsap.quickTo(cur, 'x', { duration: 0.3, ease: 'power3' });
		var qy = gsap.quickTo(cur, 'y', { duration: 0.3, ease: 'power3' });
		document.addEventListener('mousemove', function (e) {
			qx(e.clientX); qy(e.clientY);
			var t = e.target;
			var nad = !!(t.closest && t.closest('.pnb-ev-card') &&
				!t.closest('a:not(.pnb-ev-golink), button, summary, input, textarea, .pnb-ev-form'));
			cur.classList.toggle('is-on', nad);
		});
	}
})();
