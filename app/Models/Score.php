<?php
/**
 * Autor: Pol Corsin — PT04 osu! Scores
 * Model Score: accés a la taula `scores`. Només consultes preparades amb PDO.
 */
class Score
{
    /** Rangs vàlids (coincideixen amb l'ENUM de la BD). */
    public const RANKS = ['SS', 'S', 'A', 'B', 'C', 'D'];

    /** Scores per pàgina al llistat. */
    public const PER_PAGE = 6;

    /** Màxim de scores per usuari (evita que algú ompli la base de dades). */
    public const MAX_PER_USER = 100;

    /** Ordenacions permeses: clau de la URL => clàusula ORDER BY (llista blanca; mai s'insereix text de l'usuari). */
    public const SORTS = [
        'recent'   => ['label' => 'Més recents', 'sql' => 's.created_at DESC, s.id DESC'],
        'pp'       => ['label' => 'PP',          'sql' => 's.pp DESC, s.id DESC'],
        'score'    => ['label' => 'Score',       'sql' => 's.score DESC, s.id DESC'],
        'accuracy' => ['label' => 'Precisió',    'sql' => 's.accuracy DESC, s.id DESC'],
    ];

    /**
     * Columnes de les consultes de lectura. `player_rank` és la posició del jugador segons
     * la suma de PP de les seves scores publicades (1 = més PP).
     */
    private const SELECT = 's.id, s.user_id, s.beatmap_title, s.artist, s.difficulty_name, s.mapper,
        s.star_rating, s.bpm, s.cs, s.ar, s.score, s.accuracy, s.pp, s.max_combo,
        s.count_300, s.count_100, s.count_50, s.count_miss, s.rank_result, s.is_published,
        s.created_at, s.updated_at, u.username,
        (SELECT COUNT(*) + 1 FROM (SELECT user_id, SUM(pp) AS total FROM scores WHERE is_published = 1 GROUP BY user_id) t
          WHERE t.total > (SELECT COALESCE(SUM(pp), 0) FROM scores WHERE is_published = 1 AND user_id = s.user_id)) AS player_rank';

    private const FROM = ' FROM scores s INNER JOIN users u ON u.id = s.user_id ';

    private static function rankFilter(?string $rank): array
    {
        if ($rank !== null && in_array($rank, self::RANKS, true)) {
            return [' AND s.rank_result = :rank', [':rank' => $rank]];
        }
        return ['', []];
    }

    /** Nombre de scores publicades (amb filtre de rang opcional). */
    public static function countPublished(?string $rank = null): int
    {
        [$where, $params] = self::rankFilter($rank);
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM scores s WHERE s.is_published = 1' . $where);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** Una pàgina de scores publicades, amb ordenació i filtre de rang (valors desconeguts s'ignoren). */
    public static function listPublished(string $sort = 'recent', ?string $rank = null, int $page = 1): array
    {
        $orderBy = (self::SORTS[$sort] ?? self::SORTS['recent'])['sql'];
        [$where, $params] = self::rankFilter($rank);
        $sql = 'SELECT ' . self::SELECT . self::FROM . 'WHERE s.is_published = 1' . $where
             . ' ORDER BY ' . $orderBy . ' LIMIT :limit OFFSET :offset';

        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', self::PER_PAGE, PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $page - 1) * self::PER_PAGE, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Les últimes N scores publicades (per a la pàgina d'inici). */
    public static function latestPublished(int $limit): array
    {
        $sql = 'SELECT ' . self::SELECT . self::FROM . 'WHERE s.is_published = 1 ORDER BY s.created_at DESC, s.id DESC LIMIT :limit';
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** La score publicada amb més PP (peça destacada de l'inici). */
    public static function topPublished(): ?array
    {
        $sql = 'SELECT ' . self::SELECT . self::FROM . 'WHERE s.is_published = 1 ORDER BY s.pp DESC, s.id DESC LIMIT 1';
        $row = Database::connection()->query($sql)->fetch();
        return $row === false ? null : $row;
    }

    /** Una score publicada pel seu id, o null si no existeix / no és pública. */
    public static function findPublished(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT ' . self::SELECT . self::FROM . 'WHERE s.id = :id AND s.is_published = 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Una score per id, publicada o no (per a l'edició; el controlador comprova la propietat). */
    public static function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT ' . self::SELECT . self::FROM . 'WHERE s.id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Totes les scores d'un usuari (publicades o no), de la més recent a la més antiga. */
    public static function listByUser(int $userId): array
    {
        $stmt = Database::connection()->prepare('SELECT ' . self::SELECT . self::FROM . 'WHERE s.user_id = :uid ORDER BY s.created_at DESC, s.id DESC');
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    /** True si la score existeix i pertany a l'usuari. */
    public static function isOwnedBy(int $scoreId, int $userId): bool
    {
        $stmt = Database::connection()->prepare('SELECT 1 FROM scores WHERE id = :id AND user_id = :uid LIMIT 1');
        $stmt->execute([':id' => $scoreId, ':uid' => $userId]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Actualitza una score. La clàusula `user_id = :uid` és una SEGONA barrera de seguretat:
     * encara que el controlador fallés, mai es modificaria la score d'un altre usuari.
     * No es poden canviar id, user_id ni created_at. Retorna les files afectades.
     */
    public static function update(int $id, int $userId, array $d): int
    {
        $sql = 'UPDATE scores SET beatmap_title = :beatmap_title, artist = :artist, difficulty_name = :difficulty_name,
                    mapper = :mapper, star_rating = :star_rating, bpm = :bpm, cs = :cs, ar = :ar,
                    score = :score, accuracy = :accuracy, pp = :pp, max_combo = :max_combo,
                    count_300 = :count_300, count_100 = :count_100, count_50 = :count_50, count_miss = :count_miss,
                    rank_result = :rank_result, is_published = :is_published
                WHERE id = :id AND user_id = :uid';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            ':beatmap_title' => $d['beatmap_title'], ':artist' => $d['artist'], ':difficulty_name' => $d['difficulty_name'],
            ':mapper' => $d['mapper'], ':star_rating' => $d['star_rating'], ':bpm' => $d['bpm'], ':cs' => $d['cs'], ':ar' => $d['ar'],
            ':score' => $d['score'], ':accuracy' => $d['accuracy'], ':pp' => $d['pp'], ':max_combo' => $d['max_combo'],
            ':count_300' => $d['count_300'], ':count_100' => $d['count_100'], ':count_50' => $d['count_50'], ':count_miss' => $d['count_miss'],
            ':rank_result' => $d['rank_result'], ':is_published' => $d['is_published'],
            ':id' => $id, ':uid' => $userId,
        ]);
        return $stmt->rowCount();
    }

    /** Nombre de scores d'un usuari (per limitar-ne la creació). */
    public static function countByUser(int $userId): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM scores WHERE user_id = :uid');
        $stmt->execute([':uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Crea una score. El propietari ($userId) arriba SEMPRE de la sessió, mai del formulari.
     * Retorna l'id nou.
     */
    public static function create(int $userId, array $d): int
    {
        $sql = 'INSERT INTO scores (user_id, beatmap_title, artist, difficulty_name, mapper, star_rating, bpm, cs, ar,
                    score, accuracy, pp, max_combo, count_300, count_100, count_50, count_miss, rank_result, is_published)
                VALUES (:uid, :beatmap_title, :artist, :difficulty_name, :mapper, :star_rating, :bpm, :cs, :ar,
                    :score, :accuracy, :pp, :max_combo, :count_300, :count_100, :count_50, :count_miss, :rank_result, :is_published)';
        $pdo = Database::connection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':uid' => $userId,
            ':beatmap_title' => $d['beatmap_title'], ':artist' => $d['artist'], ':difficulty_name' => $d['difficulty_name'],
            ':mapper' => $d['mapper'], ':star_rating' => $d['star_rating'], ':bpm' => $d['bpm'], ':cs' => $d['cs'], ':ar' => $d['ar'],
            ':score' => $d['score'], ':accuracy' => $d['accuracy'], ':pp' => $d['pp'], ':max_combo' => $d['max_combo'],
            ':count_300' => $d['count_300'], ':count_100' => $d['count_100'], ':count_50' => $d['count_50'], ':count_miss' => $d['count_miss'],
            ':rank_result' => $d['rank_result'], ':is_published' => $d['is_published'],
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Elimina una score. Igual que update(): la condició `user_id = :uid` impedeix esborrar
     * la score d'un altre usuari encara que el controlador fallés. Retorna les files esborrades.
     */
    public static function delete(int $id, int $userId): int
    {
        $stmt = Database::connection()->prepare('DELETE FROM scores WHERE id = :id AND user_id = :uid');
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        return $stmt->rowCount();
    }
}
