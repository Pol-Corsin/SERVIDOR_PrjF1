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
import { mkdirSync, mkdtempSync } from 'node:fs';
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
  const bgOf = (el) => { const stack = []; for (let e = el; e; e = e.parentElement) { const c = parse(getComputedStyle(e).backgroundColor); if (c && c.a > 0) { stack.push(c); if (c.a >= 0.999) break; } } let base = { r: 255, g: 255, b: 255, a: 1 }; for (let i = stack.length - 1; i >= 0; i--) base = over(stack[i], base); return base; };
  const bad = []; let n = 0;
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  const seen = new Set();
  while (walker.nextNode()) {
    const t = walker.currentNode; if (!t.nodeValue.trim()) continue;
    const el = t.parentElement; if (!el || seen.has(el)) continue; seen.add(el);
    const cs = getComputedStyle(el); if (cs.visibility === 'hidden' || cs.display === 'none') continue;
    const r = el.getBoundingClientRect(); if (r.width === 0 || r.height === 0) continue;
    if (el.closest('.visually-hidden, [hidden]')) continue;
    let fg = parse(cs.color); if (!fg) continue;
    const bg = bgOf(el); fg = over(fg, bg);
    const L1 = lum(fg), L2 = lum(bg); const ratio = (Math.max(L1, L2) + 0.05) / (Math.min(L1, L2) + 0.05);
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
    for (const [path, name] of [['/', 'inici'], ['/scores', 'llistat'], ['/login', 'login']]) {
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
// logout i neteja del compte de prova (cascada elimina les seves scores)
const w3 = waitLoad(); await evalJs(`document.querySelector('form[action$="/logout"] button').click()`); await w3;
try { execSync(`"C:/xampp/mysql/bin/mysql.exe" -u root -e "DELETE FROM pt04_pol_corsin.users WHERE username LIKE 'test\\\\_cdp%'"`); } catch (e) { console.log('  (no s\'ha pogut netejar l\'usuari de prova: ' + e.message + ')'); }

ws.close(); chrome.kill();
console.log(`\n==============================\nResultat: ${pass} correctes, ${fail} fallades`);
if (fail) { console.log('Fallades:\n - ' + failures.join('\n - ')); }
process.exit(fail ? 1 : 0);
