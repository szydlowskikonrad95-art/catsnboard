/* Cats'N'Board — one Lenis clock, ScrollTrigger reveals, split words,
   gallery lightbox, custom cursor, menu toggle + scrim.
   Adapted from the source index.html. Team grid is rendered server-side (PHP),
   so no JS injection of members here. All blocks guard for missing elements
   because sections live on separate subpages. */
(function () {
  'use strict';
  var rm = matchMedia('(prefers-reduced-motion: reduce)').matches;

  // ═══ ONE Lenis clock — lerp .09, single gsap.ticker, lagSmoothing(0) ═══
  var lenis = new Lenis({ lerp: 0.09, wheelMultiplier: 1.05 });
  window.lenis = lenis; // pluginy (lightbox galerii) muszą umieć zatrzymać scroll
  // Smooth-scroll only true in-page anchors (rare); real subpage links are normal.
  document.querySelectorAll('a[href^="#"]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      var href = a.getAttribute('href');
      if (href === '#' || href.length < 2) { return; }
      var t = document.querySelector(href);
      if (t) { e.preventDefault(); document.body.classList.remove('menu-open'); lenis.scrollTo(t, { offset: 0 }); }
    });
  });
  gsap.registerPlugin(ScrollTrigger);
  lenis.on('scroll', ScrollTrigger.update);
  gsap.ticker.add(function (t) { lenis.raf(t * 1000); });
  gsap.ticker.lagSmoothing(0);

  // ═══ MENU ═══
  var burger = document.getElementById('burger');
  if (burger) { burger.addEventListener('click', function () { document.body.classList.toggle('menu-open'); }); }
  var scrim = document.getElementById('menuScrim');
  if (scrim) { scrim.addEventListener('click', function () { document.body.classList.remove('menu-open'); }); }

  // ═══ CUSTOM CURSOR (coral dot, grows over interactive) ═══
  var cur = document.getElementById('cur');
  if (cur && matchMedia('(hover:hover)').matches) {
    var mx = innerWidth / 2, my = innerHeight / 2, cx = mx, cy = my;
    addEventListener('mousemove', function (e) { mx = e.clientX; my = e.clientY; cur.classList.add('seen'); });
    (function cl() { cx += (mx - cx) * 0.2; cy += (my - cy) * 0.2; cur.style.transform = 'translate(' + cx + 'px,' + cy + 'px) translate(-50%,-50%)'; requestAnimationFrame(cl); })();
    document.querySelectorAll('a,button,[data-c]').forEach(function (el) {
      el.addEventListener('mouseenter', function () { cur.classList.add('big'); });
      el.addEventListener('mouseleave', function () { cur.classList.remove('big'); });
    });
  } else if (cur) { cur.style.display = 'none'; }

  // ═══ LIGHTBOX (gallery) ═══
  var figs = [].slice.call(document.querySelectorAll('[data-g] img'));
  var lb = document.getElementById('lightbox'), lbImg = document.getElementById('lbImg');
  if (figs.length && lb && lbImg) {
    var li = 0;
    var openLB = function (i) { li = i; lbImg.src = figs[i].src; lbImg.alt = figs[i].alt; lb.classList.add('on'); lb.setAttribute('aria-hidden', 'false'); };
    var closeLB = function () { lb.classList.remove('on'); lb.setAttribute('aria-hidden', 'true'); };
    var step = function (d) { li = (li + d + figs.length) % figs.length; lbImg.src = figs[li].src; lbImg.alt = figs[li].alt; };
    figs.forEach(function (im, i) { im.parentElement.addEventListener('click', function () { openLB(i); }); });
    var lbClose = document.getElementById('lbClose'); if (lbClose) { lbClose.addEventListener('click', closeLB); }
    var lbPrev = document.getElementById('lbPrev'); if (lbPrev) { lbPrev.addEventListener('click', function (e) { e.stopPropagation(); step(-1); }); }
    var lbNext = document.getElementById('lbNext'); if (lbNext) { lbNext.addEventListener('click', function (e) { e.stopPropagation(); step(1); }); }
    lb.addEventListener('click', function (e) { if (e.target === lb) { closeLB(); } });
    addEventListener('keydown', function (e) {
      if (!lb.classList.contains('on')) { return; }
      if (e.key === 'Escape') { closeLB(); }
      if (e.key === 'ArrowRight') { step(1); }
      if (e.key === 'ArrowLeft') { step(-1); }
    });
  }

  if (!rm) {
    // ═══ SPLIT WORDS — headings step up from masks ═══
    var wrapWords = function (el) {
      [].slice.call(el.childNodes).forEach(function (n) {
        if (n.nodeType === 3) {
          var f = document.createDocumentFragment();
          n.textContent.split(/(\s+)/).forEach(function (t) {
            if (!t) { return; }
            if (/^\s+$/.test(t)) { f.append(t); return; }
            var s = document.createElement('span'); s.className = 'w'; s.innerHTML = '<span class="wi">' + t + '</span>'; f.append(s);
          });
          n.replaceWith(f);
        } else if (n.nodeType === 1 && n.tagName !== 'BR') {
          var s2 = document.createElement('span'); s2.className = 'w';
          var i2 = document.createElement('span'); i2.className = 'wi'; n.replaceWith(s2); s2.append(i2); i2.append(n);
        }
      });
    };
    document.querySelectorAll('.splitw').forEach(function (h) {
      wrapWords(h);
      gsap.from(h.querySelectorAll('.wi'), { yPercent: 115, duration: 0.9, stagger: 0.07, ease: 'power3.out', scrollTrigger: { trigger: h, start: 'top 88%' } });
    });

    // ═══ HERO — cat scale 1.08→1 on load; copy fade-slide (guarded) ═══
    if (document.getElementById('heroImg')) {
      gsap.fromTo('#heroImg', { scale: 1.08 }, { scale: 1, duration: 1.6, ease: 'power2.out' });
      gsap.to('#heroImg', { yPercent: 8, ease: 'none', scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: 1 } });
    }
    if (document.querySelector('.hero-copy [data-rev]')) {
      gsap.from('.hero-copy [data-rev]', { opacity: 0, y: 26, duration: 1, stagger: 0.12, ease: 'power3.out', delay: 0.15 });
    }

    // ═══ REVEAL batch — WYŁĄCZONE (2026-07-04) ═══
    // ScrollTrigger.batch dla [data-rev] uparcie nie odpalał onEnter dla elementów już-w-viewport
    // na krótkich podstronach → elementy zostawały opacity:0 (strona przyćmiona). Mimo poprawek wg
    // docs (set przed batch, refresh, refreshInit) problem trwał — jakiś ScrollTrigger aktywnie
    // trzymał opacity:0. Decyzja: elementy [data-rev] pokazujemy OD RAZU (naturalne opacity z CSS),
    // bez efektu wjazdu. Reszta animacji (hero, split-words, galeria, .svc, .member) działa normalnie.
    // Elementy [data-rev] NIE dostają gsap.set(opacity:0), więc zostają widoczne — nic do zrobienia.

    // ═══ SERVICE tiles — reveal FROM LEFT (slide in x, stagger .08) ═══
    if (document.querySelector('.svc')) {
      gsap.set('.svc', { opacity: 0, x: -34 });
      ScrollTrigger.batch('.svc', { start: 'top 92%', onEnter: function (b) { gsap.to(b, { opacity: 1, x: 0, duration: 0.7, stagger: 0.08, ease: 'power3.out', overwrite: true }); } });
    }

    // ═══ TEAM members — pop in one by one (gentle up + stagger) ═══
    if (document.querySelector('.member')) {
      gsap.set('.member', { opacity: 0, y: 34 });
      ScrollTrigger.batch('.member', { start: 'top 92%', onEnter: function (b) { gsap.to(b, { opacity: 1, y: 0, duration: 0.7, stagger: 0.1, ease: 'power2.out', overwrite: true }); } });
      gsap.utils.toArray('.member .ava img').forEach(function (el, i) {
        gsap.to(el, { scale: 1.06, duration: 2.6 + (i % 4) * 0.5, yoyo: true, repeat: -1, ease: 'sine.inOut' });
      });
    }

    // ═══ GALLERY figures — mosaic scale-up reveal (scale .92→1, back.out) ═══
    if (document.querySelector('.masonry figure')) {
      gsap.set('.masonry figure', { opacity: 0, scale: 0.92 });
      ScrollTrigger.batch('.masonry figure', { start: 'top 92%', onEnter: function (b) { gsap.to(b, { opacity: 1, scale: 1, duration: 0.8, stagger: { each: 0.06, from: 'start', grid: 'auto' }, ease: 'back.out(1.5)', overwrite: true }); } });
    }

    // ═══ TRAINING photo breathes (own channel = img) ═══
    if (document.getElementById('trainImg')) {
      gsap.to('#trainImg', { scale: 1.05, duration: 4.2, yoyo: true, repeat: -1, ease: 'sine.inOut' });
    }

    // ═══ REFRESH pozycji po ułożeniu layoutu (Lenis + obrazy) — inaczej ScrollTrigger.batch
    //     liczy złe pozycje i onEnter dla elementów już-w-oknie się nie odpala (zostają opacity:0). ═══
    ScrollTrigger.refresh();
    window.addEventListener('load', function () { ScrollTrigger.refresh(); });

    // ═══ SIATKA BEZPIECZEŃSTWA (twarda): po chwili każdy element reveal który mimo wszystko został
    //     niewidoczny (opacity:0) — bo jego batch onEnter się nie odpalił — pokaż NA TWARDO przez
    //     inline style !important (gsap.to bywał cofany przez aktywny ScrollTrigger; !important nie da się
    //     cofnąć). Dzięki temu ŻADNA podstrona nie zostaje przyćmiona, niezależnie od kaprysów batcha. ═══
    setTimeout(function () {
      document.querySelectorAll('[data-rev], .svc, .member, .masonry figure').forEach(function (el) {
        if (parseFloat(getComputedStyle(el).opacity) < 0.9) {
          gsap.killTweensOf(el);
          el.style.setProperty('opacity', '1', 'important');
          el.style.setProperty('transform', 'none', 'important');
        }
      });
    }, 900);
  } else {
    if (document.getElementById('heroImg')) { gsap.set('#heroImg', { scale: 1 }); }
  }
})();
