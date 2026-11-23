/* Autor: Pol Corsin — PT04 osu! Scores
   JavaScript vanilla. Només millora l'experiència: la web funciona igual sense JS
   (sense JS no hi ha conmutador i s'usa el tema del sistema). */
(function () {
    'use strict';

    var root = document.documentElement;

    // ------------------------------------------------------------------
    // 1) Tema clar / fosc
    //    L'script del <head> ja ha posat data-theme (si hi ha preferència desada) abans de pintar.
    //    Aquí només es connecta el botó i es desa l'elecció a localStorage.
    // ------------------------------------------------------------------
    var THEME_COLORS = { dark: '#100d17', light: '#f5f2fa' };
    var mq = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function activeTheme() {
        var t = root.getAttribute('data-theme');
        if (t === 'light' || t === 'dark') { return t; }
        return mq && mq.matches ? 'dark' : 'light';     // sense elecció: el del sistema
    }

    function syncToggle() {
        var theme = activeTheme();
        document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
            btn.hidden = false;                                         // sense JS el botó no existeix
            btn.setAttribute('aria-pressed', String(theme === 'dark')); // premut = mode fosc actiu
            btn.setAttribute('data-active', theme);
        });
        document.querySelectorAll('meta[name="theme-color"]').forEach(function (m) {
            m.setAttribute('content', THEME_COLORS[theme]);
        });
    }

    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var next = activeTheme() === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            try { localStorage.setItem('theme', next); } catch (e) { /* mode privat: no es pot desar */ }
            syncToggle();
        });
    });
    // Si l'usuari no ha triat res i canvia el tema del sistema, s'actualitza l'estat del botó
    if (mq && mq.addEventListener) { mq.addEventListener('change', syncToggle); }
    syncToggle();

    // ------------------------------------------------------------------
    // 2) Menú plegable en mòbil (botó amb aria-expanded)
    // ------------------------------------------------------------------
    var toggle = document.querySelector('.nav-toggle');
    var nav = document.getElementById('menu-principal');
    if (toggle && nav) {
        var setOpen = function (open) {
            toggle.setAttribute('aria-expanded', String(open));
            nav.setAttribute('data-open', String(open));
            toggle.querySelector('.nav-toggle__label').textContent = open ? 'Tanca el menú' : 'Obre el menú';
        };
        setOpen(false);
        toggle.addEventListener('click', function () {
            setOpen(toggle.getAttribute('aria-expanded') !== 'true');
        });
        // Esc tanca el menú i retorna el focus al botó
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
                setOpen(false);
                toggle.focus();
            }
        });
    }

    // ------------------------------------------------------------------
    // 3) Mostrar/amagar contrasenya (botó accessible amb aria-pressed)
    // ------------------------------------------------------------------
    document.querySelectorAll('[data-password-toggle]').forEach(function (btn) {
        var input = document.getElementById(btn.getAttribute('aria-controls'));
        if (!input) { return; }
        btn.hidden = false;
        btn.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-pressed', String(show));
            btn.textContent = show ? 'Amaga' : 'Mostra';
        });
    });

    // ------------------------------------------------------------------
    // 4) Si el formulari torna amb errors, el focus va al resum perquè el lector de pantalla l'anunciï
    // ------------------------------------------------------------------
    var summary = document.getElementById('resum-errors');
    if (summary) { summary.focus(); }
})();
