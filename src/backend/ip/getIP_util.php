<?php
/**
 * getIP_util.php
 *
 * Utilitaires de détection et de résolution d'adresses IP.
 * Ce fichier ne produit aucune sortie — il ne fait que déclarer des fonctions.
 *
 * ── Fonctions exposées ────────────────────────────────────────────────────────
 *   normaliserCandidatIp()  Valide et normalise une IP brute extraite d'un header
 *   getClientIp()           Retourne l'IP réelle du client (gestion proxy/CDN)
 *   getClientInfo()         Retourne l'IP + le hostname DNS inverse
 *   rechercheInverseDns()      Requête DNS inverse vers le serveur DNS interne
 *   ipDansReseau()           Teste l'appartenance d'une IP à une plage CIDR
 */

/**
 * Normalise et valide une adresse IP candidate extraite d'un en-tête HTTP.
 *
 * Gère le cas des listes d'IPs séparées par virgule (X-Forwarded-For) en ne
 * retenant que la première valeur (IP la plus proche du client).
 *
 * @param string $brut         Valeur brute de l'en-tête
 * @param int    $flagsSupp   Flags FILTER_VALIDATE_IP supplémentaires (ex: FILTER_FLAG_IPV6)
 * @return string|false       IP validée ou false si invalide
 */
function normaliserCandidatIp(string $brut, int $flagsSupp = 0): string|false
{
    $ip = trim($brut);

    // X-Forwarded-For peut contenir "ip1, ip2, ip3" — on ne garde que la première
    if (($pos = strpos($ip, ',')) !== false) {
        $ip = trim(substr($ip, 0, $pos));
    }

    if ($ip === '') return false;

    return filter_var($ip, FILTER_VALIDATE_IP, $flagsSupp);
}

/**
 * Retourne l'adresse IP réelle du client en tenant compte des proxies et CDN.
 *
 * Ordre de priorité :
 *   1. HTTP_CF_CONNECTING_IPV6  Cloudflare IPv6 (réseau France Travail)
 *   2. HTTP_CLIENT_IP           Proxy standard
 *   3. HTTP_X_REAL_IP           Reverse proxy (Nginx)
 *   4. HTTP_X_FORWARDED_FOR     Proxy transparent (peut être chaîné)
 *   5. REMOTE_ADDR              Connexion directe (repli final)
 *
 * Le préfixe IPv4-mappé ::ffff: est supprimé pour homogénéiser les comparaisons CIDR.
 *
 * @return string  Adresse IPv4 ou IPv6 normalisée
 */
function getClientIp(): string
{
    
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IPV6'])) {
        $ip = normaliserCandidatIp($_SERVER['HTTP_CF_CONNECTING_IPV6'], FILTER_FLAG_IPV6);
        if ($ip !== false) {
            return preg_replace('/^::ffff:/', '', $ip);
        }
    }

    // Proxies standards
    foreach (['HTTP_CLIENT_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR'] as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = normaliserCandidatIp($_SERVER[$header]);
            if ($ip !== false) {
                return preg_replace('/^::ffff:/', '', $ip);
            }
        }
    }

    // Connexion directe
    $ip = normaliserCandidatIp($_SERVER['REMOTE_ADDR'] ?? '');
    if ($ip !== false) {
        return preg_replace('/^::ffff:/', '', $ip);
    }

    return $_SERVER['REMOTE_ADDR'] ?? '';
}

/**
 * Retourne l'IP et le hostname DNS inverse du client.
 *
 * Utilisé par getIP.php pour enrichir l'affichage de la page de test.
 * Le DNS inverse peut échouer (silencieusement) si le PTR est absent.
 *
 * @return array{ ip: string, site: string|null }
 */
function getClientInfo(): array
{
    $ip = getClientIp();

    return [
        'ip'   => $ip,
        'site' => rechercheInverseDns($ip, 1), // Timeout 1 seconde
    ];
}

/**
 * Résolution DNS inverse avec timeout configurable.
 *
 * Utilise une requête UDP brute vers le DNS interne France Travail (10.10.10.60)
 * car gethostbyaddr() ne supporte pas de timeout natif en PHP.
 * Retourne null si le PTR est absent, le serveur injoignable ou la réponse malformée.
 *
 * @param string $ip       Adresse IPv4 à résoudre
 * @param int    $delai  Timeout en secondes (défaut : 1)
 * @return string|null     Hostname ou null
 */
function rechercheInverseDns(string $ip, int $delai = 1): ?string
{
    // Construction du nom PTR : "1.2.3.4" → "4.3.2.1.in-addr.arpa"
    $parts   = array_reverse(explode('.', $ip));
    $ptr     = implode('.', $parts) . '.in-addr.arpa';

    $serveurDns = '10.10.10.60';
    $port      =  53;

    // En-tête DNS minimal : ID aléatoire, flags QUERY standard, 1 question
    $id      = rand(1, 65535);
    $request = pack('n6', $id, 0x0100, 1, 0, 0, 0);

    // Encodage de la question (format DNS : longueur + label pour chaque partie)
    foreach (explode('.', $ptr) as $libelle) {
        $request .= pack('Ca*', strlen($libelle), $libelle);
    }
    // Terminaison + QTYPE PTR (12) + QCLASS IN (1)
    $request .= pack('Cnn', 0, 12, 1);

    // Envoi UDP (sans connexion)
    $socket = @fsockopen('udp://' . $serveurDns, $port, $errno, $errstr, $delai);
    if (!$socket) return null;

    stream_set_timeout($socket, $delai);
    fwrite($socket, $request);
    $response = fread($socket, 512);
    fclose($socket);

    if ($response === false || strlen($response) < 12) return null;

    // Sauter la section question de la réponse (même format que la requête)
    $decalage = 12;
    while ($decalage < strlen($response)) {
        $longueur = ord($response[$decalage]);
        if ($longueur === 0) { $decalage++; break; }
        $decalage += $longueur + 1;
    }
    $decalage += 4;   // QTYPE + QCLASS
    $decalage += 10;  // Début RDATA (TYPE + CLASS + TTL + RDLENGTH)

    if ($decalage >= strlen($response)) return null;

    // Lecture du nom de domaine dans la RDATA
    $hostname = '';
    while ($decalage < strlen($response)) {
        $longueur = ord($response[$decalage]);
        if ($longueur === 0) break;
        if ($hostname !== '') {
            $hostname .= '.';
        }
        $hostname .= substr($response, $decalage + 1, $longueur);
        $decalage   += $longueur + 1;
    }

    return $hostname !== '' ? $hostname : null;
}

/**
 * Vérifie si une adresse IPv4 appartient à une plage réseau CIDR.
 *
 * Utilisé par save_result.php et getIP.php pour rattacher un poste client
 * au site France Travail correspondant à sa plage réseau.
 *
 * @param string     $ip       Adresse IPv4 du client (ex: "10.30.5.12")
 * @param string     $network  Adresse réseau (ex: "10.30.5.0")
 * @param int|string $masque     Longueur du préfixe CIDR (ex: 24)
 * @return bool
 */
function ipDansReseau(string $ip, string $reseau, int $masque): bool {
    if ($masque < 0 || $masque > 32) return false; // garde anti-crash
    $ipLong      = ip2long($ip);
    $reseauLong  = ip2long($reseau);
    if ($ipLong === false || $reseauLong === false) return false;
    $bits = ~((1 << (32 - $masque)) - 1);
    return ($ipLong & $bits) === ($reseauLong & $bits);
}