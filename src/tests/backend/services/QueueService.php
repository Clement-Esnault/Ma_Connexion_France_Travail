<?php
/**
 * QueueService.php
 *
 * Gère la file d'attente des tests de débit (table FT_QUEUE).
 *
 * ── Contexte ─────────────────────────────────────────────────────────────────
 * Un seul test peut être actif à la fois par tranche réseau pour éviter que
 * des centaines de mesures simultanées ne saturent la liaison et faussent les
 * résultats. Chaque navigateur obtient un TOKEN unique, passe de WAITING à
 * READY quand c'est son tour, puis déclare DONE à la fin du test.
 *
 * ── Cycle de vie d'un token ──────────────────────────────────────────────────
 *   rejoindre() → crée l'entrée, statut READY si slot libre, sinon WAITING
 *   statut()    → vérifie si READY (avec promotion automatique du premier WAITING)
 *   terminer()  → marque DONE et promeut le suivant
 *
 * ── Constantes de statut ─────────────────────────────────────────────────────
 *   STATUS_WAITING (1)  Le navigateur attend son tour dans la file
 *   STATUS_READY   (2)  Le navigateur peut lancer le test immédiatement
 *   STATUS_DONE    (3)  Le test est terminé, slot libérable après 30s
 *
 * ── Sécurité des transactions ─────────────────────────────────────────────────
 * Toutes les promotions (WAITING → READY) et libérations (READY → DONE) sont
 * effectuées dans des transactions InnoDB avec SELECT ... FOR UPDATE pour éviter
 * les conditions de course lorsque plusieurs navigateurs appellent statut()
 * simultanément.
 */

// Déclaration conditionnelle pour éviter les re-définitions lors des tests PHPUnit
if (!defined('STATUS_WAITING')) define('STATUS_WAITING', 1);
if (!defined('STATUS_READY'))   define('STATUS_READY',   2);
if (!defined('STATUS_DONE'))    define('STATUS_DONE',    3);

if (class_exists('QueueService')) return;

class QueueService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ── Nettoyage des entrées obsolètes ──────────────────────────────────────

    /**
     * Supprime les tokens expirés selon leur statut.
     *
     * Appelé automatiquement avant rejoindre() pour éviter l'accumulation.
     *
     *   DONE    → supprimé après 30 secondes (fenêtre de réclamation passée)
     *   READY   → supprimé après 30 secondes (durée max d'un test précis ~25s)
     *   WAITING → supprimé après 10 minutes  (abandon silencieux)
     */
    public function nettoyer(): void
    {
        $this->pdo->query("
            DELETE FROM FT_QUEUE
            WHERE (ID_STATUS = " . STATUS_DONE    . " AND CREATED_AT < NOW() - INTERVAL 30 SECOND)
               OR (ID_STATUS = " . STATUS_READY   . " AND STARTED_AT < NOW() - INTERVAL 30 SECOND)
               OR (ID_STATUS = " . STATUS_WAITING . " AND CREATED_AT < NOW() - INTERVAL 10 MINUTE)
        ");
    }

    // ── Rejoindre la file ─────────────────────────────────────────────────────

    /**
     * Inscrit le navigateur dans la file et retourne son statut initial.
     *
     * Le verrou InnoDB sur FT_QUEUE_LOCK (table à une ligne) empêche deux
     * navigateurs d'obtenir STATUS_READY simultanément lors d'une arrivée groupée.
     *
     * @return array{token: string, ready: bool}
     * @throws Exception  En cas d'erreur PDO (propagée vers queue.php)
     */
    public function rejoindre(): array
    {
        $this->nettoyer();

        // Génère un token aléatoire de 32 caractères hexadécimaux
        $jeton = bin2hex(random_bytes(16));

        $this->pdo->beginTransaction();
        try {
            // Verrou exclusif sur la ligne sentinelle — bloque les joins concurrents
            $this->pdo->query("SELECT ID FROM FT_QUEUE_LOCK WHERE ID = 1 FOR UPDATE");

            // Le slot est occupé si un READY existe OU si un DONE date de moins de 30s
            // (la marge de 30s évite qu'un nouveau token vole le slot d'un test
            // qui vient juste de terminer et de poster terminer())
            $slotOccupe = (int) $this->pdo->query("
                SELECT COUNT(*) FROM FT_QUEUE
                WHERE ID_STATUS = " . STATUS_READY . "
                   OR (ID_STATUS = " . STATUS_DONE . " AND CREATED_AT > NOW() - INTERVAL 30 SECOND)
            ")->fetchColumn();

            $statutInitial = ($slotOccupe === 0) ? STATUS_READY : STATUS_WAITING;

            $this->pdo->prepare("
                INSERT INTO FT_QUEUE (TOKEN, ID_STATUS, CREATED_AT, STARTED_AT)
                VALUES (?, ?, NOW(), ?)
            ")->execute([
                $jeton,
                $statutInitial,
                // STARTED_AT renseigné immédiatement si READY, sinon null
                $statutInitial === STATUS_READY ? date('Y-m-d H:i:s') : null,
            ]);

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return [
            'token' => $jeton,
            'ready' => $statutInitial === STATUS_READY,
        ];
    }

    // ── Vérifier son statut dans la file ─────────────────────────────────────

    /**
     * Retourne le statut courant d'un token dans la file.
     *
     * La promotion (WAITING → READY) est exécutée à l'intérieur d'une transaction
     * FOR UPDATE pour être atomique — deux navigateurs ne peuvent pas être promus
     * simultanément même s'ils appellent statut() au même instant.
     *
     * Promeut automatiquement le premier WAITING si aucun READY n'existe.
     *
     * @param  string $jeton  Token obtenu via rejoindre()
     * @return array{ready: bool, position: int}|array{error: string}
     * @throws Exception  En cas d'erreur PDO
     */
    public function statut(string $jeton): array
    {
        $this->nettoyer();

        $estPret = false;

        $this->pdo->beginTransaction();
        try {
            // Verrouille toutes les lignes pour empêcher les promotions parallèles
            $lignes = $this->pdo->query("
                SELECT ID_QUEUE, TOKEN, ID_STATUS FROM FT_QUEUE
                ORDER BY ID_QUEUE ASC
                FOR UPDATE
            ")->fetchAll(PDO::FETCH_ASSOC);

            $maLigne    = null;
            $aUnPret    = false;

            foreach ($lignes as $ligne) {
                if ((int) $ligne['ID_STATUS'] === STATUS_READY) {
                    $aUnPret = true;
                }
                if ($ligne['TOKEN'] === $jeton) {
                    $maLigne = $ligne;
                }
            }

            // Token introuvable → expiré ou invalide
            if (!$maLigne) {
                $this->pdo->rollBack();
                return ['error' => 'Token invalide ou expiré'];
            }

            // Promotion : si aucun READY en cours, le premier WAITING devient READY
            if ((int) $maLigne['ID_STATUS'] === STATUS_WAITING && !$aUnPret) {
                $premierEnAttente = null;
                foreach ($lignes as $ligne) {
                    if ((int) $ligne['ID_STATUS'] === STATUS_WAITING) {
                        $premierEnAttente = $ligne['TOKEN'];
                        break; // Premier dans l'ordre d'arrivée (ORDER BY ID_QUEUE ASC)
                    }
                }

                if ($premierEnAttente === $jeton) {
                    $this->pdo->prepare("
                        UPDATE FT_QUEUE
                        SET ID_STATUS = ?, STARTED_AT = NOW()
                        WHERE TOKEN = ?
                    ")->execute([STATUS_READY, $jeton]);
                    $estPret = true;
                }
            } else {
                $estPret = (int) $maLigne['ID_STATUS'] === STATUS_READY;
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        // Position dans la file = nombre de WAITING insérés avant ce token
        $requetePosition = $this->pdo->prepare("
            SELECT COUNT(*) FROM FT_QUEUE
            WHERE ID_QUEUE < (SELECT ID_QUEUE FROM FT_QUEUE WHERE TOKEN = ?)
              AND ID_STATUS = ?
        ");
        $requetePosition->execute([$jeton, STATUS_WAITING]);
        $position = (int) $requetePosition->fetchColumn() + 1;

        return [
            'position' => $position,
            'ready'    => $estPret,
        ];
    }

    // ── Libérer la file ───────────────────────────────────────────────────────

    /**
     * Marque le token comme DONE et promeut le prochain WAITING.
     *
     * Gère deux cas :
     *   — Token READY   : test terminé normalement → DONE + promotion du suivant
     *   — Token WAITING : navigateur fermé avant son tour → suppression directe
     *
     * @param  string $jeton  Token à libérer
     * @return array{success: bool}
     * @throws Exception  En cas d'erreur PDO
     */
    public function terminer(string $jeton): array
    {
        $this->pdo->beginTransaction();
        try {
            $requete = $this->pdo->prepare(
                "SELECT ID_STATUS FROM FT_QUEUE WHERE TOKEN = ? FOR UPDATE"
            );
            $requete->execute([$jeton]);
            $ligne = $requete->fetch();

            if ($ligne && (int) $ligne['ID_STATUS'] === STATUS_READY) {
                // Test terminé — passer DONE et libérer STARTED_AT
                $this->pdo->prepare("
                    UPDATE FT_QUEUE SET ID_STATUS = ?, STARTED_AT = NULL
                    WHERE TOKEN = ?
                ")->execute([STATUS_DONE, $jeton]);

                // Promouvoir le plus ancien WAITING dans l'ordre d'arrivée
                $this->pdo->prepare("
                    UPDATE FT_QUEUE SET ID_STATUS = ?, STARTED_AT = NOW()
                    WHERE ID_STATUS = ?
                    ORDER BY CREATED_AT ASC
                    LIMIT 1
                ")->execute([STATUS_READY, STATUS_WAITING]);

            } elseif ($ligne) {
                // Token WAITING qui abandonne (fermeture d'onglet) → suppression immédiate
                $this->pdo->prepare(
                    "DELETE FROM FT_QUEUE WHERE TOKEN = ?"
                )->execute([$jeton]);
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return ['success' => true];
    }

    // ── Helpers pour les tests PHPUnit ────────────────────────────────────────

    /**
     * Retourne la ligne complète d'un token, ou null s'il est inexistant.
     * Utilisé par les tests pour inspecter l'état interne de la file.
     *
     * @param  string $jeton  Token à rechercher
     * @return array<string, mixed>|null
     */
    public function getLigne(string $jeton): ?array
    {
        $requete = $this->pdo->prepare("SELECT * FROM FT_QUEUE WHERE TOKEN = ?");
        $requete->execute([$jeton]);
        $ligne = $requete->fetch(PDO::FETCH_ASSOC);
        return $ligne ?: null;
    }

    /**
     * Retourne le nombre d'entrées ayant un statut donné.
     * Utilisé par les tests pour vérifier les comptages.
     *
     * @param  int $statutCode  STATUS_WAITING | STATUS_READY | STATUS_DONE
     * @return int
     */
    public function compterParStatut(int $statutCode): int
    {
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM FT_QUEUE WHERE ID_STATUS = $statutCode"
        )->fetchColumn();
    }

    /**
     * Vide complètement la table FT_QUEUE.
     * Utilisé par les tests pour repartir d'un état propre.
     */
    public function viderTable(): void
    {
        $this->pdo->query("DELETE FROM FT_QUEUE");
    }
}