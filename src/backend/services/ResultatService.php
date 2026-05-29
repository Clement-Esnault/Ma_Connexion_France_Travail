<?php

if (class_exists('ResultatService')) return;

/**
 * ResultatService
 *
 * Encapsule la validation et l'insertion d'un résultat de test de débit.
 * Extrait de backend/ip/save_result.php pour permettre les tests unitaires
 * sans passer par le protocole HTTP.
 *
 * Usage :
 *   $service = new ResultatService($pdo);
 *   $erreur  = $service->valider($ping, $dl, $ul, $mode, $sessionId);
 *   if ($erreur !== null) { echo json_encode(['success' => false, 'error' => $erreur]); exit; }
 *   $service->inserer($ping, $dl, $ul, $mode, $sessionId, $ip, $codeGx);
 */
class ResultatService
{
    /** Modes de test acceptés en base. */
    private const MODES_VALIDES = ['precise'];

    public function __construct(private PDO $pdo) {}

    /**
     * Valide les valeurs d'un résultat de test.
     * Retourne un message d'erreur si invalide, null si tout est bon.
     *
     * @param float  $ping       Latence en ms
     * @param float  $dl         Débit descendant en Mbit/s
     * @param float  $ul         Débit montant en Mbit/s
     * @param string $mode       Mode de test
     * @param string $sessionId  UUID de session
     * @return string|null  Message d'erreur ou null
     */
    public function valider(float $ping, float $dl, float $ul, string $mode, string $sessionId): ?string
    {
        if ($ping <= 0 || $dl <= 0 || $ul <= 0) {
            return 'Valeurs de mesure invalides (ping, download ou upload ≤ 0)';
        }
        if (!in_array($mode, self::MODES_VALIDES, true)) {
            return "Mode '$mode' invalide. Valeurs acceptées : " . implode(', ', self::MODES_VALIDES);
        }
        if (!preg_match('/^[a-f0-9\-]{36}$/', $sessionId)) {
            return 'session_id invalide (format UUID v4 attendu)';
        }
        return null;
    }

    /**
     * Insère un résultat validé en base.
     * Pré-condition : appeler valider() avant.
     *
     * @param float  $ping
     * @param float  $dl
     * @param float  $ul
     * @param string $mode
     * @param string $sessionId
     * @param string $ipClient
     * @param string $codeGxSite
     */
    public function inserer(
        float  $ping,
        float  $dl,
        float  $ul,
        string $mode,
        string $sessionId,
        string $ipClient,
        string $codeGxSite,
    ): void {
        $stmt = $this->pdo->prepare('
            INSERT INTO FT_LOGS
                (PING_LOGS, DOWNLOAD_LOGS, UPLOAD_LOGS, MODE, SESSION_ID, IP_CLIENT, CODE_GX_SITE)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$ping, $dl, $ul, $mode, $sessionId, $ipClient, $codeGxSite]);
    }

    /**
     * Normalise et sécurise un session_id reçu depuis l'extérieur.
     * Filtre les caractères non-UUID et tronque à 36 caractères.
     *
     * @param string $brut  Valeur brute reçue
     * @return string
     */
    public static function normaliserSessionId(string $brut): string
    {
        return substr(preg_replace('/[^a-f0-9\-]/', '', strtolower($brut)), 0, 36);
    }
}