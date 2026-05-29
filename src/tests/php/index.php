<?php
/**
 * tests/php/index.php — Rapport visuel des tests PHPUnit
 *
 * Lit junit.xml généré par PHPUnit et affiche les résultats
 * dans la DA du projet Ma Connexion.
 *
 * Accès : http://YOUR_SERVER_IP/speedtest/tests/php/
 *
 * Pour générer junit.xml :
 *   C:\xampp\php\php.exe C:\SRV\phpunit-11.5.55.phar -c phpunit.xml
 */

$junitPath = __DIR__ . '/junit.xml';
$hasReport = file_exists($junitPath);
$data      = null;
$parseError = null;

if ($hasReport) {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($junitPath);
    if ($xml === false) {
        $parseError = 'Impossible de parser junit.xml.';
    } else {
        $data = parseJunit($xml, $junitPath);
    }
}

/**
 * Parse le XML JUnit de PHPUnit en structure exploitable.
 * Supporte les deux formats : testsuite imbriqué (PHPUnit 11) et plat.
 */
function parseJunit(SimpleXMLElement $xml, string $junitPath): array {
    $suites     = [];
    $totalTests = 0;
    $totalFail  = 0;
    $totalError = 0;
    $totalSkip  = 0;
    $totalTime  = 0.0;

    /**
     * Parcourt récursivement les <testsuite> pour trouver ceux qui contiennent
     * des <testcase> directs (= suites feuilles = fichiers de test).
     * Structure PHPUnit 11 : <testsuites><testsuite name="config"><testsuite name="Ma Connexion"><testsuite name="AuditTest"><testcase...
     */
    function extraireSuites(SimpleXMLElement $node, array &$suites, int &$totalTests, int &$totalFail, int &$totalError, int &$totalSkip, float &$totalTime): void {
        // Ce nœud contient des testcase directs → c'est une suite feuille
        if (isset($node->testcase)) {
            $suiteName  = (string)($node['name'] ?? 'Sans nom');
            $suiteTests = [];

            foreach ($node->testcase as $tc) {
                $name    = (string)($tc['name']      ?? '');
                $class   = (string)($tc['classname'] ?? '');
                $time    = round((float)($tc['time'] ?? 0) * 1000);
                $status  = 'pass';
                $message = null;
                $detail  = null;

                if (isset($tc->failure)) {
                    $status  = 'fail';
                    $message = (string)($tc->failure['message'] ?? 'Échec');
                    $detail  = (string)$tc->failure;
                } elseif (isset($tc->error)) {
                    $status  = 'error';
                    $message = (string)($tc->error['message'] ?? 'Erreur');
                    $detail  = (string)$tc->error;
                } elseif (isset($tc->skipped)) {
                    $status = 'skip';
                }

                $suiteTests[] = compact('name', 'class', 'time', 'status', 'message', 'detail');
                $totalTests++;
                $totalTime += (float)($tc['time'] ?? 0);
                if ($status === 'fail')  $totalFail++;
                if ($status === 'error') $totalError++;
                if ($status === 'skip')  $totalSkip++;
            }

            if (!empty($suiteTests)) {
                $suiteFail = count(array_filter($suiteTests, fn($t) => in_array($t['status'], ['fail', 'error'])));
                $suites[]  = [
                    'name'  => $suiteName,
                    'tests' => $suiteTests,
                    'fail'  => $suiteFail,
                    'pass'  => count($suiteTests) - $suiteFail,
                ];
            }
        }

        // Parcourir les sous-suites récursivement
        foreach ($node->testsuite as $child) {
            extraireSuites($child, $suites, $totalTests, $totalFail, $totalError, $totalSkip, $totalTime);
        }
    }

    // Point d'entrée : le nœud racine peut être <testsuites> ou <testsuite>
    $root = $xml->getName() === 'testsuites' ? $xml : $xml;
    foreach ($root->testsuite as $topSuite) {
        extraireSuites($topSuite, $suites, $totalTests, $totalFail, $totalError, $totalSkip, $totalTime);
    }
    // Cas où les testcase sont directement sous la racine
    if (isset($xml->testcase)) {
        extraireSuites($xml, $suites, $totalTests, $totalFail, $totalError, $totalSkip, $totalTime);
    }

    return [
        'suites'      => $suites,
        'totalTests'  => $totalTests,
        'totalPass'   => $totalTests - $totalFail - $totalError - $totalSkip,
        'totalFail'   => $totalFail + $totalError,
        'totalSkip'   => $totalSkip,
        'totalTime'   => round($totalTime * 1000),
        'generatedAt' => date('d/m/Y H:i', filemtime($junitPath)),
    ];
}

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tests PHP — Ma Connexion</title>

    <link rel="preload" href="../../frontend/fonts/Marianne-Regular.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="../../frontend/fonts/Marianne-Medium.woff2"  as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="../../frontend/fonts/Marianne-Bold.woff2"    as="font" type="font/woff2" crossorigin>
    <link rel="icon" type="image/png" href="../../frontend/fonts/favicon.png">
    <link rel="stylesheet" href="../../frontend/fonts/style.css">

    <style>
        .page { max-width: 1000px; margin: 28px auto; padding: 0 20px; }

        /* ── Barre de résultat ── */
        .result-bar {
            height: 6px;
            border-radius: 99px;
            margin-bottom: 20px;
        }
        .result-bar.pass { background: var(--ft-success); }
        .result-bar.fail { background: var(--ft-red); }
        .result-bar.none { background: var(--ft-border); }

        /* ── KPI cards ── */
        .kpi-row {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        .kpi-card {
            background: var(--ft-white);
            border: 1px solid var(--ft-border);
            border-radius: var(--ft-radius-md);
            box-shadow: var(--ft-shadow-xs);
            padding: 14px 18px;
            text-align: center;
        }
        .kpi-val { font-size: 28px; font-weight: 700; color: var(--ft-primary); line-height: 1.1; }
        .kpi-lbl { font-size: 12px; color: var(--ft-muted); margin-top: 4px; }
        .kpi-card.pass .kpi-val { color: var(--ft-success); }
        .kpi-card.fail .kpi-val { color: var(--ft-red); }
        .kpi-card.skip .kpi-val { color: var(--ft-warn); }
        .kpi-card.time .kpi-val { color: var(--ft-blue2); font-size: 22px; }

        /* ── Info générée ── */
        .report-meta {
            font-size: 12px;
            color: var(--ft-muted);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
        }
        .report-meta code {
            font-size: 11px;
            background: var(--ft-light);
            padding: 2px 8px;
            border-radius: 4px;
            color: var(--ft-primary);
        }
        .btn-refresh {
            background: var(--ft-blue2);
            color: #fff;
            border: none;
            border-radius: var(--ft-radius-sm);
            padding: 5px 14px;
            font-family: "Marianne", sans-serif;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-refresh:hover { background: var(--ft-blue); }

        /* ── Module accordion ── */
        .module-block {
            background: var(--ft-white);
            border: 1px solid var(--ft-border);
            border-radius: var(--ft-radius-md);
            box-shadow: var(--ft-shadow-xs);
            margin-bottom: 10px;
            overflow: hidden;
        }
        .module-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 18px;
            background: var(--ft-light);
            border-bottom: 1px solid var(--ft-border);
            cursor: pointer;
            user-select: none;
            gap: 12px;
        }
        .module-header:hover { background: #d0d8f5; }
        .module-name  { font-weight: 700; color: var(--ft-primary); font-size: 14px; flex: 1; }
        .module-stats { display: flex; gap: 8px; align-items: center; flex-shrink: 0; }
        .pill {
            padding: 2px 10px; border-radius: 99px;
            font-weight: 600; font-size: 11px;
        }
        .pill-pass { background: #d4edda; color: #155724; }
        .pill-fail { background: #f8d7da; color: #721c24; }
        .pill-skip { background: #fff3cd; color: #856404; }
        .module-toggle { font-size: 11px; color: var(--ft-muted); transition: transform .2s; flex-shrink: 0; }
        .module-toggle.open { transform: rotate(180deg); }

        .module-tests { display: none; }
        .module-tests.open { display: block; }

        /* ── Ligne de test ── */
        .test-row {
            display: flex;
            align-items: flex-start;
            padding: 8px 18px;
            border-bottom: 1px solid var(--ft-border-light);
            gap: 10px;
            font-size: 13px;
        }
        .test-row:last-child { border-bottom: none; }
        .test-row.pass { border-left: 3px solid var(--ft-success); }
        .test-row.fail,
        .test-row.error { border-left: 3px solid var(--ft-red); background: #fff8f8; }
        .test-row.skip  { border-left: 3px solid var(--ft-warn); background: #fffbf0; }

        .test-icon { flex-shrink: 0; font-size: 14px; margin-top: 1px; }
        .test-info { flex: 1; }
        .test-name { color: var(--ft-text); }
        .test-row.fail .test-name,
        .test-row.error .test-name { color: var(--ft-red); font-weight: 600; }
        .test-row.skip .test-name  { color: var(--ft-warn); }
        .test-duration { font-size: 11px; color: var(--ft-muted); white-space: nowrap; flex-shrink: 0; }

        .test-detail {
            margin-top: 6px;
            background: #fff0f0;
            border: 1px solid #ffd0cc;
            border-radius: var(--ft-radius-sm);
            padding: 8px 12px;
            font-size: 11px;
            color: #721c24;
            font-family: monospace;
            white-space: pre-wrap;
            word-break: break-all;
            max-height: 120px;
            overflow-y: auto;
        }

        /* ── État sans rapport ── */
        .no-report {
            background: var(--ft-white);
            border: 1px solid var(--ft-border);
            border-radius: var(--ft-radius-md);
            padding: 40px 30px;
            text-align: center;
        }
        .no-report-icon { font-size: 40px; margin-bottom: 12px; }
        .no-report h2   { color: var(--ft-primary); margin-bottom: 8px; font-size: 18px; }
        .no-report p    { color: var(--ft-muted); font-size: 13px; margin-bottom: 16px; }
        .no-report code {
            display: block;
            background: var(--ft-light);
            padding: 10px 16px;
            border-radius: var(--ft-radius-sm);
            font-size: 12px;
            color: var(--ft-primary);
            text-align: left;
            margin: 8px 0;
        }

        @media (max-width: 640px) {
            .kpi-row { grid-template-columns: repeat(3, 1fr); }
        }
    </style>
</head>
<body>

<div class="bandeau-dsi">
    Outil interne — DSI France Travail &nbsp;·&nbsp; Tests PHP — Réservé aux développeurs
</div>

<div class="header">
    <div class="logo-bar">
        <img src="../../frontend/fonts/logo_ft.svg" alt="France Travail" height="40" style="height:40px;width:auto;">
        <div class="logo-separateur"></div>
        <span class="logo-text">Ma Connexion</span>
    </div>
    <span style="font-size:13px;color:var(--ft-muted);font-weight:500;">Tests PHP — PHPUnit</span>
</div>

<div class="page">

<?php if ($parseError): ?>
    <div class="no-report">
        <div class="no-report-icon">⚠️</div>
        <h2>Erreur de lecture</h2>
        <p><?= e($parseError) ?></p>
    </div>

<?php elseif (!$hasReport || !$data): ?>
    <div class="no-report">
        <div class="no-report-icon">🧪</div>
        <h2>Aucun rapport disponible</h2>
        <p>Lancez PHPUnit pour générer le rapport. Le fichier <code>junit.xml</code> sera créé automatiquement.</p>
        <code>cd C:\xampp\htdocs\speedtest\tests\php</code>
        <code>C:\xampp\php\php.exe C:\SRV\phpunit-11.5.55.phar -c phpunit.xml</code>
        <p style="margin-top:12px;font-size:12px;color:var(--ft-muted);">
            Puis rechargez cette page.
        </p>
    </div>

<?php else:
    $allPass = $data['totalFail'] === 0;
    $barClass = $data['totalTests'] === 0 ? 'none' : ($allPass ? 'pass' : 'fail');
?>

    <!-- Barre résultat -->
    <div class="result-bar <?= $barClass ?>"></div>

    <!-- KPIs -->
    <div class="kpi-row">
        <div class="kpi-card">
            <div class="kpi-val"><?= $data['totalTests'] ?></div>
            <div class="kpi-lbl">Tests</div>
        </div>
        <div class="kpi-card pass">
            <div class="kpi-val"><?= $data['totalPass'] ?></div>
            <div class="kpi-lbl">Passés</div>
        </div>
        <div class="kpi-card fail">
            <div class="kpi-val"><?= $data['totalFail'] ?></div>
            <div class="kpi-lbl">Échecs</div>
        </div>
        <div class="kpi-card skip">
            <div class="kpi-val"><?= $data['totalSkip'] ?></div>
            <div class="kpi-lbl">Ignorés</div>
        </div>
        <div class="kpi-card time">
            <div class="kpi-val"><?= number_format($data['totalTime'] / 1000, 2) ?> s</div>
            <div class="kpi-lbl">Durée</div>
        </div>
    </div>

    <!-- Méta -->
    <div class="report-meta">
        <span>Rapport généré le <strong><?= $data['generatedAt'] ?></strong> — <?= count($data['suites']) ?> suite(s)</span>
        <a href="?" class="btn-refresh">↺ Actualiser</a>
    </div>

    <!-- Suites -->
    <?php foreach ($data['suites'] as $suite):
        $allSuitePass = $suite['fail'] === 0;
    ?>
    <div class="module-block">
        <div class="module-header" onclick="toggleSuite(this)">
            <span class="module-name"><?= e($suite['name']) ?></span>
            <span class="module-stats">
                <span class="pill pill-pass"><?= $suite['pass'] ?> ✓</span>
                <?php if ($suite['fail'] > 0): ?>
                    <span class="pill pill-fail"><?= $suite['fail'] ?> ✗</span>
                <?php endif; ?>
            </span>
            <span class="module-toggle <?= $allSuitePass ? '' : 'open' ?>">▼</span>
        </div>
        <div class="module-tests <?= $allSuitePass ? '' : 'open' ?>">
            <?php foreach ($suite['tests'] as $test):
                $icon = match($test['status']) {
                    'pass'  => '✅',
                    'fail'  => '❌',
                    'error' => '💥',
                    'skip'  => '⏭️',
                    default => '❓'
                };
            ?>
            <div class="test-row <?= $test['status'] ?>">
                <span class="test-icon"><?= $icon ?></span>
                <div class="test-info">
                    <div class="test-name"><?= e($test['name']) ?></div>
                    <?php if ($test['message']): ?>
                        <div class="test-detail"><?= e(trim($test['detail'] ?? $test['message'])) ?></div>
                    <?php endif; ?>
                </div>
                <span class="test-duration"><?= $test['time'] ?> ms</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

<?php endif; ?>

</div>

<div class="footer">
    Ma Connexion — DSI France Travail &nbsp;·&nbsp; Tests PHP — PHPUnit 11
</div>

<script>
function toggleSuite(header) {
    const body   = header.nextElementSibling;
    const toggle = header.querySelector('.module-toggle');
    body.classList.toggle('open');
    toggle.classList.toggle('open');
}
</script>
</body>
</html>