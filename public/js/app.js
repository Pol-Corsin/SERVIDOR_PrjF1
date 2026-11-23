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
    // 4) Previsualització en directe del color de la dificultat (mateix algorisme que app/Helpers/stars.php:
    //    interpolació entre els 11 colors de l'escala i fons clar/fosc segons el contrast)
    // ------------------------------------------------------------------
    var starPreview = document.querySelector('[data-star-preview]');
    var starInput = document.getElementById('star_rating');
    if (starPreview && starInput) {
        var stops = JSON.parse(starPreview.getAttribute('data-stops'));
        var fillDark = starPreview.getAttribute('data-fill-dark');
        var fillLight = starPreview.getAttribute('data-fill-light');
        var toRgb = function (hex) { var h = hex.replace('#', ''); return [0, 2, 4].map(function (i) { return parseInt(h.substr(i, 2), 16); }); };
        var lum = function (rgb) {
            var c = rgb.map(function (v) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); });
            return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
        };
        var contrast = function (a, b) { var l1 = lum(a), l2 = lum(b); if (l1 < l2) { var t = l1; l1 = l2; l2 = t; } return (l1 + 0.05) / (l2 + 0.05); };
        var toHex = function (rgb) { return '#' + rgb.map(function (v) { return ('0' + v.toString(16)).slice(-2); }).join('').toUpperCase(); };
        var updateStars = function () {
            var raw = starInput.value.replace(',', '.');
            var s = parseFloat(raw);
            if (isNaN(s)) { s = 0; }
            s = Math.max(0, Math.min(10, s));
            var lo = Math.floor(s), hi = Math.min(10, lo + 1), t = s - lo;
            var a = toRgb(stops[lo]), b = toRgb(stops[hi]);
            var rgb = [0, 1, 2].map(function (i) { return Math.round(a[i] + (b[i] - a[i]) * t); });
            var onDark = contrast(rgb, toRgb(fillDark)), onLight = contrast(rgb, toRgb(fillLight));
            starPreview.style.setProperty('--star', toHex(rgb));
            starPreview.style.setProperty('--star-fill', onDark >= onLight ? fillDark : fillLight);
            starPreview.querySelector('[data-star-value]').textContent = s.toFixed(2).replace('.', ',');
        };
        starInput.addEventListener('input', updateStars);
    }

    // ------------------------------------------------------------------
    // 5) Previsualització de la imatge triada. Les comprovacions de tipus i mida d'aquí són només una ajuda
    //    d'usabilitat: el servidor torna a validar-ho tot pel contingut real del fitxer.
    // ------------------------------------------------------------------
    var imageInput = document.querySelector('[data-image-input]');
    var imagePreview = document.querySelector('[data-image-preview]');
    if (imageInput && imagePreview) {
        var previewImg = imagePreview.querySelector('img');
        var objectUrl = null;
        var clientError = null;
        var showClientError = function (msg) {
            if (!clientError) {
                clientError = document.createElement('p');
                clientError.className = 'field-error';
                clientError.setAttribute('role', 'alert');
                imageInput.insertAdjacentElement('afterend', clientError);
            }
            clientError.textContent = msg;
            clientError.hidden = !msg;
        };
        imageInput.addEventListener('change', function () {
            if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
            imagePreview.hidden = true;
            showClientError('');
            var file = imageInput.files && imageInput.files[0];
            if (!file) { return; }
            if (['image/jpeg', 'image/png', 'image/webp'].indexOf(file.type) === -1) {
                imageInput.value = '';
                showClientError('Només s\'accepten imatges JPEG, PNG o WebP.');
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                imageInput.value = '';
                showClientError('La imatge és massa gran. El màxim és de 5 MB.');
                return;
            }
            objectUrl = URL.createObjectURL(file);
            previewImg.src = objectUrl;
            imagePreview.hidden = false;
        });
    }

    // ------------------------------------------------------------------
    // 6) Si el formulari torna amb errors, el focus va al resum perquè el lector de pantalla l'anunciï
    // ------------------------------------------------------------------
    var summary = document.getElementById('resum-errors');
    if (summary) { summary.focus(); }
})();
