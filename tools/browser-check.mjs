/**
 * Autor: Pol Corsin — PT04 osu! Scores
 * Comprovacions en un Chrome REAL (eina de desenvolupament; no forma part de la web).
 * Usa el protocol CDP amb Node 22 (WebSocket nadiu), sense instal·lar cap dependència.
 *
 * Ús:  node tools/browser-check.mjs [url_base] [carpeta_captures]
 * Cal Apache + MySQL engegats i Google Chrome instal·lat.
 *
 * Comprova, per a cada pàgina, en tema FOSC i CLAR i a 320 / 768 / 1280 px:
 *   - que no hi hagi scroll horitzontal;
 *   - el contrast REAL renderitzat de cada text (color calculat vs. fons efectiu);
 *   - que el tema s'apliqui, que el conmutador funcioni i que es desi;
 *   - que no hi hagi parpelleig del tema (el tema s'aplica abans del primer pintat);
 *   - que prefers-color-scheme es respecti quan no hi ha preferència desada;
 *   - que el focus amb teclat sigui visible i no quedi tapat per la capçalera;
 *   - el flux real al navegador: registre -> login -> crear -> editar -> eliminar (amb confirmació) -> logout.
 */
import { spawn, execSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, existsSync, readdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { writeFileSync } from 'node:fs';

const BASE = (process.argv[2] || 'http://localhost/Practiques/reDAW2/PRACTIQUES/SERVIDOR_PrjF1').replace(/\/$/, '');
const SHOTS = process.argv[3] || '';
const CHROME = process.env.CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
if (SHOTS) { mkdirSync(SHOTS, { recursive: true }); }

let pass = 0, fail = 0; const failures = [];
const ok = (name, cond, detail = '') => {
    if (cond) { pass++; console.log('  OK    ' + name); }
    else { fail++; failures.push(name); console.log('  FAIL  ' + name + (detail ? '  [' + detail + ']' : '')); }
};
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// ---------------------------------------------------------------- Chrome + CDP
const profile = mkdtempSync(join(tmpdir(), 'chrome-check-'));
const port = 9300 + Math.floor(Math.random() * 500);
const chrome = spawn(CHROME, [`--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, '--headless=new', '--disable-gpu', '--no-first-run', '--hide-scrollbars', 'about:blank'], { stdio: 'ignore' });
let wsUrl = '';
for (let i = 0; i < 50 && !wsUrl; i++) {
    await sleep(200);
    try { const j = await (await fetch(`http://127.0.0.1:${port}/json`)).json(); wsUrl = j.find((t) => t.type === 'page')?.webSocketDebuggerUrl || ''; } catch { /* encara no */ }
}
if (!wsUrl) { console.error('No s\'ha pogut connectar amb Chrome'); chrome.kill(); process.exit(2); }

const ws = new WebSocket(wsUrl);
await new Promise((r) => ws.addEventListener('open', r));
let seq = 0; const pending = new Map(); const listeners = [];
ws.addEventListener('message', (m) => {
    const d = JSON.parse(m.data);
    if (d.id && pending.has(d.id)) { const { res, rej } = pending.get(d.id); pending.delete(d.id); d.error ? rej(new Error(d.error.message)) : res(d.result); }
    else { listeners.forEach((l) => l(d)); }
});
const send = (method, params = {}) => new Promise((res, rej) => { const id = ++seq; pending.set(id, { res, rej }); ws.send(JSON.stringify({ id, method, params })); });
const evalJs = async (expr) => { const r = await send('Runtime.evaluate', { expression: expr, awaitPromise: true, returnByValue: true }); if (r.exceptionDetails) { throw new Error(r.exceptionDetails.text + ' ' + (r.exceptionDetails.exception?.description || '')); } return r.result.value; };
const waitLoad = () => new Promise((res) => { const l = (d) => { if (d.method === 'Page.loadEventFired') { listeners.splice(listeners.indexOf(l), 1); res(); } }; listeners.push(l); setTimeout(res, 8000); });
const go = async (path) => { const w = waitLoad(); await send('Page.navigate', { url: BASE + path }); await w; await sleep(150); };
const viewport = (w, h = 900) => send('Emulation.setDeviceMetricsOverride', { width: w, height: h, deviceScaleFactor: 1, mobile: w < 600 });
const media = (scheme) => send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-color-scheme', value: scheme }] });
const setTheme = async (t) => { await evalJs(`localStorage.setItem('theme','${t}')`); };
const shot = async (name) => { if (!SHOTS) return; const r = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true }); writeFileSync(join(SHOTS, name + '.png'), Buffer.from(r.data, 'base64')); };
// Enter necessita el text '\r' perquè Chrome generi l'esdeveniment de caràcter que activa els botons
const key = (k, code, vk) => send('Input.dispatchKeyEvent', k === 'Enter' ? { type: 'keyDown', key: k, code, windowsVirtualKeyCode: vk, text: '\r' } : { type: 'rawKeyDown', key: k, code, windowsVirtualKeyCode: vk }).then(() => send('Input.dispatchKeyEvent', { type: 'keyUp', key: k, code, windowsVirtualKeyCode: vk }));
const tab = () => key('Tab', 'Tab', 9);

await send('Page.enable'); await send('Runtime.enable');

// ---------------------------------------------------------------- Funció que s'executa DINS de la pàgina: contrast real
const CONTRAST_JS = `(() => {
  const parse = (c) => { const m = c.match(/rgba?\\(([^)]+)\\)/); if (!m) return null; const p = m[1].split(/[ ,\\/]+/).filter(Boolean).map(Number); return { r: p[0], g: p[1], b: p[2], a: p[3] === undefined ? 1 : p[3] }; };
  const lin = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
  const lum = (c) => 0.2126 * lin(c.r) + 0.7152 * lin(c.g) + 0.0722 * lin(c.b);
  const over = (f, b) => ({ r: f.r * f.a + b.r * (1 - f.a), g: f.g * f.a + b.g * (1 - f.a), b: f.b * f.a + b.b * (1 - f.a), a: 1 });
  const flatten = (stack, base) => { let c = base; for (let i = stack.length - 1; i >= 0; i--) c = over(stack[i], c); return c; };
  const WHITE = { r: 255, g: 255, b: 255, a: 1 }, BLACK = { r: 0, g: 0, b: 0, a: 1 };
  const rootStyle = getComputedStyle(document.documentElement);
  const glows = [1, 2, 3].map((i) => parse(rootStyle.getPropertyValue('--glow-' + i).trim())).filter(Boolean);
  // Fons possibles darrere d'un element: llista de colors (es comprova el PITJOR cas)
  //  - Dins d'una targeta amb imatge: la imatge és desconeguda -> pitjor cas = imatge blanca o negra, amb la capa fosca i els panells semitransparents.
  //  - Directament sobre la pàgina: color de fons + el pitjor cas del fons animat (tots els brillants apilats).
  const bgsOf = (el) => {
    const stack = [];
    for (let e = el; e; e = e.parentElement) {
      if (e.classList && e.classList.contains('score--has-image')) {
        const ov = parse(getComputedStyle(e).getPropertyValue('--img-overlay').trim());
        return [WHITE, BLACK].map((img) => flatten(stack.concat(ov ? [ov] : []), img));
      }
      const c = parse(getComputedStyle(e).backgroundColor);
      if (c && c.a > 0) { stack.push(c); if (c.a >= 0.999) return [flatten(stack, WHITE)]; }
    }
    const base = parse(getComputedStyle(document.body).backgroundColor) || WHITE;
    return [flatten(stack, base), flatten(stack, flatten(glows, base))];
  };
  const bad = []; let n = 0;
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  const seen = new Set();
  while (walker.nextNode()) {
    const t = walker.currentNode; if (!t.nodeValue.trim()) continue;
    const el = t.parentElement; if (!el || seen.has(el)) continue; seen.add(el);
    const cs = getComputedStyle(el); if (cs.visibility === 'hidden' || cs.display === 'none') continue;
    const r = el.getBoundingClientRect(); if (r.width === 0 || r.height === 0) continue;
    if (el.closest('.visually-hidden, [hidden]')) continue;
    const fg0 = parse(cs.color); if (!fg0) continue;
    let ratio = 99;
    for (const bg of bgsOf(el)) {
      const fg = over(fg0, bg);
      const L1 = lum(fg), L2 = lum(bg); ratio = Math.min(ratio, (Math.max(L1, L2) + 0.05) / (Math.min(L1, L2) + 0.05));
    }
    const size = parseFloat(cs.fontSize), bold = parseInt(cs.fontWeight, 10) >= 700;
    const large = size >= 24 || (size >= 18.66 && bold);
    const min = large ? 3 : 4.5; n++;
    if (ratio < min && !(el.closest('.error-page') && el.classList.contains('code'))) bad.push(el.tagName.toLowerCase() + '.' + (el.className || '') + ' "' + t.nodeValue.trim().slice(0, 24) + '" ' + ratio.toFixed(2) + ' < ' + min);
  }
  return { n, bad };
})()`;

const PAGES = [['/', 'inici'], ['/scores', 'llistat'], ['/scores/1', 'detall'], ['/login', 'login'], ['/register', 'registre'], ['/guia-estils', 'guia'], ['/no-existeix', '404']];

console.log('\n== 1. Temes: aplicació, contrast real i desbordament (pàgines públiques) ==');
await media('dark');
await go('/');                       // per poder escriure a localStorage de l'origen
for (const theme of ['dark', 'light']) {
    await setTheme(theme);
    for (const [path, name] of PAGES) {
        for (const w of [320, 768, 1280]) {
            await viewport(w); await go(path);
            const info = await evalJs(`({ theme: document.documentElement.getAttribute('data-theme'), bg: getComputedStyle(document.body).backgroundColor, sw: document.documentElement.scrollWidth, iw: window.innerWidth, scheme: getComputedStyle(document.documentElement).colorScheme })`);
            const expectedBg = theme === 'dark' ? 'rgb(16, 13, 23)' : 'rgb(245, 242, 250)';
            ok(`[${theme}] ${name} @${w}: tema aplicat i sense scroll horitzontal`, info.theme === theme && info.bg === expectedBg && info.sw <= info.iw, JSON.stringify(info));
            if (w === 1280) {
                const c = await evalJs(CONTRAST_JS);
                ok(`[${theme}] ${name}: contrast real de ${c.n} textos ≥ AA`, c.bad.length === 0, c.bad.slice(0, 4).join(' | '));
                await shot(`${theme}-${name}`);
            }
        }
    }
}

console.log('\n== 2. Conmutador de tema, persistència i prefers-color-scheme ==');
await viewport(1280);
await evalJs(`localStorage.clear()`);
await media('light'); await go('/');
let s = await evalJs(`({ t: document.documentElement.getAttribute('data-theme'), bg: getComputedStyle(document.body).backgroundColor, p: document.querySelector('[data-theme-toggle]').getAttribute('aria-pressed'), hidden: document.querySelector('[data-theme-toggle]').hidden })`);
ok('Sense preferència i sistema CLAR: es respecta prefers-color-scheme (fons clar)', s.t === null && s.bg === 'rgb(245, 242, 250)' && s.p === 'false' && s.hidden === false, JSON.stringify(s));
await media('dark'); await go('/');
s = await evalJs(`({ t: document.documentElement.getAttribute('data-theme'), bg: getComputedStyle(document.body).backgroundColor, p: document.querySelector('[data-theme-toggle]').getAttribute('aria-pressed') })`);
ok('Sense preferència i sistema FOSC: fons fosc i aria-pressed="true"', s.bg === 'rgb(16, 13, 23)' && s.p === 'true', JSON.stringify(s));
await evalJs(`document.querySelector('[data-theme-toggle]').click()`);
s = await evalJs(`({ t: document.documentElement.getAttribute('data-theme'), ls: localStorage.getItem('theme'), p: document.querySelector('[data-theme-toggle]').getAttribute('aria-pressed'), bg: getComputedStyle(document.body).backgroundColor })`);
ok('Clic al conmutador: passa a clar, ho desa a localStorage i aria-pressed="false"', s.t === 'light' && s.ls === 'light' && s.p === 'false' && s.bg === 'rgb(245, 242, 250)', JSON.stringify(s));
await go('/scores');
s = await evalJs(`({ t: document.documentElement.getAttribute('data-theme'), bg: getComputedStyle(document.body).backgroundColor })`);
ok('La preferència es manté en una altra pàgina (encara que el sistema sigui fosc)', s.t === 'light' && s.bg === 'rgb(245, 242, 250)', JSON.stringify(s));
await evalJs(`document.querySelector('[data-theme-toggle]').click()`);
ok('Segon clic: torna a fosc i es desa', await evalJs(`localStorage.getItem('theme') === 'dark' && document.documentElement.getAttribute('data-theme') === 'dark'`));

// Teclat: el conmutador és un <button>: Enter i Espai l'activen
await go('/'); await evalJs(`document.querySelector('[data-theme-toggle]').focus()`);
const pressed = () => evalJs(`document.querySelector('[data-theme-toggle]').getAttribute('aria-pressed')`);
const p0 = await pressed();
await key('Enter', 'Enter', 13); await sleep(100);
const p1 = await pressed();
await key(' ', 'Space', 32); await sleep(100);
const p2 = await pressed();
ok('Teclat: Enter canvia el tema i Espai el torna a canviar (aria-pressed alterna)', p1 !== p0 && p2 === p0, `${p0} -> ${p1} -> ${p2}`);
const acc = await evalJs(`(() => { const b = document.querySelector('[data-theme-toggle]'); return { tag: b.tagName, name: b.textContent.trim(), pressed: b.getAttribute('aria-pressed'), type: b.type, svgHidden: [...b.querySelectorAll('svg')].every((x) => x.getAttribute('aria-hidden') === 'true') }; })()`);
ok('Accessibilitat del conmutador: <button>, nom "Mode fosc", aria-pressed, icones aria-hidden', acc.tag === 'BUTTON' && acc.name === 'Mode fosc' && ['true', 'false'].includes(acc.pressed) && acc.svgHidden, JSON.stringify(acc));

console.log('\n== 3. Sense parpelleig: el tema desat s\'aplica abans del primer pintat ==');
await evalJs(`localStorage.setItem('theme','light')`); await media('dark');
const idScript = await send('Page.addScriptToEvaluateOnNewDocument', { source: `
  window.__log = { themeAt: null, paintAt: null };
  new MutationObserver(() => { const d = document.documentElement; if (d && window.__log.themeAt === null && d.getAttribute('data-theme')) window.__log.themeAt = performance.now(); }).observe(document, { attributes: true, subtree: true });
  new PerformanceObserver((l) => { for (const e of l.getEntries()) if (e.name === 'first-paint' && window.__log.paintAt === null) window.__log.paintAt = e.startTime; }).observe({ type: 'paint', buffered: true });` });
await go('/');
await sleep(300);
const lg = await evalJs(`window.__log`);
ok('data-theme="light" posat ANTES del primer pintat (sistema fosc + preferència clara)', lg.themeAt !== null && (lg.paintAt === null || lg.themeAt < lg.paintAt), JSON.stringify(lg));
await send('Page.removeScriptToEvaluateOnNewDocument', { identifier: idScript.identifier });
await evalJs(`localStorage.removeItem('theme')`);

console.log('\n== 4. Focus amb teclat visible i no tapat per la capçalera (tots els temes) ==');
for (const theme of ['dark', 'light']) {
    await setTheme(theme);
    for (const [path, name] of [['/', 'inici'], ['/scores', 'llistat'], ['/scores?per_page=2&page=3', 'llistat paginat'], ['/login', 'login']]) {
        await viewport(1280, 700); await go(path);
        await evalJs(`document.activeElement && document.activeElement.blur(); window.scrollTo(0,0)`);
        const problems = []; let count = 0; const seenEls = new Set();
        for (let i = 0; i < 60; i++) {
            await tab();
            const f = await evalJs(`(() => { const e = document.activeElement; if (!e || e === document.body) return null; let cs = getComputedStyle(e); const card = e.closest('.score'); if (cs.outlineStyle === 'none' && card) { cs = getComputedStyle(card); } const r = e.getBoundingClientRect(); const hdr = document.querySelector('.site-header').getBoundingClientRect(); return { id: e.tagName + '|' + (e.textContent || '').trim().slice(0, 18) + '|' + (e.getAttribute('href') || ''), os: cs.outlineStyle, ow: parseFloat(cs.outlineWidth), oc: cs.outlineColor, top: r.top, bottom: r.bottom, hdr: hdr.bottom, vh: innerHeight, sticky: getComputedStyle(document.querySelector('.site-header')).position, skip: e.classList.contains('skip-link'), inHeader: !!e.closest('.site-header') }; })()`);
            if (!f) continue; if (seenEls.has(f.id)) break; seenEls.add(f.id); count++;
            if (f.os === 'none' || f.ow < 2) problems.push('sense contorn: ' + f.id);
            if (!f.skip && !f.inHeader && f.top < f.hdr - 1 && f.bottom > 0) problems.push('tapat per la capçalera: ' + f.id);
            if (f.bottom > f.vh + 1 || f.top < -1) problems.push('fora de la finestra: ' + f.id);
        }
        ok(`[${theme}] ${name}: ${count} elements enfocables amb Tab, tots amb contorn visible i a la vista`, problems.length === 0 && count > 5, problems.slice(0, 3).join(' | '));
    }
}

console.log('\n== 5. Flux real al navegador: registre -> login -> crear -> editar -> eliminar -> logout ==');
await media('light'); await viewport(1000, 900);
await evalJs(`localStorage.setItem('theme','light')`);
const user = 'test_cdp' + Math.floor(Math.random() * 90000 + 10000);
const pw = 'Prova2026Ok';
const fill = (id, v) => evalJs(`(() => { const e = document.getElementById('${id}'); e.focus(); e.value = ${JSON.stringify(v)}; return true; })()`);
const submit = async () => { const w = waitLoad(); await evalJs(`document.querySelector('main form button[type=submit]').click()`); await w; await sleep(200); };
const body = () => evalJs(`document.body.innerText`);

await go('/register');
await fill('username', user); await fill('password', pw); await fill('password_confirm', 'DiferentOk2026'); await submit();
ok('Registre amb contrasenyes diferents: error visible, focus al resum i el nom es conserva', (await body()).includes('no coincideixen') && await evalJs(`document.activeElement.id === 'resum-errors' && document.getElementById('username').value === ${JSON.stringify(user)} && document.getElementById('password').value === ''`));
await shot('light-registre-errors');
await fill('password', pw); await fill('password_confirm', pw); await submit();
ok('Registre correcte: porta a /login amb missatge d\'èxit', (await evalJs('location.pathname')).endsWith('/login') && (await body()).includes('Compte creat'));
await fill('username', user); await fill('password', pw); await submit();
ok('Login al navegador: arriba a "Les meves scores"', (await evalJs('location.pathname')).endsWith('/my-scores') && (await body()).includes('Hola, ' + user));
ok('Capçalera amb l\'usuari i botó "Tanca la sessió"', (await body()).includes('Tanca la sessió'));

await go('/scores/new');
await shot('light-crea-form');
await fill('beatmap_title', 'Prova del navegador'); await fill('artist', 'Artista X'); await fill('difficulty_name', 'Hard'); await fill('mapper', 'tester');
await fill('star_rating', '4,5'); await fill('bpm', '150'); await fill('cs', '4'); await fill('ar', '8,5');
await evalJs(`document.getElementById('rank_result').value = 'B'`);
await fill('score', '1234567'); await fill('accuracy', '300'); await fill('pp', '123,4'); await fill('max_combo', '500');
await fill('count_300', '400'); await fill('count_100', '30'); await fill('count_50', '5'); await fill('count_miss', '2');
await submit();
ok('Crear amb precisió 300: error al camp, valors conservats, sense crear', (await body()).includes('La precisió ha d') && await evalJs(`document.getElementById('beatmap_title').value === 'Prova del navegador' && document.getElementById('accuracy').getAttribute('aria-invalid') === 'true'`));
await shot('light-crea-errors');
await fill('accuracy', '92,5'); await submit();
ok('Crear correcte: porta al detall amb "Score afegida correctament"', /\/scores\/\d+$/.test(await evalJs('location.pathname')) && (await body()).includes('Score afegida correctament'));
const myId = (await evalJs('location.pathname')).match(/(\d+)$/)[1];
await shot('light-detall-propi');
ok('El detall propi mostra Edita i Elimina', await evalJs(`!!document.querySelector('a[href$="/scores/${myId}/edit"]') && !!document.querySelector('a[href$="/scores/${myId}/delete"]')`));
await go(`/scores/${myId}/edit`);
await fill('beatmap_title', 'Prova EDITADA'); await submit();
ok('Editar: es desa i es veu "Score desada correctament"', (await body()).includes('Score desada correctament') && (await body()).includes('Prova EDITADA'));
await go(`/scores/${myId}/delete`);
ok('Eliminar: primer una pàgina de confirmació (no esborra encara)', (await body()).includes('Vols eliminar aquesta score?') && (await body()).includes('no es pot desfer'));
await shot('light-elimina-confirmacio');
await go(`/scores/${myId}`);
ok('Cancel·lar la confirmació deixa la score intacta', (await evalJs('document.body.innerText')).includes('Prova EDITADA'));
await go(`/scores/${myId}/delete`); await submit();
ok('Confirmar: score eliminada, torna a "Les meves scores" amb missatge', (await evalJs('location.pathname')).endsWith('/my-scores') && (await body()).includes('Score eliminada'));
await go(`/scores/${myId}`);
ok('La score eliminada ja no existeix (404)', (await body()).includes('Pàgina no trobada'));
await go('/scores/1/edit');
ok('Score aliena (id 1) al navegador: 403 "Accés denegat"', (await body()).includes('Accés denegat'));
await go('/scores/1/delete');
ok('Eliminar score aliena (id 1) al navegador: 403', (await body()).includes('Accés denegat'));
await shot('light-403');
await viewport(390, 800); await go('/my-scores');
ok('"Les meves scores" a 390 px sense scroll horitzontal', await evalJs('document.documentElement.scrollWidth <= innerWidth'));
await shot('light-meves-390');
// tanca sessió amb el botó (amb amplada d'escriptori: a 390 px el menú està plegat)
await viewport(1000, 900); await go('/my-scores');
const w2 = waitLoad(); await evalJs(`document.querySelector('form[action$="/logout"] button').click()`); await w2; await sleep(200);
ok('Logout amb el botó: torna a l\'inici com a anònim', (await body()).includes('Has tancat la sessió') && (await body()).includes('Inicia sessió'));

console.log('\n== 6. Pàgines autenticades en tema fosc i clar: contrast real i desbordament ==');
await media('dark'); await viewport(1280, 900);
await go('/login'); await fill('username', 'demo_aiko'); await fill('password', 'Osu!Demo2026'); await submit();
for (const theme of ['dark', 'light']) {
    await setTheme(theme);
    for (const [path, name] of [['/my-scores', 'meves'], ['/scores/new', 'crea'], ['/scores/1/edit', 'edita'], ['/scores/1/delete', 'elimina']]) {
        for (const w of [320, 1280]) {
            await viewport(w); await go(path);
            const sw = await evalJs('({ sw: document.documentElement.scrollWidth, iw: innerWidth })');
            ok(`[${theme}] ${name} @${w}: sense scroll horitzontal`, sw.sw <= sw.iw, JSON.stringify(sw));
            if (w === 1280) {
                const c = await evalJs(CONTRAST_JS);
                ok(`[${theme}] ${name}: contrast real de ${c.n} textos ≥ AA`, c.bad.length === 0, c.bad.slice(0, 4).join(' | '));
                await shot(`${theme}-auth-${name}`);
            }
        }
    }
}
console.log('\n== 7. Dificultat, imatges, URL oficial, paginació i fons animat (al navegador, sessió demo_aiko) ==');
const PUBLIC_DIR = 'C:/xampp/htdocs/Practiques/reDAW2/PRACTIQUES/SERVIDOR_PrjF1/public/';
const uploadsNow = () => readdirSync(PUBLIC_DIR + 'uploads/scores').filter((f) => f !== '.gitkeep').sort();
const uploadsBefore = uploadsNow();
// Nombre real de scores publicades (inclou les que hagi creat l'usuari): cap expectativa assumeix un nombre fix
const PUB_TOTAL = Number(execSync('"C:/xampp/mysql/bin/mysql.exe" -u root -N -e "SELECT COUNT(*) FROM pt04_pol_corsin.scores WHERE is_published = 1"').toString().trim());
const tmp7 = mkdtempSync(join(tmpdir(), 'cdp-img-'));

// --- Imatges de prova generades pel mateix Chrome (captures de pàgines amb degradats): PNG, JPEG i WebP
const makeImage = async (css, format, file) => {
    await viewport(800, 450);
    const w = waitLoad();
    await send('Page.navigate', { url: 'data:text/html;charset=utf-8,' + encodeURIComponent(`<body style="margin:0;width:800px;height:450px;background:${css}">`) });
    await w; await sleep(150);
    const r = await send('Page.captureScreenshot', { format, quality: format === 'png' ? undefined : 85 });
    writeFileSync(join(tmp7, file), Buffer.from(r.data, 'base64'));
};
await makeImage('linear-gradient(135deg,#ffffff,#fffbe0)', 'png', 'blanca.png');                                // pitjor cas: imatge clara
await makeImage('radial-gradient(circle at 30% 30%,#ff9a3c,#b05cff 55%,#14436a)', 'jpeg', 'foto.jpg');
await makeImage('linear-gradient(160deg,#ff5ca8,#ffd43b 60%,#3b9fff)', 'webp', 'posta.webp');
writeFileSync(join(tmp7, 'nota.txt'), 'això no és una imatge');
writeFileSync(join(tmp7, 'fals.png'), 'text dins d\'un fitxer amb extensió .png');
ok('Imatges de prova generades (PNG, JPEG i WebP)', ['blanca.png', 'foto.jpg', 'posta.webp'].every((f) => existsSync(join(tmp7, f))));

// --- Valors esperats de l'escala de color (calculats pel PHP real, helper stars.php)
const starVals = [0, 1, 1.5, 2.25, 3, 3.45, 4, 5, 5.8, 6, 7, 7.5, 8, 9, 9.2, 10];
writeFileSync(join(tmp7, 'stars.php'), `<?php require 'C:/xampp/htdocs/Practiques/reDAW2/PRACTIQUES/SERVIDOR_PrjF1/app/Helpers/stars.php'; echo json_encode(array_map(fn($v) => starStyle($v), json_decode($argv[1])));`);
const phpStars = JSON.parse(execSync(`"C:/xampp/php/php.exe" "${join(tmp7, 'stars.php').replace(/\\/g, '/')}" "[${starVals.join(',')}]"`).toString());

const typeInto = (id, v) => evalJs(`(() => { const e = document.getElementById('${id}'); e.focus(); e.value = ${JSON.stringify(v)}; e.dispatchEvent(new Event('input', { bubbles: true })); return true; })()`);
const setFile = async (selector, path) => {
    const doc = await send('DOM.getDocument', { depth: 0 });
    const q = await send('DOM.querySelector', { nodeId: doc.root.nodeId, selector });
    await send('DOM.setFileInputFiles', { files: [path], nodeId: q.nodeId });
    await sleep(150);
};
const cardPath = () => evalJs(`(() => { const c = document.querySelector('.score--has-image'); if (!c) return null; const m = getComputedStyle(c).getPropertyValue('--card-image').match(/uploads\\/scores\\/[a-f0-9]{32}\\.(jpg|png|webp)/); return m ? m[0] : null; })()`);

await go('/');   // sortim de la pàgina data: (no té localStorage)
await viewport(1100, 900); await media('light'); await setTheme('light');
await go('/scores/new');

// 1) Previsualització en directe de l'escala: mateix color que calcula el PHP
let parity = 0; const diffs = [];
for (let i = 0; i < starVals.length; i++) {
    await typeInto('star_rating', String(starVals[i]).replace('.', i % 2 ? ',' : '.'));
    const got = await evalJs(`(() => { const p = document.querySelector('[data-star-preview]'); const cs = getComputedStyle(p); return { star: p.style.getPropertyValue('--star').trim().toUpperCase(), fill: p.style.getPropertyValue('--star-fill').trim().toUpperCase(), color: cs.color, border: cs.borderTopColor, text: p.textContent.trim() }; })()`);
    if (got.star === phpStars[i].color && got.fill === phpStars[i].fill.toUpperCase() && got.color === got.border) { parity++; } else { diffs.push(`${starVals[i]}: JS ${got.star}/${got.fill} vs PHP ${phpStars[i].color}/${phpStars[i].fill}`); }
}
ok(`Previsualització de dificultat: el JS dóna el MATEIX color i fons que el PHP en ${starVals.length} valors (inclosos decimals), amb número i contorn del mateix color`, parity === starVals.length, diffs.slice(0, 3).join(' | '));
await typeInto('star_rating', '3,45');
ok('El número mostrat a la previsualització és el valor real (3,45)', (await evalJs(`document.querySelector('[data-star-value]').textContent`)) === '3,45');
await shot('light-form-dificultat');

// 2) Camps i pujada: avís del navegador amb un fitxer no vàlid i vista prèvia amb un de vàlid
await fill('beatmap_title', 'Prova d\'imatge i enllaç'); await fill('artist', 'Artista Y'); await fill('difficulty_name', 'Expert'); await fill('mapper', 'tester');
await fill('bpm', '175'); await fill('cs', '4'); await fill('ar', '9'); await typeInto('star_rating', '7,5');
await evalJs(`document.getElementById('rank_result').value = 'S'`);
await fill('score', '2345678'); await fill('accuracy', '98,5'); await fill('pp', '321,1'); await fill('max_combo', '800');
await fill('count_300', '700'); await fill('count_100', '20'); await fill('count_50', '3'); await fill('count_miss', '1');
await fill('official_url', 'https://osu.ppy.sh/scores/osu/4400123456');
await setFile('#image', join(tmp7, 'nota.txt'));
ok('Fitxer que no és imatge: avís al navegador (role="alert") i el camp es buida', await evalJs(`document.querySelector('#image').files.length === 0 && !!document.querySelector('.field-error[role=alert]:not([hidden])') && document.querySelector('[data-image-preview]').hidden`));
await setFile('#image', join(tmp7, 'blanca.png'));
ok('Imatge vàlida: previsualització visible amb text alternatiu', await evalJs(`(() => { const p = document.querySelector('[data-image-preview]'); const i = p.querySelector('img'); return !p.hidden && i.src.startsWith('blob:') && i.alt.length > 5 && !document.querySelector('.field-error[role=alert]:not([hidden])'); })()`));
ok('Cap avís d\'error buit visible (el hidden guanya a display:flex)', await evalJs(`[...document.querySelectorAll('.field-error')].every((e) => getComputedStyle(e).display === 'none' || e.textContent.trim().length > 0)`));
await shot('light-form-previsualitzacio');
// el servidor també valida: un fitxer de text amb extensió .png arriba al servidor i és rebutjat
await setFile('#image', join(tmp7, 'fals.png'));
await submit();
ok('Fitxer fals (.png amb text) enviat: el SERVIDOR el rebutja, es manté el que s\'havia escrit i el focus va al resum', (await body()).includes('no és una imatge vàlida') && await evalJs(`document.activeElement.id === 'resum-errors' && document.getElementById('beatmap_title').value === 'Prova d\\'imatge i enllaç' && document.getElementById('official_url').value.startsWith('https://osu.ppy.sh')`));
ok('Després de l\'error es demana tornar a seleccionar la imatge si hi ha altres errors (o no hi ha cap fitxer desat)', uploadsNow().join() === uploadsBefore.join());
await setFile('#image', join(tmp7, 'blanca.png'));
await submit();
ok('Crear amb imatge i URL oficial: porta al detall amb l\'avís d\'èxit', /\/scores\/\d+$/.test(await evalJs('location.pathname')) && (await body()).includes('Score afegida correctament'));
const newId = (await evalJs('location.pathname')).match(/(\d+)$/)[1];
const img1 = await cardPath();
ok('La imatge s\'ha desat al disc amb nom aleatori (uploads/scores/<32 hex>)', !!img1 && existsSync(PUBLIC_DIR + img1) && uploadsNow().length === uploadsBefore.length + 1, String(img1));

// 3) Targeta amb imatge: aspecte i botó oficial
const card = await evalJs(`(() => { const c = document.querySelector('.score--has-image'); const cs = getComputedStyle(c); return { bg: cs.backgroundImage, size: cs.backgroundSize, pos: cs.backgroundPosition, color: cs.color, text: getComputedStyle(c).getPropertyValue('--text').trim(), stars: c.querySelector('.stars').textContent.trim() }; })()`);
ok('Targeta amb imatge: fons = capa fosca + imatge, mida cover, centrada', card.bg.includes('linear-gradient') && card.bg.includes('url(') && card.size.split(', ').every((x) => x === 'cover') && card.pos.split(', ').every((x) => x === '50% 50%'), card.bg.slice(0, 80));
ok('Targeta amb imatge: usa text clar també al tema clar (tokens fosques locals)', card.color === 'rgb(244, 241, 251)' && card.text.toLowerCase() === '#f4f1fb', card.color);
ok('La imatge es carrega de debò (200, image/*, amplada natural > 0)', await evalJs(`(async () => { const u = getComputedStyle(document.querySelector('.score--has-image')).backgroundImage.match(/url\\("?([^")]+)"?\\)/)[1]; const r = await fetch(u); const img = new Image(); img.src = u; await img.decode(); return r.status === 200 && r.headers.get('content-type').startsWith('image/') && img.naturalWidth > 0; })()`));
const off = await evalJs(`(() => { const a = document.querySelector('a.btn--official'); if (!a) return null; return { href: a.href, target: a.target, rel: a.rel, lang: a.lang, text: a.textContent.replace(/\\s+/g, ' ').trim(), hasIcon: !!a.querySelector('svg[aria-hidden=true]') }; })()`);
ok('Botó "Official score": enllaç oficial, target=_blank, rel noopener noreferrer, lang=en, text clar', !!off && off.href === 'https://osu.ppy.sh/scores/osu/4400123456' && off.target === '_blank' && off.rel.includes('noopener') && off.rel.includes('noreferrer') && off.lang === 'en' && off.text.startsWith('Official score') && off.text.includes('pestanya nova') && off.hasIcon, JSON.stringify(off));
const stSame = await evalJs(`[...document.querySelectorAll('.stars')].every((s) => { const cs = getComputedStyle(s); return cs.color === cs.borderTopColor; })`);
ok('Dificultat al detall: número i contorn comparteixen color', stSame);
await shot('light-card-imatge-blanca');
for (const theme of ['dark', 'light']) {
    await setTheme(theme);
    for (const w of [320, 768, 1280]) {
        await viewport(w); await go('/scores/' + newId);
        const o = await evalJs('({ sw: document.documentElement.scrollWidth, iw: innerWidth })');
        ok(`[${theme}] detall amb imatge @${w}: sense scroll horitzontal`, o.sw <= o.iw, JSON.stringify(o));
        if (w === 1280) {
            const c = await evalJs(CONTRAST_JS);
            ok(`[${theme}] detall amb imatge de fons: contrast PITJOR CAS (imatge blanca o negra) de ${c.n} textos ≥ AA`, c.bad.length === 0, c.bad.slice(0, 4).join(' | '));
            await shot(`${theme}-card-imatge`);
        }
    }
}

// 4) Editar: substituir (JPEG) -> el fitxer vell s'esborra; treure -> s'esborra; tornar a pujar (WebP)
await viewport(1100, 900); await setTheme('light');
await go(`/scores/${newId}/edit`);
ok('Editar: es mostra la imatge actual i la casella "Elimina la imatge actual"', await evalJs(`!!document.querySelector('.image-current img') && !!document.getElementById('remove_image') && document.querySelector('label[for=remove_image]').textContent.includes('Elimina')`));
await setFile('#image', join(tmp7, 'foto.jpg')); await submit();
const img2 = await cardPath();
ok('Substituir per JPEG: ruta nova, fitxer nou al disc i el VELL esborrat', !!img2 && img2 !== img1 && img2.endsWith('.jpg') && existsSync(PUBLIC_DIR + img2) && !existsSync(PUBLIC_DIR + img1), `${img1} -> ${img2}`);
await shot('light-card-imatge-foto');
await go(`/scores/${newId}/edit`);
await evalJs(`document.getElementById('remove_image').click()`); await submit();
ok('Treure la imatge: la targeta torna a l\'aspecte per defecte i el fitxer s\'esborra', (await cardPath()) === null && !existsSync(PUBLIC_DIR + img2) && uploadsNow().join() === uploadsBefore.join());
await go(`/scores/${newId}/edit`); await setFile('#image', join(tmp7, 'posta.webp')); await submit();
const img3 = await cardPath();
ok('Pujar una imatge WebP (posta de sol): es desa i es mostra', !!img3 && img3.endsWith('.webp') && existsSync(PUBLIC_DIR + img3));
await shot('light-card-imatge-webp');
await setTheme('dark'); await go('/scores/' + newId); await shot('dark-card-imatge-webp');

// 5) Eliminar la score amb confirmació: l'arxiu desapareix
await go(`/scores/${newId}/delete`); await submit();
ok('Eliminar la score: s\'esborra la fila i la seva imatge del disc', (await body()).includes('Score eliminada') && !existsSync(PUBLIC_DIR + img3) && uploadsNow().join() === uploadsBefore.join());

// 5b) Targeta compacta: una sola superfície, sense caixes interiors, alçada ajustada al contingut i jerarquia visual
const LAYOUT_JS = (sel) => `(() => {
  const c = document.querySelector(${JSON.stringify(sel)}); const cr = c.getBoundingClientRect();
  const q = (s) => c.querySelector(s); const rect = (s) => q(s).getBoundingClientRect(); const fs = (s) => parseFloat(getComputedStyle(q(s)).fontSize);
  const id = rect('.score__id'), res = rect('.score__result'), foot = rect('.score__foot');
  // "Caixes": elements grans (més del 40 % de l'amplada i 50 px d'alt) amb fons propi dins la targeta
  const boxes = [...c.querySelectorAll('*')].filter((e) => { const r = e.getBoundingClientRect(); const m = getComputedStyle(e).backgroundColor.match(/rgba?\\(([^)]+)\\)/); const alpha = m ? (m[1].split(/[ ,\\/]+/)[3] ?? 1) : 0; return r.width > cr.width * 0.4 && r.height > 50 && Number(alpha) > 0; });
  const fixedH = [...c.querySelectorAll('*')].filter((e) => /px/.test(e.style.height || '')).length;
  return { h: Math.round(cr.height), sideBySide: res.left >= id.right - 1 && Math.abs(res.top - id.top) < 30, stacked: res.top >= id.bottom - 1, footBelow: foot.top >= Math.max(id.bottom, res.bottom) - 1,
    boxes: boxes.length, panels: c.querySelectorAll('.score__panel, .score__map, .score__diff-top').length, fixedH, overflowX: c.scrollWidth > c.clientWidth + 1,
    title: fs('.score__title'), points: fs('.points'), acc: fs('.acc'), diff: fs('.score__diff-name'), player: fs('.score__player-name'), meta: fs('.score__player-meta'),
    hasAll: ['.stars', '.score__diff-name', '.diff-stats', '.rank', '.points', '.acc', '.hits', '.hit--pp', '.hit--plain', '.avatar', '.score__player-name', '.player-rank', '.player-pp', '.player-date'].every((s) => !!q(s)),
    statsN: c.querySelectorAll('.diff-stats div').length, hitsN: c.querySelectorAll('.hits li').length };
})()`;
await setTheme('dark');
for (const [w, maxH] of [[1280, 215], [768, 215], [390, 380]]) {
    await viewport(w); await go('/scores?per_page=4');
    const L = await evalJs(LAYOUT_JS('.score:not(:has(.score__actions))'));
    ok(`Targeta compacta @${w}: alçada ajustada (${L.h}px ≤ ${maxH}px), sense alçades fixes, sense desbordament`, L.h <= maxH && L.fixedH === 0 && !L.overflowX, JSON.stringify(L));
    ok(`Targeta @${w}: una sola superfície (cap caixa interior amb fons propi, cap panell antic)`, L.boxes === 0 && L.panels === 0, `caixes=${L.boxes} panells=${L.panels}`);
    ok(`Targeta @${w}: ${w >= 640 ? 'rang+score a la dreta del títol (mateixa fila)' : 'rang+score sota la dificultat (apilats)'}; dades i jugador a sota`, (w >= 640 ? L.sideBySide : L.stacked) && L.footBelow, JSON.stringify({ s: L.sideBySide, st: L.stacked, f: L.footBelow }));
    ok(`Targeta @${w}: jerarquia (score ${L.points}px > títol ${L.title}px > nom dificultat ${L.diff}px; jugador ${L.player}px i metadades ${L.meta}px són secundaris)`, L.points > L.title && L.title > L.diff && L.title > L.player && L.player <= 17 && L.meta <= 14 && L.points >= 28 && L.title >= 20, JSON.stringify(L));
    ok(`Targeta @${w}: conserva tota la informació (estrelles, CS/AR/BPM, rang, score, precisió, 4 cops + PP + combo, jugador amb #rang, PP i data)`, L.hasAll && L.statsN === 3 && L.hitsN === 6);
}
await viewport(1280); await go('/my-scores');
const LA = await evalJs(LAYOUT_JS('.score'));
ok(`Targeta amb accions (Edita/Elimina) @1280: continua compacta (${LA.h}px ≤ 260px) i sense caixes interiors`, LA.h <= 260 && LA.boxes === 0, JSON.stringify(LA));
await viewport(390); await go('/my-scores');
const LM = await evalJs(LAYOUT_JS('.score'));
ok(`Targeta amb accions @390: sense desbordament i sense caixes (${LM.h}px)`, !LM.overflowX && LM.boxes === 0 && LM.h <= 420, JSON.stringify(LM));
await shot('dark-card-compacta-mobil');
await viewport(1280); await go('/'); const LH = await evalJs(LAYOUT_JS('.score'));
ok('Targeta destacada de l\'inici: compacta i sense caixes interiors', LH.boxes === 0 && LH.panels === 0 && !LH.overflowX, JSON.stringify(LH));
await setTheme('light'); await viewport(1280); await go('/scores?per_page=4'); await shot('light-card-compacta');
await viewport(768); await go('/scores?per_page=2'); await shot('light-card-compacta-tauleta');
await viewport(1280); await go('/scores/' + newId + '/edit'); // (la score de prova ja està eliminada; aquesta ruta només comprova el 404)
ok('La score de prova eliminada dóna 404 també a l\'edició', (await body()).includes('Pàgina no trobada'));

// 6) Paginació al navegador: mòbil i escriptori
await setTheme('dark');
await viewport(390, 900); await go('/scores?per_page=2&page=3');
const pm = await evalJs(`(() => { const nav = document.querySelector('nav.pager'); return { numHidden: [...nav.querySelectorAll('.pager__num')].every((n) => getComputedStyle(n).display === 'none'), ctl: nav.querySelectorAll('.pager__ctl').length, current: nav.querySelector('[aria-current=page]').textContent.replace(/\\s+/g, ' ').trim(), sw: document.documentElement.scrollWidth, iw: innerWidth, label: nav.getAttribute('aria-label'), info: nav.querySelector('.pager__info').textContent.trim(), maxRight: Math.max(...[...nav.querySelectorAll('li')].filter((l) => l.offsetParent).map((l) => l.getBoundingClientRect().right)) }; })()`);
ok('Paginació en mòbil (390 px): només Primera/Anterior/actual/Següent/Última, sense scroll horitzontal', pm.numHidden && pm.ctl === 4 && pm.current === 'Pàgina 3' && pm.sw <= pm.iw && pm.maxRight <= pm.iw && pm.label === 'Paginació' && pm.info === `Pàgina 3 de ${Math.ceil(PUB_TOTAL / 2)}.`, JSON.stringify(pm));
await shot('dark-paginacio-mobil');
await viewport(1280, 900); await go('/scores?per_page=2&page=3');
ok('Paginació a l\'escriptori: es veuen els números veïns (2, 3, 4)', await evalJs(`[...document.querySelectorAll('nav.pager li a')].map((a) => a.textContent.replace(/\\s+/g, ' ').trim()).join('|')`) === 'Primera|Anterior|Pàgina 2|Pàgina 3|Pàgina 4|Següent|Última');
// Teclat: es pot arribar a tots els controls amb Tab i es veuen amb focus
await evalJs(`document.activeElement.blur(); window.scrollTo(0, 0)`);
let reached = new Set(), badFocus = [];
for (let i = 0; i < 80 && reached.size < 5; i++) {
    await tab();
    const f = await evalJs(`(() => { const e = document.activeElement; if (!e || !e.closest('nav.pager')) return null; const cs = getComputedStyle(e); return { t: e.textContent.replace(/\\s+/g, ' ').trim(), ow: parseFloat(cs.outlineWidth), os: cs.outlineStyle }; })()`);
    if (f) { reached.add(f.t); if (f.os === 'none' || f.ow < 2) { badFocus.push(f.t); } }
}
ok('Teclat: Tab arriba als controls de la paginació i tots mostren focus visible', reached.size >= 5 && badFocus.length === 0, [...reached].join(',') + ' | sense focus: ' + badFocus.join(','));
for (const [theme, w] of [['dark', 320], ['light', 320], ['dark', 768], ['light', 1280]]) {
    await setTheme(theme); await viewport(w); await go('/scores?sort=pp&rank=A&per_page=2');
    const o = await evalJs('({ sw: document.documentElement.scrollWidth, iw: innerWidth })');
    ok(`[${theme}] llistat amb filtre, ordre i mida @${w}: sense scroll horitzontal`, o.sw <= o.iw, JSON.stringify(o));
    if (w === 1280) { const c = await evalJs(CONTRAST_JS); ok(`[${theme}] llistat paginat: contrast real de ${c.n} textos ≥ AA`, c.bad.length === 0, c.bad.slice(0, 3).join(' | ')); }
}
await setTheme('dark'); await viewport(1280, 900); await go('/scores?per_page=10');
ok('Llistat: totes les pastilles d\'estrelles tenen número i contorn del mateix color', await evalJs(`(() => { const s = [...document.querySelectorAll('.stars')]; return s.length >= 10 && s.every((x) => { const cs = getComputedStyle(x); return cs.color === cs.borderTopColor; }); })()`));
const colorsSeen = await evalJs(`new Set([...document.querySelectorAll('.stars')].map((x) => getComputedStyle(x).color)).size`);
ok('Llistat: scores amb dificultats diferents mostren colors diferents', colorsSeen >= 8, String(colorsSeen));
ok('Rang del jugador i data sense frases explicatives (al navegador)', await evalJs(`(() => { const t = document.body.innerText; return !/per PP total/.test(t) && !/Publicada el/.test(t) && /#\\d+\\s+(PP totals\\s+)?[\\d.,]+ pp/.test(t) && /\\b\\d{2}\\/\\d{2}\\/\\d{4}\\b/.test(t); })()`));

// 7) Fons animat
for (const theme of ['dark', 'light']) {
    await setTheme(theme); await media('no-preference'); await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'no-preference' }] });
    await viewport(1280, 800); await go('/');
    const bgInfo = await evalJs(`(() => { const b = getComputedStyle(document.body), a = getComputedStyle(document.body, '::before'), c = getComputedStyle(document.body, '::after'); return { img: b.backgroundImage, an: a.animationName + '/' + c.animationName, dur: a.animationDuration + '/' + c.animationDuration, pos: a.position, pe: a.pointerEvents, z: a.zIndex, op: a.opacity, grad: a.backgroundImage.includes('radial-gradient') && c.backgroundImage.includes('radial-gradient') }; })()`);
    ok(`[${theme}] Fons: sense rejilla (cap background-image al body) i dues capes animades de degradats radials, fixes, per darrere i sense interceptar clics`, bgInfo.img === 'none' && bgInfo.an === 'glow-a/glow-b' && bgInfo.dur === '80s/110s' && bgInfo.pos === 'fixed' && bgInfo.pe === 'none' && bgInfo.z === '-1' && bgInfo.grad, JSON.stringify(bgInfo));
    await shot(`${theme}-fons-inici`);
    await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }, { name: 'prefers-color-scheme', value: theme }] });
    const rm = await evalJs(`(() => { const a = getComputedStyle(document.body, '::before'), c = getComputedStyle(document.body, '::after'); return a.animationName + '/' + c.animationName; })()`);
    ok(`[${theme}] Fons amb prefers-reduced-motion: les animacions s'aturen (animation-name none)`, rm === 'none/none', rm);
    await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'no-preference' }] });
}
// El fons no tapa el contingut: un clic al botó principal de l'inici arriba al botó
await go('/'); ok('Els clics arriben al contingut (el fons no interfereix)', await evalJs(`(() => { const b = document.querySelector('.hero .btn--primary'); const r = b.getBoundingClientRect(); return document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2).closest('a') === b; })()`));
await setTheme('dark');
ok('Cap fitxer d\'imatge orfe a uploads/ després de tot el flux', uploadsNow().join() === uploadsBefore.join(), uploadsNow().join());


// logout i neteja del compte de prova (cascada elimina les seves scores)
const w3 = waitLoad(); await evalJs(`document.querySelector('form[action$="/logout"] button').click()`); await w3;
try { execSync(`"C:/xampp/mysql/bin/mysql.exe" -u root -e "DELETE FROM pt04_pol_corsin.users WHERE username LIKE 'test\\\\_cdp%'"`); } catch (e) { console.log('  (no s\'ha pogut netejar l\'usuari de prova: ' + e.message + ')'); }

ws.close(); chrome.kill();
console.log(`\n==============================\nResultat: ${pass} correctes, ${fail} fallades`);
if (fail) { console.log('Fallades:\n - ' + failures.join('\n - ')); }
process.exit(fail ? 1 : 0);
