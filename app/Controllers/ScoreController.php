<?php
/**
 * Autor: Pol Corsin — PT04 osu! Scores
 * Controlador de scores: llistat públic (ordre, filtre i paginació), detall i CRUD de les scores PRÒPIES
 * (crear, veure, editar i eliminar).
 *
 * Regles de seguretat de totes les accions d'usuari:
 *  - Cal sessió iniciada.
 *  - Crear, editar i eliminar només es fan per POST amb token CSRF.
 *  - El propietari surt SEMPRE de la sessió; mai d'un formulari ni de la URL.
 *  - Editar i eliminar comproven al servidor que la score és de l'usuari (403 si no).
 *  - Els errors de la base de dades es registren al log; l'usuari només veu un missatge genèric.
 */
class ScoreController extends Controller
{
    private const DB_ERROR = 'No s\'ha pogut completar l\'operació per un error del servidor. Torna-ho a provar.';

    // ------------------------------------------------------------ Part pública

    public function index(): void
    {
        // Els valors de la URL es validen contra llistes blanques
        $sort = isset(Score::SORTS[$_GET['sort'] ?? '']) ? $_GET['sort'] : 'recent';
        $rank = in_array($_GET['rank'] ?? '', Score::RANKS, true) ? $_GET['rank'] : null;

        $total = Score::countPublished($rank);
        $pages = max(1, (int) ceil($total / Score::PER_PAGE));
        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $page = min($page, $pages); // una pàgina inexistent es corregeix a l'última

        $this->view('scores/index', [
            'title'  => 'Totes les scores',
            'scores' => Score::listPublished($sort, $rank, $page),
            'sort'   => $sort,
            'rank'   => $rank,
            'page'   => $page,
            'pages'  => $pages,
            'total'  => $total,
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
        $this->view('scores/create', [
            'title'     => 'Afegeix una score',
            'values'    => ['is_published' => 1],
            'errors'    => [],
            'formError' => null,
        ]);
    }

    /** CREATE (desar). El propietari és l'usuari de la sessió. */
    public function store(): void
    {
        $this->requireLogin();
        $this->requireValidCsrf();

        [$clean, $errors] = validateScoreInput($_POST);
        $formError = null;
        $status = 422;
        if (!$errors && Score::countByUser(currentUserId()) >= Score::MAX_PER_USER) {
            $formError = 'Has arribat al límit de ' . Score::MAX_PER_USER . ' scores. Elimina\'n alguna per afegir-ne de noves.';
        }

        if (!$errors && $formError === null) {
            try {
                $id = Score::create(currentUserId(), $clean);
                flash('success', 'Score afegida correctament.');
                $this->redirect('/scores/' . $id);
            } catch (PDOException $e) {
                error_log('Crear score: ' . $e->getMessage());
                $formError = self::DB_ERROR;
                $status = 500;
            }
        }

        $this->view('scores/create', [
            'title'     => 'Afegeix una score',
            'values'    => $_POST,
            'errors'    => $errors,
            'formError' => $formError,
        ], $status);
    }

    /** UPDATE (formulari). */
    public function edit(int $id): void
    {
        $score = $this->loadOwnScoreOrFail($id);
        $this->view('scores/edit', [
            'title'     => 'Edita la score',
            'score'     => $score,
            'values'    => $score,
            'errors'    => [],
            'formError' => null,
        ]);
    }

    /** UPDATE (desar). */
    public function update(int $id): void
    {
        // Ordre: sessió -> existeix -> és MEVA -> token CSRF -> validació -> desar
        $score = $this->loadOwnScoreOrFail($id);
        $this->requireValidCsrf();

        [$clean, $errors] = validateScoreInput($_POST);
        $formError = null;
        $status = 422;

        if (!$errors) {
            try {
                // L'usuari propietari surt de la SESSIÓ, mai del formulari
                Score::update($id, currentUserId(), $clean);
                flash('success', 'Score desada correctament.');
                $this->redirect('/scores/' . $id);
            } catch (PDOException $e) {
                error_log('Editar score ' . $id . ': ' . $e->getMessage());
                $formError = self::DB_ERROR;
                $status = 500;
            }
        }

        $this->view('scores/edit', [
            'title'     => 'Edita la score',
            'score'     => $score,
            'values'    => $_POST,   // es tornen a mostrar els valors escrits (cap és sensible)
            'errors'    => $errors,
            'formError' => $formError,
        ], $status);
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

    /** DELETE (pas 2): només per POST + CSRF + propietat. */
    public function destroy(int $id): void
    {
        $this->loadOwnScoreOrFail($id);
        $this->requireValidCsrf();

        try {
            // La consulta repeteix `AND user_id = :uid`: segona barrera de seguretat
            Score::delete($id, currentUserId());
            flash('success', 'Score eliminada.');
        } catch (PDOException $e) {
            error_log('Eliminar score ' . $id . ': ' . $e->getMessage());
            flash('error', self::DB_ERROR);
        }
        $this->redirect('/my-scores');
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
