<?php
/**
 * Autor: Pol Corsin — PT04 osu! Scores
 * Tests d'integració AUTOMATITZATS (eina de desenvolupament, no forma part de la web).
 * Fan peticions HTTP reals a l'Apache de XAMPP i comproven també la base de dades.
 *
 * Ús (Apache i MySQL engegats):
 *     C:\xampp\php\php.exe tools\tests.php [url_base]
 *
 * Només toquen dades pròpies: creen usuaris "test_xxxx" i les seves scores, i els esborren al final.
 * Les scores de demostració NO es modifiquen.
 */

$base = rtrim($argv[1] ?? 'http://localhost/Practiques/reDAW2/PRACTIQUES/SERVIDOR_PrjF1', '/');
$root = dirname(__DIR__);
$config = require $root . '/config/config.php';
$pdo = new PDO("mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset=utf8mb4",
    $config['db']['user'], $config['db']['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

$pass = 0; $fail = 0; $failures = [];
function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail, $failures;
    if ($ok) { $pass++; echo "  OK    $name\n"; }
    else { $fail++; $failures[] = $name; echo "  FAIL  $name" . ($detail ? "  [$detail]" : '') . "\n"; }
}
function section(string $t): void { echo "\n== $t ==\n"; }

/** Petició HTTP sense seguir redireccions. Retorna ['status','headers','body','location','cookies']. */
function req(string $method, string $path, array $data = [], string $jar = ''): array
{
    global $base;
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 20,
        CURLOPT_CUSTOMREQUEST => $method,
    ]);
    if ($jar !== '') { curl_setopt($ch, CURLOPT_COOKIEFILE, $jar); curl_setopt($ch, CURLOPT_COOKIEJAR, $jar); }
    if ($method === 'POST') { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); }
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hsize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $headers = substr($raw, 0, $hsize);
    preg_match('/^Location:\s*(\S+)/mi', $headers, $m);
    preg_match_all('/^Set-Cookie:\s*(.+)$/mi', $headers, $c);
    return ['status' => $status, 'headers' => $headers, 'body' => substr($raw, $hsize), 'location' => $m[1] ?? '', 'cookies' => $c[1] ?? []];
}
function csrf(string $path, string $jar): string
{
    $r = req('GET', $path, [], $jar);
    preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $r['body'], $m);
    return $m[1] ?? '';
}
function sessionId(string $jar): string
{
    foreach (file($jar) as $line) {
        $p = explode("\t", trim($line));
        if (count($p) >= 7 && $p[5] === 'OSUSCORESID') { return $p[6]; }
    }
    return '';
}
function newJar(): string { $j = tempnam(sys_get_temp_dir(), 'jar'); return $j; }

$errorLog = $root . '/storage/logs/error.log';
$logBefore = is_file($errorLog) ? filesize($errorLog) : 0;
$suffix = substr(bin2hex(random_bytes(4)), 0, 6);
$user = "test_$suffix";
$goodPass = 'Prova2026Ok';

try {
    // -------------------------------------------------------------------------------------
    section('1. Rutes públiques i errors');
    foreach (['/' => 200, '/scores' => 200, '/scores/1' => 200, '/login' => 200, '/register' => 200, '/guia-estils' => 200,
              '/scores/99999' => 404, '/scores/abc' => 404, '/no-existeix' => 404, '/scores/1%20OR%201=1' => 404] as $p => $code) {
        $r = req('GET', $p);
        check("GET $p -> $code", $r['status'] === $code, "rebut {$r['status']}");
    }
    check('Anònim: /my-scores redirigeix a /login', ($r = req('GET', '/my-scores'))['status'] === 302 && str_ends_with($r['location'], '/login'));
    check('Anònim: GET /scores/1/edit redirigeix a /login', ($r = req('GET', '/scores/1/edit'))['status'] === 302 && str_ends_with($r['location'], '/login'));
    check('Anònim: POST /scores/1/edit NO modifica res (302 a login)', ($r = req('POST', '/scores/1/edit', ['beatmap_title' => 'hack']))['status'] === 302);
    $r = req('GET', '/scores/1');
    check('Anònim: la pàgina de detall NO mostra botó "Edita"', !str_contains($r['body'], '/edit'));
    foreach (['/config/config.php', '/app/Core/Router.php', '/database/pt04_pol_corsin.sql', '/tools/tests.php', '/storage/logs/error.log', '/README.md'] as $p) {
        check("Fitxer intern bloquejat: $p", req('GET', $p)['status'] === 403);
    }

    // -------------------------------------------------------------------------------------
    section('2. Llistat públic: ordre, filtre i paginació');
    $count = fn(string $body) => substr_count($body, 'class="score"') + substr_count($body, 'class="score score--featured"');
    check('Pàgina 1: 6 scores', $count(req('GET', '/scores')['body']) === 6);
    check('Pàgina 2: 6 scores', $count(req('GET', '/scores?page=2')['body']) === 6);
    check('Pàgina inexistent (99) es corregeix a l\'última', $count(req('GET', '/scores?page=99')['body']) === 6);
    check('Pàgina invàlida (abc, -3) -> pàgina 1', $count(req('GET', '/scores?page=abc')['body']) === 6 && req('GET', '/scores?page=-3')['status'] === 200);
    check('Filtre rang SS -> 2 scores', $count(req('GET', '/scores?rank=SS')['body']) === 2);
    check('Filtre rang invàlid s\'ignora (6 scores)', $count(req('GET', '/scores?rank=ZZ')['body']) === 6);
    $r = req('GET', "/scores?sort=" . urlencode("pp; DROP TABLE scores;--"));
    check('Ordre amb injecció SQL s\'ignora (200, 6 scores)', $r['status'] === 200 && $count($r['body']) === 6);
    check('Taula scores intacta després de la injecció', (int) $pdo->query('SELECT COUNT(*) c FROM scores WHERE id <= 12')->fetch()['c'] === 12);
    $pp = req('GET', '/scores?sort=pp')['body'];
    preg_match('/score__title"><a[^>]*>([^<]+)/', $pp, $m);
    check('Ordenat per PP: el primer és el de més PP (Midnight Circuit, 412,60)', ($m[1] ?? '') === 'Midnight Circuit', $m[1] ?? '-');
    check('Paginació: nav amb aria-label i aria-current="page"', str_contains($pp, 'aria-label="Paginació"') && str_contains($pp, 'aria-current="page"'));
    $r = req('GET', '/scores?rank=SS&page=2');
    check('Estat buit no es dispara amb filtre SS (pàgina corregida)', $r['status'] === 200 && $count($r['body']) === 2);

    // -------------------------------------------------------------------------------------
    section('3. Registre');
    $jar = newJar();
    $tok = csrf('/register', $jar);
    check('Formulari de registre inclou token CSRF', strlen($tok) === 64);
    $r = req('POST', '/register', ['username' => $user, 'password' => $goodPass, 'password_confirm' => $goodPass], $jar);
    check('POST sense CSRF -> 403', $r['status'] === 403);
    $r = req('POST', '/register', ['csrf_token' => 'invalid', 'username' => $user, 'password' => $goodPass, 'password_confirm' => $goodPass], $jar);
    check('POST amb CSRF invàlid -> 403', $r['status'] === 403);

    $weak = 'abc123';
    $r = req('POST', '/register', ['csrf_token' => $tok, 'username' => $user, 'password' => $weak, 'password_confirm' => $weak], $jar);
    check('Contrasenya dèbil -> 422 amb error', $r['status'] === 422 && str_contains($r['body'], 'almenys 8 caràcters'));
    check('Error de registre: es conserva el nom d\'usuari', str_contains($r['body'], 'value="' . $user . '"'));
    check('Error de registre: la contrasenya NO es repobla', !str_contains($r['body'], $weak) && !preg_match('/type="password"[^>]*value=/', $r['body']));
    check('Error accessible: resum amb role="alert" + aria-invalid + aria-describedby', str_contains($r['body'], 'role="alert"') && str_contains($r['body'], 'aria-invalid="true"') && str_contains($r['body'], 'aria-describedby'));

    $r = req('POST', '/register', ['csrf_token' => $tok, 'username' => $user, 'password' => 'sensemajuscula1', 'password_confirm' => 'sensemajuscula1'], $jar);
    check('Contrasenya sense majúscula -> 422', $r['status'] === 422 && str_contains($r['body'], 'majúscula'));
    $r = req('POST', '/register', ['csrf_token' => $tok, 'username' => $user, 'password' => $goodPass, 'password_confirm' => 'Diferent2026Ok'], $jar);
    check('Confirmació diferent -> 422', $r['status'] === 422 && str_contains($r['body'], 'no coincideixen'));
    $r = req('POST', '/register', ['csrf_token' => $tok, 'username' => 'demo_aiko', 'password' => $goodPass, 'password_confirm' => $goodPass], $jar);
    check('Nom d\'usuari duplicat (demo_aiko) -> 422', $r['status'] === 422 && str_contains($r['body'], 'ja està en ús'));
    foreach (['ab', 'nom amb espais', 'àccent', str_repeat('a', 21), "x'; DROP TABLE users;--"] as $bad) {
        $r = req('POST', '/register', ['csrf_token' => $tok, 'username' => $bad, 'password' => $goodPass, 'password_confirm' => $goodPass], $jar);
        check('Nom d\'usuari invàlid rebutjat: ' . substr($bad, 0, 12), $r['status'] === 422);
    }
    check('Cap usuari invàlid s\'ha creat', (int) $pdo->query("SELECT COUNT(*) c FROM users WHERE username LIKE 'x%DROP%' OR username = 'ab'")->fetch()['c'] === 0);

    $r = req('POST', '/register', ['csrf_token' => $tok, 'username' => $user, 'password' => $goodPass, 'password_confirm' => $goodPass], $jar);
    check('Registre correcte -> 302 a /login', $r['status'] === 302 && str_ends_with($r['location'], '/login'));
    $row = $pdo->prepare('SELECT * FROM users WHERE username = ?'); $row->execute([$user]); $u = $row->fetch();
    check('Usuari creat a la BD', (bool) $u);
    check('Contrasenya guardada com a hash bcrypt/argon (no text pla)', $u && $u['password_hash'] !== $goodPass && str_starts_with($u['password_hash'], '$2y$') && password_verify($goodPass, $u['password_hash']));
    check('El hash no conté la contrasenya en cap lloc', $u && !str_contains($u['password_hash'], $goodPass));
    $r = req('GET', '/login', [], $jar);
    check('Missatge d\'èxit del registre mostrat a /login (flash)', str_contains($r['body'], 'Compte creat correctament'));
    $r = req('GET', '/login', [], $jar);
    check('El missatge flash només es mostra UNA vegada', !str_contains($r['body'], 'Compte creat correctament'));

    // -------------------------------------------------------------------------------------
    section('4. Inici de sessió i cookies');
    $jar = newJar();
    $tok = csrf('/login', $jar);
    $sidAnon = sessionId($jar);
    $r = req('POST', '/login', ['username' => $user, 'password' => $goodPass], $jar);
    check('Login sense CSRF -> 403', $r['status'] === 403);
    $r = req('POST', '/login', ['csrf_token' => $tok, 'username' => $user, 'password' => 'Incorrecta2026'], $jar);
    $msgWrongPass = preg_match('/alert__title">([^<]+)/', $r['body'], $m) ? $m[1] : '';
    check('Contrasenya incorrecta -> 401 amb missatge genèric', $r['status'] === 401 && str_contains($msgWrongPass, 'no són correctes'));
    $r = req('POST', '/login', ['csrf_token' => $tok, 'username' => 'no_existeix_' . $suffix, 'password' => 'Incorrecta2026'], $jar);
    $msgNoUser = preg_match('/alert__title">([^<]+)/', $r['body'], $m) ? $m[1] : '';
    check('Usuari inexistent -> MATEIX missatge (no revela si existeix)', $r['status'] === 401 && $msgNoUser === $msgWrongPass);
    check('Login fallit: no es repobla la contrasenya', !str_contains($r['body'], 'Incorrecta2026'));
    check('Login fallit: no hi ha sessió iniciada', req('GET', '/my-scores', [], $jar)['status'] === 302);

    $tok = csrf('/login', $jar);
    $r = req('POST', '/login', ['csrf_token' => $tok, 'username' => $user, 'password' => $goodPass], $jar);
    check('Login correcte -> 302 a /my-scores', $r['status'] === 302 && str_ends_with($r['location'], '/my-scores'));
    $sidAuth = sessionId($jar);
    check('L\'ID de sessió es REGENERA després del login', $sidAnon !== '' && $sidAuth !== '' && $sidAnon !== $sidAuth);
    $cookie = implode(' | ', $r['cookies']);
    check('Cookie de sessió: HttpOnly', stripos($cookie, 'HttpOnly') !== false, $cookie);
    check('Cookie de sessió: SameSite=Lax', stripos($cookie, 'SameSite=Lax') !== false, $cookie);
    check('Cookie de sessió: sense Secure a HTTP (s\'activa sola amb HTTPS)', stripos($cookie, 'secure') === false);
    check('Cookie de sessió: no conté usuari ni contrasenya', !str_contains($cookie, $user) && !str_contains($cookie, $goodPass));
    $r = req('GET', '/my-scores', [], $jar);
    check('Sessió iniciada: /my-scores 200 i capçalera amb l\'usuari', $r['status'] === 200 && str_contains($r['body'], $user) && str_contains($r['body'], 'Tanca la sessió'));
    check('Missatge de benvinguda (flash) a la primera pàgina després del login', str_contains($r['body'], 'Hola, ' . $user));
    check('Ja amb sessió: /login redirigeix a /my-scores', ($r = req('GET', '/login', [], $jar))['status'] === 302);
    check('Usuari sense scores veu l\'estat buit', str_contains(req('GET', '/my-scores', [], $jar)['body'], 'Encara no tens cap score'));

    // -------------------------------------------------------------------------------------
    section('5. Edició de scores i propietat (autorització)');
    $pdo->prepare("INSERT INTO scores (user_id, beatmap_title, artist, difficulty_name, mapper, star_rating, bpm, cs, ar, score, accuracy, pp, max_combo, count_300, count_100, count_50, count_miss, rank_result, is_published)
                   VALUES (?, 'Test Beatmap', 'Test Artist', 'Normal', 'tester', 3.5, 120, 4, 8, 1000000, 90.5, 50, 300, 250, 20, 5, 1, 'B', 1)")->execute([$u['id']]);
    $myId = (int) $pdo->lastInsertId();
    $other = $pdo->query("SELECT id, beatmap_title, user_id FROM scores WHERE id = 1")->fetch();
    $snapshot = $pdo->query('SELECT * FROM scores WHERE id = 1')->fetch();

    $r = req('GET', "/scores/$myId/edit", [], $jar);
    check('Propietari: formulari d\'edició 200', $r['status'] === 200 && str_contains($r['body'], 'Test Beatmap'));
    check('Formulari: etiquetes <label for> per a cada camp', substr_count($r['body'], '<label for=') >= 14);
    check('Detall propi mostra botó "Edita"', str_contains(req('GET', "/scores/$myId", [], $jar)['body'], "/scores/$myId/edit"));
    check('Detall d\'una altra persona NO mostra "Edita"', !str_contains(req('GET', '/scores/1', [], $jar)['body'], '/scores/1/edit'));

    check('AUTORITZACIÓ: GET edit de score aliena -> 403', req('GET', '/scores/1/edit', [], $jar)['status'] === 403);
    $tokEdit = csrf("/scores/$myId/edit", $jar);
    $evil = ['csrf_token' => $tokEdit, 'beatmap_title' => 'HACKEJAT', 'artist' => 'x', 'difficulty_name' => 'x', 'mapper' => 'x', 'star_rating' => '1', 'bpm' => '100',
             'cs' => '4', 'ar' => '8', 'score' => '1', 'accuracy' => '50', 'pp' => '1', 'max_combo' => '1', 'count_300' => '1', 'count_100' => '1', 'count_50' => '1', 'count_miss' => '1',
             'rank_result' => 'D', 'is_published' => '1'];
    check('AUTORITZACIÓ: POST edit de score aliena -> 403', req('POST', '/scores/1/edit', $evil, $jar)['status'] === 403);
    check('La score aliena NO ha canviat', $pdo->query('SELECT * FROM scores WHERE id = 1')->fetch() == $snapshot);
    check('Edit d\'una score inexistent -> 404', req('GET', '/scores/999999/edit', [], $jar)['status'] === 404);

    $good = $evil; $good['beatmap_title'] = '<script>alert(1)</script> Nou títol'; $good['star_rating'] = '5,31'; $good['accuracy'] = '98,75'; $good['rank_result'] = 'S';
    $good['user_id'] = '1'; $good['id'] = '1'; // intent de manipular propietari/id des del formulari: s'ha d'ignorar
    $r = req('POST', "/scores/$myId/edit", array_merge($good, ['csrf_token' => '']), $jar);
    check('Edició sense CSRF -> 403', $r['status'] === 403);
    $r = req('POST', "/scores/$myId/edit", $good, $jar);
    check('Edició vàlida -> 302 al detall', $r['status'] === 302 && str_ends_with($r['location'], "/scores/$myId"), "{$r['status']} {$r['location']}");
    $db = $pdo->query("SELECT * FROM scores WHERE id = $myId")->fetch();
    check('BD actualitzada (títol, estrelles amb coma, precisió, rang)', $db['beatmap_title'] === $good['beatmap_title'] && (float) $db['star_rating'] === 5.31 && (float) $db['accuracy'] === 98.75 && $db['rank_result'] === 'S');
    check('user_id enviat pel navegador s\'ignora (el propietari no canvia)', (int) $db['user_id'] === (int) $u['id']);
    check('La score 1 segueix intacta després de tot', $pdo->query('SELECT * FROM scores WHERE id = 1')->fetch() == $snapshot);
    $r = req('GET', "/scores/$myId", [], $jar);
    check('Missatge d\'èxit després de desar (flash)', str_contains($r['body'], 'Score desada correctament'));
    check('XSS: el títol es mostra ESCAPAT (cap <script> real)', !str_contains($r['body'], '<script>alert(1)') && str_contains($r['body'], '&lt;script&gt;alert(1)&lt;/script&gt;'));

    $bad = $good; $bad['accuracy'] = '150'; $bad['rank_result'] = 'Z'; $bad['bpm'] = 'abc'; $bad['beatmap_title'] = '';
    $r = req('POST', "/scores/$myId/edit", $bad, $jar);
    check('Dades invàlides -> 422 amb errors per camp', $r['status'] === 422 && str_contains($r['body'], 'La precisió ha d') && str_contains($r['body'], 'rang vàlid') && str_contains($r['body'], 'El BPM'));
    check('Errors: resum enllaçat als camps (href="#accuracy")', str_contains($r['body'], 'href="#accuracy"') && str_contains($r['body'], 'id="resum-errors"'));
    check('Dades invàlides: la BD NO canvia', $pdo->query("SELECT accuracy FROM scores WHERE id = $myId")->fetch()['accuracy'] === '98.75');
    $r = req('POST', "/scores/$myId/edit", array_merge($good, ['star_rating' => '99', 'score' => '-5', 'max_combo' => '1e9']), $jar);
    check('Valors fora de rang/negatius/notació científica rebutjats', $r['status'] === 422);
    unset($good['is_published']);   // una casella desmarcada no s'envia
    $r = req('POST', "/scores/$myId/edit", $good, $jar);
    check('Desmarcar "publicada" -> score oculta del públic', $pdo->query("SELECT is_published FROM scores WHERE id = $myId")->fetch()['is_published'] === 0);
    check('Score no publicada: anònim -> 404', req('GET', "/scores/$myId")['status'] === 404);
    check('Score no publicada: el propietari sí la veu', req('GET', "/scores/$myId", [], $jar)['status'] === 200);
    check('Score no publicada no surt al llistat públic', !str_contains(req('GET', '/scores?sort=recent')['body'], 'Nou títol'));

    // -------------------------------------------------------------------------------------
    section('6. Un altre usuari no pot editar les teves scores');
    $jar2 = newJar();
    $tok2 = csrf('/register', $jar2); $user2 = "test_b$suffix";
    req('POST', '/register', ['csrf_token' => $tok2, 'username' => $user2, 'password' => $goodPass, 'password_confirm' => $goodPass], $jar2);
    $tok2 = csrf('/login', $jar2);
    req('POST', '/login', ['csrf_token' => $tok2, 'username' => $user2, 'password' => $goodPass], $jar2);
    check('Usuari B ha iniciat sessió', req('GET', '/my-scores', [], $jar2)['status'] === 200);
    check('Usuari B: GET edit de score d\'A -> 403', req('GET', "/scores/$myId/edit", [], $jar2)['status'] === 403);
    $tokB = csrf('/my-scores', $jar2);
    $r = req('POST', "/scores/$myId/edit", array_merge($good, ['csrf_token' => $tokB, 'beatmap_title' => 'B ho ha canviat']), $jar2);
    check('Usuari B: POST edit de score d\'A -> 403', $r['status'] === 403);
    check('La score d\'A NO ha canviat', str_contains($pdo->query("SELECT beatmap_title FROM scores WHERE id = $myId")->fetch()['beatmap_title'], 'Nou títol'));
    check('Usuari B no veu la score no publicada d\'A', req('GET', "/scores/$myId", [], $jar2)['status'] === 404);

    section('6a. CRUD: crear una score (CREATE)');
    $countOf = fn(int $uid) => (int) $pdo->query("SELECT COUNT(*) c FROM scores WHERE user_id = $uid")->fetch()['c'];
    $newScore = $evil; unset($newScore['csrf_token']);
    $newScore['beatmap_title'] = 'Creada pel test'; $newScore['rank_result'] = 'A'; $newScore['is_published'] = '1';
    check('Anònim: GET /scores/new redirigeix a /login', ($r = req('GET', '/scores/new'))['status'] === 302 && str_ends_with($r['location'], '/login'));
    $before = (int) $pdo->query('SELECT COUNT(*) c FROM scores')->fetch()['c'];
    check('Anònim: POST /scores/new NO crea res', req('POST', '/scores/new', $newScore)['status'] === 302 && (int) $pdo->query('SELECT COUNT(*) c FROM scores')->fetch()['c'] === $before);
    $r = req('GET', '/scores/new', [], $jar);
    check('Amb sessió: formulari de creació 200 amb token CSRF i etiquetes', $r['status'] === 200 && substr_count($r['body'], '<label for=') >= 14 && preg_match('/csrf_token" value="[a-f0-9]{64}"/', $r['body']) === 1);
    check('El formulari de creació no té camp d\'usuari/id (el propietari és la sessió)', !preg_match('/name="(user_id|id)"/', $r['body']));
    $tokNew = csrf('/scores/new', $jar);
    $mineBefore = $countOf((int) $u['id']);
    check('Crear sense CSRF -> 403 i no es crea res', req('POST', '/scores/new', $newScore, $jar)['status'] === 403 && $countOf((int) $u['id']) === $mineBefore);
    $r = req('POST', '/scores/new', array_merge($newScore, ['csrf_token' => $tokNew, 'accuracy' => '250', 'bpm' => 'x', 'rank_result' => '', 'beatmap_title' => 'Títol que es conserva']), $jar);
    check('Crear amb dades invàlides -> 422 amb errors i sense crear', $r['status'] === 422 && str_contains($r['body'], 'La precisió ha d') && str_contains($r['body'], 'rang vàlid') && $countOf((int) $u['id']) === $mineBefore);
    check('Errors de creació: es conserven els valors no sensibles escrits', str_contains($r['body'], 'value="Títol que es conserva"') && str_contains($r['body'], 'value="x"'));
    $payload = array_merge($newScore, ['csrf_token' => $tokNew, 'user_id' => '1', 'id' => '1', 'created_at' => '2000-01-01 00:00:00', 'beatmap_title' => '<b>Nova</b> & "cita"']);
    $r = req('POST', '/scores/new', $payload, $jar);
    check('Crear amb dades vàlides -> 302 al detall', $r['status'] === 302 && preg_match('#/scores/(\d+)$#', $r['location'], $mm) === 1, $r['location']);
    $createdId = (int) ($mm[1] ?? 0);
    $row = $pdo->query("SELECT * FROM scores WHERE id = $createdId")->fetch();
    check('La score creada pertany a l\'usuari de la SESSIÓ (user_id enviat = 1 s\'ignora)', $row && (int) $row['user_id'] === (int) $u['id']);
    check('id i created_at enviats pel navegador s\'ignoren', $row && $createdId !== 1 && !str_starts_with($row['created_at'], '2000'));
    check('Dades guardades correctament (títol amb HTML tal qual a la BD)', $row && $row['beatmap_title'] === '<b>Nova</b> & "cita"' && $row['rank_result'] === 'A');
    $r = req('GET', "/scores/$createdId", [], $jar);
    check('Missatge d\'èxit en crear (flash)', str_contains($r['body'], 'Score afegida correctament'));
    check('Títol amb HTML sortida ESCAPADA (XSS)', !str_contains($r['body'], '<b>Nova</b>') && str_contains($r['body'], '&lt;b&gt;Nova&lt;/b&gt;'));
    $r = req('GET', '/my-scores', [], $jar);
    check('"Les meves scores" mostra la nova score amb Edita i Elimina', str_contains($r['body'], "/scores/$createdId/edit") && str_contains($r['body'], "/scores/$createdId/delete"));
    check('La nova score apareix al llistat públic', str_contains(req('GET', '/scores?sort=recent')['body'], '&lt;b&gt;Nova&lt;/b&gt;'));
    // Límit per usuari: omplim amb inserts directes fins a MAX i comprovem que el servidor ho bloqueja
    $max = 100; $have = $countOf((int) $u['id']);
    $ins = $pdo->prepare("INSERT INTO scores (user_id, beatmap_title, artist, difficulty_name, mapper, star_rating, bpm, cs, ar, score, accuracy, pp, max_combo, count_300, count_100, count_50, count_miss, rank_result, is_published) VALUES (?, 'omple', 'a', 'a', 'a', 1, 100, 4, 8, 1, 50, 1, 1, 1, 0, 0, 0, 'D', 0)");
    for ($i = $have; $i < $max; $i++) { $ins->execute([$u['id']]); }
    $r = req('POST', '/scores/new', array_merge($newScore, ['csrf_token' => $tokNew]), $jar);
    check('Límit de 100 scores per usuari: el servidor ho bloqueja (422)', $r['status'] === 422 && str_contains($r['body'], 'límit de 100') && $countOf((int) $u['id']) === $max);
    $pdo->prepare("DELETE FROM scores WHERE user_id = ? AND beatmap_title = 'omple'")->execute([$u['id']]);

    section('6c. CRUD: eliminar una score (DELETE) i autorització');
    $target = $createdId;                                   // score de l'usuari A
    $demoBefore = $pdo->query('SELECT COUNT(*) c FROM scores WHERE id <= 12')->fetch()['c'];
    check('Anònim: GET /scores/{id}/delete redirigeix a login', ($r = req('GET', "/scores/$target/delete"))['status'] === 302 && str_ends_with($r['location'], '/login'));
    check('Anònim: POST /scores/{id}/delete NO esborra', req('POST', "/scores/$target/delete", ['csrf_token' => 'x'])['status'] === 302 && $pdo->query("SELECT COUNT(*) c FROM scores WHERE id = $target")->fetch()['c'] == 1);
    $tokB = csrf('/my-scores', $jar2);
    check('USUARI B: GET confirmació d\'eliminar score d\'A -> 403', req('GET', "/scores/$target/delete", [], $jar2)['status'] === 403);
    check('USUARI B: POST eliminar score d\'A (amb CSRF vàlid propi) -> 403', req('POST', "/scores/$target/delete", ['csrf_token' => $tokB], $jar2)['status'] === 403);
    check('La score d\'A segueix existint després dels intents d\'B', $pdo->query("SELECT COUNT(*) c FROM scores WHERE id = $target")->fetch()['c'] == 1);
    check('USUARI A: no pot eliminar una demo aliena (id 1) -> 403 i segueix existint', req('POST', '/scores/1/delete', ['csrf_token' => csrf('/my-scores', $jar)], $jar)['status'] === 403 && $pdo->query('SELECT COUNT(*) c FROM scores WHERE id = 1')->fetch()['c'] == 1);
    check('Eliminar una score inexistent -> 404', req('GET', '/scores/999999/delete', [], $jar)['status'] === 404);
    $r = req('GET', "/scores/$target/delete", [], $jar);
    check('Propietari: pàgina de CONFIRMACIÓ 200 amb avís i formulari POST', $r['status'] === 200 && str_contains($r['body'], 'Vols eliminar aquesta score?') && str_contains($r['body'], 'no es pot desfer') && str_contains($r['body'], 'method="post"'));
    check('GET a la confirmació NO esborra res (accions destructives només per POST)', $pdo->query("SELECT COUNT(*) c FROM scores WHERE id = $target")->fetch()['c'] == 1);
    check('Altres usuaris no veuen "Elimina" a la score d\'A', !str_contains(req('GET', "/scores/$target", [], $jar2)['body'], '/delete') && !str_contains(req('GET', '/scores/1', [], $jar)['body'], '/scores/1/delete'));
    $tokDel = csrf("/scores/$target/delete", $jar);
    check('Eliminar sense CSRF -> 403 i la score segueix', req('POST', "/scores/$target/delete", [], $jar)['status'] === 403 && $pdo->query("SELECT COUNT(*) c FROM scores WHERE id = $target")->fetch()['c'] == 1);
    check('Eliminar amb CSRF invàlid -> 403', req('POST', "/scores/$target/delete", ['csrf_token' => 'invalid'], $jar)['status'] === 403);
    $r = req('POST', "/scores/$target/delete", ['csrf_token' => $tokDel, 'user_id' => '1', 'id' => '1'], $jar);
    check('Eliminar correctament (id/user_id extra del formulari ignorats) -> 302 a /my-scores', $r['status'] === 302 && str_ends_with($r['location'], '/my-scores'));
    check('La score ha desaparegut de la BD', $pdo->query("SELECT COUNT(*) c FROM scores WHERE id = $target")->fetch()['c'] == 0);
    check('La score amb id 1 NO s\'ha tocat (no s\'ha esborrat l\'id manipulat)', $pdo->query('SELECT COUNT(*) c FROM scores WHERE id = 1')->fetch()['c'] == 1);
    check('Missatge d\'èxit en eliminar (flash)', str_contains(req('GET', '/my-scores', [], $jar)['body'], 'Score eliminada'));
    check('La score eliminada dóna 404', req('GET', "/scores/$target", [], $jar)['status'] === 404 && req('GET', "/scores/$target/edit", [], $jar)['status'] === 404);
    check('Les 12 scores de demostració segueixen intactes', $pdo->query('SELECT COUNT(*) c FROM scores WHERE id <= 12')->fetch()['c'] == $demoBefore && (int) $demoBefore === 12);
    check('Eliminar un usuari esborra en cascada les seves scores (FK, comprovat a la BD)', (function () use ($pdo) {
        $pdo->exec("INSERT INTO users (username, password_hash) VALUES ('test_cascade_tmp', 'x')");
        $uid = (int) $pdo->lastInsertId();
        $pdo->exec("INSERT INTO scores (user_id, beatmap_title, artist, difficulty_name, score, accuracy, max_combo, rank_result) VALUES ($uid, 't', 't', 't', 1, 1, 1, 'D')");
        $pdo->exec("DELETE FROM users WHERE id = $uid");
        return (int) $pdo->query("SELECT COUNT(*) c FROM scores WHERE user_id = $uid")->fetch()['c'] === 0;
    })());

    section('6d. Temes clar i fosc, layout');
    $home = req('GET', '/')['body'];
    check('Tema: script al <head> que aplica localStorage abans de pintar', preg_match('/<head>.*localStorage\.getItem\(\'theme\'\).*data-theme.*<\/head>/s', $home) === 1);
    check('Tema: meta color-scheme "dark light" i theme-color per esquema', str_contains($home, 'content="dark light"') && substr_count($home, 'name="theme-color"') === 2);
    check('Tema: botó conmutador amb aria-pressed (ocult sense JS) a la capçalera', preg_match('/<button[^>]*data-theme-toggle[^>]*aria-pressed="false"[^>]*hidden/', $home) === 1 && str_contains($home, 'Mode fosc'));
    $css = file_get_contents($root . '/public/css/base.css');
    check('CSS: tokens per a data-theme="dark", data-theme="light" i prefers-color-scheme: light', str_contains($css, ':root[data-theme="dark"]') && str_contains($css, ':root[data-theme="light"]') && str_contains($css, '@media (prefers-color-scheme: light)'));
    check('JS: desa a localStorage amb try/catch i respecta prefers-color-scheme', ($js = file_get_contents($root . '/public/js/app.js')) && str_contains($js, "localStorage.setItem('theme'") && str_contains($js, 'prefers-color-scheme: dark') && str_contains($js, 'catch'));
    $out = []; exec('"' . PHP_BINARY . '" "' . $root . '/tools/contrast.php" 2>&1', $out, $codi);
    check('Contrast WCAG AA en els DOS temes (tools/contrast.php) i blocs clars idèntics', $codi === 0, implode(' | ', array_slice(array_filter($out, fn($l) => str_contains($l, 'FAIL') || str_contains($l, 'ERROR')), 0, 3)));
    $hardcoded = 0;
    foreach (['components.css', 'pages.css'] as $cssFile) { $hardcoded += preg_match_all('/(?<![\w-])#[0-9a-fA-F]{3,6}\b/', preg_replace('/\/\*.*?\*\//s', '', file_get_contents($root . '/public/css/' . $cssFile))); }
    check('Només els 6 colors d\'avatar són fixos als CSS de components/pàgines (la resta, via tokens de tema)', $hardcoded === 6, "$hardcoded colors fixos");
    $page = file_get_contents($root . '/public/css/base.css');
    check('Layout: .page centrat (margin-inline:auto) amb amplada màxima de 56rem', preg_match('/\.page\s*\{[^}]*min\(100% - 2rem, 56rem\)[^}]*margin-inline:\s*auto/', $page) === 1);
    foreach (['/scores', '/scores/1'] as $p) { check("Layout $p: contingut dins de .page", str_contains(req('GET', $p)['body'], 'class="page"')); }

    section('6b. Capçaleres de seguretat');
    $r = req('GET', '/my-scores', [], $jar);
    check('Capçaleres: nosniff, X-Frame-Options, Referrer-Policy', stripos($r['headers'], 'X-Content-Type-Options: nosniff') !== false && stripos($r['headers'], 'X-Frame-Options: DENY') !== false && stripos($r['headers'], 'Referrer-Policy') !== false);
    check('Amb sessió: Cache-Control no-store', stripos($r['headers'], 'Cache-Control: no-store') !== false);

    // -------------------------------------------------------------------------------------
    section('7. Tancament de sessió');
    $r = req('POST', '/logout', [], $jar);
    check('Logout sense CSRF -> 403 (la sessió continua)', $r['status'] === 403 && req('GET', '/my-scores', [], $jar)['status'] === 200);
    check('Logout per GET no existeix (404)', req('GET', '/logout', [], $jar)['status'] === 404);
    $tok = csrf('/my-scores', $jar);
    $oldSid = sessionId($jar);
    $r = req('POST', '/logout', ['csrf_token' => $tok], $jar);
    check('Logout correcte -> 302 a /', $r['status'] === 302);
    check('Logout esborra la cookie (Set-Cookie amb caducitat passada)', preg_match('/OSUSCORESID=deleted|expires=Thu, 01 Jan 1970|Max-Age=0|expires=.*1970/i', implode('|', $r['cookies'])) === 1, implode('|', $r['cookies']));
    check('Després del logout: /my-scores redirigeix a /login', req('GET', '/my-scores', [], $jar)['status'] === 302);
    // Reutilitzem l'ID de sessió ANTIC amb una cookie manual: el servidor no l'ha de reconèixer
    $ch = curl_init($base . '/my-scores');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIE => "OSUSCORESID=$oldSid"]);
    curl_exec($ch);
    check('Després del logout: l\'ID de sessió antic ja no dóna accés (302)', curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 302);
    curl_close($ch);

    // -------------------------------------------------------------------------------------
    section('8. Caducitat de sessió per inactivitat (45 min)');
    $savePath = ini_get('session.save_path') ?: 'C:/xampp/tmp';
    if (str_contains($savePath, ';')) { $savePath = substr($savePath, strrpos($savePath, ';') + 1); }
    $jar3 = newJar();
    $tok = csrf('/login', $jar3);
    req('POST', '/login', ['csrf_token' => $tok, 'username' => $user, 'password' => $goodPass], $jar3);
    $sid = sessionId($jar3);
    $file = rtrim($savePath, '/\\') . "/sess_$sid";
    check('Fitxer de sessió trobat al servidor (dades NO a la cookie)', is_file($file), $file);
    if (is_file($file)) {
        $setActivity = function (int $ageSeconds) use ($file) {
            $c = file_get_contents($file);
            file_put_contents($file, preg_replace('/last_activity\|i:\d+;/', 'last_activity|i:' . (time() - $ageSeconds) . ';', $c));
        };
        $setActivity(44 * 60);
        check('44 minuts d\'inactivitat: la sessió ENCARA és vàlida', req('GET', '/my-scores', [], $jar3)['status'] === 200);
        check('L\'activitat s\'ha renovat (expiració lliscant)', (time() - (int) (preg_match('/last_activity\|i:(\d+);/', file_get_contents($file), $m) ? $m[1] : 0)) < 60);
        $setActivity(46 * 60);
        $r = req('GET', '/my-scores', [], $jar3);
        check('46 minuts d\'inactivitat: sessió caducada -> redirigeix a /login', $r['status'] === 302 && str_ends_with($r['location'], '/login'));
        $r = req('GET', '/login', [], $jar3);
        check('Es mostra l\'avís "La sessió ha caducat per inactivitat"', str_contains($r['body'], 'caducat per inactivitat'));
        check('Després de caducar, ja no hi ha accés a /my-scores', req('GET', '/my-scores', [], $jar3)['status'] === 302);
    }

    // -------------------------------------------------------------------------------------
    section('9. Accessibilitat estructural (automàtica, sobre l\'HTML servit)');
    foreach (['/', '/scores', '/scores/1', '/login', '/register', '/guia-estils', '/no-existeix'] as $p) {
        $html = req('GET', $p)['body'];
        $dom = new DOMDocument(); @$dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
        $x = new DOMXPath($dom);
        $ids = []; $dup = 0;
        foreach ($x->query('//*[@id]') as $n) { $i = $n->getAttribute('id'); if (isset($ids[$i])) { $dup++; } $ids[$i] = true; }
        $h1 = $x->query('//h1')->length;
        $noLabel = 0;
        foreach ($x->query('//input[not(@type="hidden")] | //select | //textarea') as $n) {
            $id = $n->getAttribute('id');
            if ($id === '' || $x->query("//label[@for='$id']")->length === 0) { $noLabel++; }
        }
        $imgNoAlt = $x->query('//img[not(@alt)]')->length;
        $btnEmpty = 0;
        foreach ($x->query('//a | //button') as $n) { if (trim($n->textContent) === '' && !$n->getAttribute('aria-label')) { $btnEmpty++; } }
        check("$p: lang=ca, 1 <h1>, <title>, main, skip-link", $dom->documentElement->getAttribute('lang') === 'ca' && $h1 === 1 && $x->query('//title')->length === 1 && $x->query('//main[@id="contingut"]')->length === 1 && $x->query('//a[@href="#contingut"]')->length === 1, "h1=$h1");
        check("$p: sense ids duplicats ni camps sense label ni enllaços/botons buits", $dup === 0 && $noLabel === 0 && $btnEmpty === 0 && $imgNoAlt === 0, "dup=$dup sinLabel=$noLabel buits=$btnEmpty");
        $levels = []; foreach ($x->query('//h1|//h2|//h3|//h4') as $n) { $levels[] = (int) substr($n->nodeName, 1); }
        $skip = false; for ($i = 1; $i < count($levels); $i++) { if ($levels[$i] - $levels[$i - 1] > 1) { $skip = true; } }
        check("$p: jerarquia d'encapçalaments sense salts", !$skip, implode(',', $levels));
    }
} finally {
    // Neteja: només dades de prova (les scores es borren en cascada)
    $pdo->prepare("DELETE FROM users WHERE username LIKE 'test\\_%'")->execute();
    foreach (glob(sys_get_temp_dir() . '/jar*') as $j) { @unlink($j); }
}

section('10. Registre d\'errors de PHP');
$logAfter = is_file($errorLog) ? filesize($errorLog) : 0;
check('Cap error/avís/avís obsolet nou a storage/logs/error.log durant els tests', $logAfter === $logBefore, $logAfter > $logBefore ? trim(substr(file_get_contents($errorLog), $logBefore, 300)) : '');
check('Usuaris de prova esborrats', (int) $pdo->query("SELECT COUNT(*) c FROM users WHERE username LIKE 'test\\_%'")->fetch()['c'] === 0);
check('Scores de demostració intactes (12) i usuaris demo (4)', (int) $pdo->query('SELECT COUNT(*) c FROM scores WHERE id <= 12')->fetch()['c'] === 12 && (int) $pdo->query("SELECT COUNT(*) c FROM users WHERE username LIKE 'demo\\_%'")->fetch()['c'] === 4);

echo "\n==============================\n";
echo "Resultat: $pass correctes, $fail fallades\n";
if ($fail) { echo "Fallades:\n - " . implode("\n - ", $failures) . "\n"; }
exit($fail ? 1 : 0);
