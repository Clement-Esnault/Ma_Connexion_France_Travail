<?php
/**
 * backend/services/AuditService.php
 *
 * Centralise l'écriture des entrées d'audit dans FT_AUDIT_SITES.
 * À appeler après chaque AJOUT / MODIFICATION / SUPPRESSION de site.
 */

class AuditService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Enregistre une action d'audit.
     *
     * @param string      $action    'AJOUT' | 'MODIFICATION' | 'SUPPRESSION'
     * @param int         $idCompte  ID du compte connecté
     * @param string      $codeGx    CODE_GX_SITE concerné
     * @param string      $nomSite   NOM_SITE au moment de l'action
     * @param string      $ipAction  IP du client effectuant l'action
     * @param array|null  $avant     État avant (pour MODIFICATION)
     * @param array|null  $apres     État après  (pour MODIFICATION / AJOUT)
     */
    public function enregistrer(
        string $action,
        int    $idCompte,
        string $codeGx,
        string $nomSite,
        string $ipAction,
        ?array $avant  = null,
        ?array $apres  = null
    ): void {
        $detail = null;

        if ($action === 'MODIFICATION' && $avant !== null && $apres !== null) {
            // Ne conserver que les champs qui ont réellement changé
            $diff = [];
            foreach ($apres as $champ => $valeurApres) {
                $valeurAvant = $avant[$champ] ?? null;
                // Comparer en string pour éviter les faux positifs int/string
                    $aStr = is_numeric($valeurAvant) ? rtrim(rtrim((string)(float)$valeurAvant, '0'), '.') : (string)$valeurAvant;
                    $bStr = is_numeric($valeurApres) ? rtrim(rtrim((string)(float)$valeurApres, '0'), '.') : (string)$valeurApres;
                    if ($aStr !== $bStr) {
                    $diff[$champ] = [
                        'avant' => $valeurAvant,
                        'apres' => $valeurApres,
                    ];
                }
            }
            if (!empty($diff)) {
                $detail = json_encode($diff, JSON_UNESCAPED_UNICODE);
            }
        } elseif ($action === 'AJOUT' && $apres !== null) {
            $detail = json_encode(['ajout' => $apres], JSON_UNESCAPED_UNICODE);
        } elseif ($action === 'SUPPRESSION' && $avant !== null) {
            $detail = json_encode(['supprime' => $avant], JSON_UNESCAPED_UNICODE);
        }

        $this->pdo->prepare("
            INSERT INTO FT_AUDIT_SITES
                (ID_COMPTE, ACTION, ID_SITE, NOM_SITE, IP_ACTION, DETAIL)
            VALUES
                (:id_compte, :action, :id_site, :nom_site, :ip_action, :detail)
        ")->execute([
            ':id_compte' => $idCompte,
            ':action'    => $action,
            ':id_site'   => $codeGx,
            ':nom_site'  => $nomSite,
            ':ip_action' => $ipAction,
            ':detail'    => $detail,
        ]);
    }
}