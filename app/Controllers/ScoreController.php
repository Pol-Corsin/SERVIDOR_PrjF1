<?php
/**
 * Autor: Pol Corsin — PT04 osu! Scores
 * Controlador de scores: llistat públic (ordre, filtre, paginació), detall i CRUD de les scores PRÒPIES
 * (crear, veure, editar i eliminar), amb imatge de fons opcional i URL oficial opcional.
 *
 * Regles de seguretat de totes les accions d'usuari:
 *  - Cal sessió iniciada.
 *  - Crear, editar i eliminar només es fan per POST amb token CSRF.
 *  - El propietari surt SEMPRE de la sessió; mai d'un formulari ni de la URL.
 *  - Editar i eliminar comproven al servidor que la score és de l'usuari (403 si no).
 *  - La ruta de la imatge NO la tria l'usuari: la genera el servidor; mai es llegeix d'un formulari.
 *    Només es pot substituir o treure la imatge d'una score pròpia.
 *  - Els errors de la base de dades es registren al log; l'usuari només veu un missatge genèric.
 */
class ScoreController extends Controller
{
    private const DB_ERROR = 'No s\'ha pogut completar l\'operació per un error del servidor. Torna-ho a provar.';

    /** Valor d'un paràmetre de la URL, sempre com a text (ignora arrays com ?sort[]=x). */
    private function query(string $key): string
    {
        $v = $_GET[$key] ?? '';
        return is_string($v) ? $v : '';
    }

    // ------------------------------------------------------------ Part pública

    public function index(): void
    {
        // Tots els valors de la URL es validen contra llistes blanques o rangs
        $sort = isset(Score::SORTS[$this->query('sort')]) ? $this->query('sort') : 'recent';
        $rank = in_array($this->query('rank'), Score::RANKS, true) ? $this->query('rank') : null;
        // Entero estricte: "2.5", "abc" o "6;DROP" no són vàlids (no es converteixen a 2, 0 o 6)
        $perPage = filter_var($this->query('per_page'), FILTER_VALIDATE_INT);
        $perPage = in_array($perPage, Score::PAGE_SIZES, true) ? $perPage : Score::DEFAULT_PER_PAGE;

        $total = Score::countPublished($rank);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = filter_var($this->query('page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $page = min($page, $pages); // una pàgina inexistent es corregeix a l'última

        $this->view('scores/index', [
            'title'   => 'Totes les scores',
            'scores'  => Score::listPublished($sort, $rank, $page, $perPage),
            'sort'    => $sort,
            'rank'    => $rank,
            'page'    => $page,
            'pages'   => $pages,
            'perPage' => $perPage,
            'total'   => $total,
        ]);
    }

    public function show(int $id): void
    {
        $score = Score::findPublished($id);
        // Una score no publicada només la pot veure el seu propietari
        if ($score === null && isLoggedIn() && userOwnsScore($id, currentUserId())) {
            $score = Score::findById($id);
        }
        if ($score === null) {
            $this->abort(404);
            return;
        }
        $this->view('scores/show', [
            'title'   => $score['beatmap_title'],
            'score'   => $score,
            'isOwner' => isLoggedIn() && (int) $score['user_id'] === currentUserId(),
        ]);
    }

    // --------------------------------------------------- Les meves scores (CRUD)

    /** READ: les meves scores. */
    public function mine(): void
    {
        $this->requireLogin();
        $this->view('scores/mine', [
            'title'  => 'Les meves scores',
            'scores' => Score::listByUser(currentUserId()),
        ]);
    }

    /** CREATE (formulari). */
    public function create(): void
    {
        $this->requireLogin();
        $this->view('scores/create', $this->formData('Afegeix una score', ['is_published' => 1]));
    }

    /** CREATE (desar). El propietari és l'usuari de la sessió. */
    public function store(): void
    {
        $this->requireLogin();
        if ($tooBig = $this->requestTooLarge()) {
            $this->view('scores/create', $this->formData('Afegeix una score', [], [], $tooBig), 413);
            return;
        }
        $this->requireValidCsrf();

        [$clean, $errors] = validateScoreInput($_POST);
        $file = $_FILES['image'] ?? null;
        [$imageError] = validateScoreImage($file);
        if ($imageError !== null) { $errors['image'] = $imageError; }

        $formError = null;
        $status = 422;
        if (!$errors && Score::countByUser(currentUserId()) >= Score::MAX_PER_USER) {
            $formError = 'Has arribat al límit de ' . Score::MAX_PER_USER . ' scores. Elimina\'n alguna per afegir-ne de noves.';
        }

        if (!$errors && $formError === null) {
            $storedPath = null;
            try {
                // La imatge es desa DESPRÉS de validar-ho tot; si la BD falla, s'esborra de nou
                [$storedPath, $storeError] = storeScoreImage($file);
                if ($storeError !== null) {
                    $errors['image'] = $storeError;
                } else {
                    $clean['image_path'] = $storedPath;
                    $id = Score::create(currentUserId(), $clean);
                    flash('success', 'Score afegida correctament.');
                    $this->redirect('/scores/' . $id);
                }
            } catch (PDOException $e) {
                deleteScoreImage($storedPath);
                error_log('Crear score: ' . $e->getMessage());
                $formError = self::DB_ERROR;
                $status = 500;
            }
        }

        $this->view('scores/create', $this->formData('Afegeix una score', $_POST, $errors, $formError, hasUploadedFile($file)), $status);
    }

    /** UPDATE (formulari). */
    public function edit(int $id): void
    {
        $score = $this->loadOwnScoreOrFail($id);
        $this->view('scores/edit', $this->formData('Edita la score', $score) + ['score' => $score]);
    }

    /** UPDATE (desar). Pot conservar, substituir o treure la imatge. */
    public function update(int $id): void
    {
        // Ordre: sessió -> existeix -> és MEVA -> mida -> token CSRF -> validació -> desar
        $score = $this->loadOwnScoreOrFail($id);
        if ($tooBig = $this->requestTooLarge()) {
            $this->view('scores/edit', $this->formData('Edita la score', $score, [], $tooBig) + ['score' => $score], 413);
            return;
        }
        $this->requireValidCsrf();

        [$clean, $errors] = validateScoreInput($_POST);
        $file = $_FILES['image'] ?? null;
        [$imageError] = validateScoreImage($file);
        if ($imageError !== null) { $errors['image'] = $imageError; }

        $formError = null;
        $status = 422;

        if (!$errors) {
            // La imatge actual és la de la BD (mai un valor del formulari)
            $oldPath = isSafeScoreImagePath($score['image_path']) ? $score['image_path'] : null;
            $newPath = $oldPath;
            $storedPath = null;
            try {
                if (hasUploadedFile($file)) {                       // substituir
                    [$storedPath, $storeError] = storeScoreImage($file);
                    if ($storeError !== null) {
                        $errors['image'] = $storeError;
                    } else {
                        $newPath = $storedPath;
                    }
                } elseif (!empty($_POST['remove_image'])) {         // treure
                    $newPath = null;
                }

                if (!$errors) {
                    $clean['image_path'] = $newPath;
                    // L'usuari propietari surt de la SESSIÓ, mai del formulari
                    Score::update($id, currentUserId(), $clean);
                    if ($oldPath !== null && $oldPath !== $newPath) {
                        deleteScoreImage($oldPath);                 // només DESPRÉS de desar bé
                    }
                    flash('success', 'Score desada correctament.');
                    $this->redirect('/scores/' . $id);
                }
            } catch (PDOException $e) {
                deleteScoreImage($storedPath);                      // no deixem la imatge nova orfe
                error_log('Editar score ' . $id . ': ' . $e->getMessage());
                $formError = self::DB_ERROR;
                $status = 500;
            }
        }

        $this->view('scores/edit', $this->formData('Edita la score', $_POST, $errors, $formError, hasUploadedFile($file)) + ['score' => $score], $status);
    }

    /**
     * DELETE (pas 1): pàgina de CONFIRMACIÓ. No esborra res: només mostra la score i demana confirmar.
     * (Funciona sense JavaScript i és accessible amb teclat i lector de pantalla.)
     */
    public function confirmDelete(int $id): void
    {
        $score = $this->loadOwnScoreOrFail($id);
        $this->view('scores/delete', [
            'title' => 'Elimina la score',
            'score' => $score,
        ]);
    }

    /** DELETE (pas 2): només per POST + CSRF + propietat. Esborra també la imatge associada. */
    public function destroy(int $id): void
    {
        $score = $this->loadOwnScoreOrFail($id);
        $this->requireValidCsrf();

        try {
            // La consulta repeteix `AND user_id = :uid`: segona barrera de seguretat
            $deleted = Score::delete($id, currentUserId());
            if ($deleted > 0) {
                deleteScoreImage($score['image_path']);   // només si la fila s'ha esborrat de debò
            }
            flash('success', 'Score eliminada.');
        } catch (PDOException $e) {
            error_log('Eliminar score ' . $id . ': ' . $e->getMessage());
            flash('error', self::DB_ERROR);
        }
        $this->redirect('/my-scores');
    }

    // ------------------------------------------------------------------ Ajudes

    /** Dades per a les vistes del formulari (crear/editar). */
    private function formData(string $title, array $values, array $errors = [], ?string $formError = null, bool $imageLost = false): array
    {
        return [
            'title'     => $title,
            'values'    => $values,
            'errors'    => $errors,
            'formError' => $formError,
            // Un navegador no pot tornar a omplir un camp de fitxer: si el formulari torna amb errors, ho avisem
            'imageNote' => ($imageLost && $errors && empty($errors['image']))
                ? 'La imatge no s\'ha desat perquè el formulari té errors. Torna a seleccionar-la abans d\'enviar.'
                : null,
        ];
    }

    /** Si la petició supera post_max_size, PHP buida $_POST i $_FILES: ho detectem abans de fallar amb un 403 confús. */
    private function requestTooLarge(): ?string
    {
        return postExceededLimit()
            ? 'La mida total de l\'enviament supera el límit del servidor. Prova amb una imatge més petita (màxim 5 MB).'
            : null;
    }

    /**
     * Carrega una score i comprova al servidor que pertany a l'usuari de la sessió.
     * - Sense sessió -> redirigeix a login.
     * - No existeix -> 404.
     * - És d'un altre usuari -> 403 (canviar l'id a la URL no serveix de res).
     */
    private function loadOwnScoreOrFail(int $id): array
    {
        $this->requireLogin();
        $score = Score::findById($id);
        if ($score === null) {
            $this->abort(404);
            exit;
        }
        if (!userOwnsScore($id, currentUserId())) {
            $this->abort(403, 'Només pots gestionar les teves pròpies scores.');
            exit;
        }
        return $score;
    }
}
