<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tests JS — Ma Connexion</title>

    <link rel="preload" href="../../frontend/fonts/Marianne-Regular.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="../../frontend/fonts/Marianne-Medium.woff2"  as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="../../frontend/fonts/Marianne-Bold.woff2"    as="font" type="font/woff2" crossorigin>
    <link rel="icon" type="image/png" href="../../frontend/fonts/favicon.png">
    <link rel="stylesheet" href="../../frontend/fonts/style.css">

    <style>
        /* QUnit tourne en silence — on cache tout son HTML natif */
        #qunit, #qunit-fixture { display: none !important; }

        .page { max-width: 1000px; margin: 28px auto; padding: 0 20px; }

        /* ── Barre de progression globale ── */
        .test-progress-bar {
            height: 6px;
            background: var(--ft-border-light);
            border-radius: 99px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .test-progress-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--ft-blue), var(--ft-blue2));
            border-radius: 99px;
            transition: width .3s ease;
        }
        .test-progress-fill.done-pass { background: var(--ft-success); }
        .test-progress-fill.done-fail { background: var(--ft-red); }

        /* ── KPI cards ── */
        .kpi-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
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
        .kpi-val  { font-size: 28px; font-weight: 700; color: var(--ft-primary); line-height: 1.1; }
        .kpi-lbl  { font-size: 12px; color: var(--ft-muted); margin-top: 4px; }
        .kpi-card.pass .kpi-val { color: var(--ft-success); }
        .kpi-card.fail .kpi-val { color: var(--ft-red); }
        .kpi-card.time .kpi-val { color: var(--ft-blue2); font-size: 22px; }

        /* ── Module accordion ── */
        .module-block {
            background: var(--ft-white);
            border: 1px solid var(--ft-border);
            border-radius: var(--ft-radius-md);
            box-shadow: var(--ft-shadow-xs);
            margin-bottom: 12px;
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
        .module-name  { font-weight: 700; color: var(--ft-primary); font-size: 14px; }
        .module-stats { font-size: 12px; color: var(--ft-muted); display: flex; gap: 10px; align-items: center; }
        .module-stats .pill {
            padding: 2px 10px; border-radius: 99px; font-weight: 600; font-size: 11px;
        }
        .pill-pass { background: #d4edda; color: #155724; }
        .pill-fail { background: #f8d7da; color: #721c24; }
        .module-toggle { font-size: 12px; color: var(--ft-muted); transition: transform .2s; }
        .module-toggle.open { transform: rotate(180deg); }

        .module-tests { display: none; }
        .module-tests.open { display: block; }

        /* ── Ligne de test ── */
        .test-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 18px;
            border-bottom: 1px solid var(--ft-border-light);
            gap: 12px;
            font-size: 13px;
        }
        .test-row:last-child { border-bottom: none; }
        .test-row.pass { border-left: 3px solid var(--ft-success); }
        .test-row.fail { border-left: 3px solid var(--ft-red); background: #fff8f8; }

        .test-icon { font-size: 14px; flex-shrink: 0; }
        .test-name { flex: 1; color: var(--ft-text); }
        .test-row.fail .test-name { color: var(--ft-red); font-weight: 600; }
        .test-assertions { font-size: 11px; color: var(--ft-muted); white-space: nowrap; }
        .test-duration   { font-size: 11px; color: var(--ft-muted); white-space: nowrap; min-width: 40px; text-align: right; }

        /* ── Détail assertion échouée ── */
        .test-detail {
            background: #fff3f3;
            border: 1px solid #ffd0cc;
            border-radius: var(--ft-radius-sm);
            margin: 0 18px 10px;
            padding: 10px 14px;
            font-size: 12px;
            color: #721c24;
        }
        .test-detail-msg   { font-weight: 600; margin-bottom: 6px; }
        .test-detail-row   { display: flex; gap: 8px; margin-top: 3px; }
        .test-detail-label { color: var(--ft-muted); min-width: 70px; }
        .test-detail-val   { font-family: monospace; word-break: break-all; }

        /* ── État loading ── */
        .test-loading {
            text-align: center;
            padding: 40px 20px;
            color: var(--ft-muted);
            font-size: 14px;
        }
        .spinner-inline {
            display: inline-block;
            width: 16px; height: 16px;
            border: 2px solid var(--ft-border);
            border-top-color: var(--ft-blue2);
            border-radius: 50%;
            animation: spin .7s linear infinite;
            vertical-align: middle;
            margin-right: 8px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 600px) {
            .kpi-row { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<div class="bandeau-dsi">
    Outil interne — DSI France Travail &nbsp;·&nbsp; Tests JavaScript — Réservé aux développeurs
</div>

<div class="header">
    <div class="logo-bar">
        <img src="../../frontend/fonts/logo_ft.svg" alt="France Travail" height="40" style="height:40px;width:auto;">
        <div class="logo-separateur"></div>
        <span class="logo-text">Ma Connexion</span>
    </div>
    <span style="font-size:13px;color:var(--ft-muted);font-weight:500;">Tests JavaScript</span>
</div>

<div class="page">

    <div class="test-progress-bar">
        <div class="test-progress-fill" id="progress-fill"></div>
    </div>

    <!-- KPIs -->
    <div class="kpi-row">
        <div class="kpi-card">
            <div class="kpi-val" id="kpi-total">—</div>
            <div class="kpi-lbl">Tests</div>
        </div>
        <div class="kpi-card pass">
            <div class="kpi-val" id="kpi-pass">—</div>
            <div class="kpi-lbl">Passés</div>
        </div>
        <div class="kpi-card fail">
            <div class="kpi-val" id="kpi-fail">—</div>
            <div class="kpi-lbl">Échecs</div>
        </div>
        <div class="kpi-card time">
            <div class="kpi-val" id="kpi-time">—</div>
            <div class="kpi-lbl">Durée</div>
        </div>
    </div>

    <!-- Modules -->
    <div id="modules-container">
        <div class="test-loading">
            <span class="spinner-inline"></span>Exécution des tests…
        </div>
    </div>

</div>

<div class="footer">
    Ma Connexion — DSI France Travail &nbsp;·&nbsp; Tests JavaScript &nbsp;·&nbsp;
    QUnit v2.25 &nbsp;·&nbsp;
    <a href="http://YOUR_SERVER_IP/speedtest/tests/js/" style="color:var(--ft-blue2);">
        http://YOUR_SERVER_IP/speedtest/tests/js/
    </a>
</div>

<!-- QUnit en silence -->
<div id="qunit"></div>
<div id="qunit-fixture"></div>
<div id="daltonien-label"   style="display:none"></div>
<div id="daltonien-menu"    style="display:none" class="hidden"></div>
<div id="btn-daltonien"     style="display:none" aria-expanded="false"></div>
<div id="badge-nav-alertes" style="display:none" class="hidden"></div>
<div id="ft-tooltip"        style="display:none"></div>

<script src="qunit.js"></script>
<script>QUnit.config.autostart = false;</script>

<script>
window.FT_API = {
    garbageEndpoint: "/backend/ip/garbage.php",
    uploadEndpoint:  "/backend/ip/upload_measure.php",
    queueJoin:       "/backend/ip/queue.php?action=join&token=",
    queueStatus:     "/backend/ip/queue.php?action=status&token=",
    queueDone:       "/backend/ip/queue.php?action=done&token=",
    getConfig:       "/backend/ip/get_config_speedtest.php",
    saveResult:      "/backend/ip/save_result.php",
};

// ── Collecte des résultats via l'API QUnit ────────────────────────────────
const modules = {};
let totalTests = 0;

QUnit.moduleStart(function(details) {
    modules[details.name] = { tests: [], pass: 0, fail: 0 };
});

QUnit.testDone(function(details) {
    const mod = modules[details.module] || (modules[details.module] = { tests: [], pass: 0, fail: 0 });
    mod.tests.push(details);
    if (details.failed > 0) mod.fail++; else mod.pass++;
    totalTests++;
    const fill = document.getElementById("progress-fill");
    fill.style.width = Math.min(95, totalTests * 2) + "%";
});

QUnit.done(function(details) {
    renderResults(details);
});

function renderResults(summary) {
    document.getElementById("kpi-total").textContent = summary.total;
    document.getElementById("kpi-pass").textContent  = summary.passed;
    document.getElementById("kpi-fail").textContent  = summary.failed;
    document.getElementById("kpi-time").textContent  = (summary.runtime / 1000).toFixed(2) + " s";

    const fill = document.getElementById("progress-fill");
    fill.style.width = "100%";
    fill.classList.add(summary.failed === 0 ? "done-pass" : "done-fail");

    const container = document.getElementById("modules-container");
    container.innerHTML = "";

    Object.entries(modules).forEach(function(entry) {
        const modName = entry[0];
        const mod     = entry[1];
        const allPass = mod.fail === 0;

        const block = document.createElement("div");
        block.className = "module-block";

        const header = document.createElement("div");
        header.className = "module-header";

        const nameSpan = document.createElement("span");
        nameSpan.className = "module-name";
        nameSpan.textContent = modName;

        const statsSpan = document.createElement("span");
        statsSpan.className = "module-stats";

        const pillPass = document.createElement("span");
        pillPass.className = "pill pill-pass";
        pillPass.textContent = mod.pass + " \u2713";
        statsSpan.appendChild(pillPass);

        if (mod.fail > 0) {
            const pillFail = document.createElement("span");
            pillFail.className = "pill pill-fail";
            pillFail.textContent = mod.fail + " \u2717";
            statsSpan.appendChild(pillFail);
        }

        const toggle = document.createElement("span");
        toggle.className = "module-toggle" + (allPass ? "" : " open");
        toggle.textContent = "\u25bc";

        header.appendChild(nameSpan);
        header.appendChild(statsSpan);
        header.appendChild(toggle);

        const body = document.createElement("div");
        body.className = "module-tests" + (allPass ? "" : " open");

        mod.tests.forEach(function(test) {
            const pass = test.failed === 0;

            const row = document.createElement("div");
            row.className = "test-row " + (pass ? "pass" : "fail");

            const icon = document.createElement("span");
            icon.className = "test-icon";
            icon.textContent = pass ? "\u2705" : "\u274c";

            const name = document.createElement("span");
            name.className = "test-name";
            name.textContent = test.name;

            const assertions = document.createElement("span");
            assertions.className = "test-assertions";
            assertions.textContent = test.passed + "/" + test.total + " assertions";

            const duration = document.createElement("span");
            duration.className = "test-duration";
            duration.textContent = test.runtime + " ms";

            row.appendChild(icon);
            row.appendChild(name);
            row.appendChild(assertions);
            row.appendChild(duration);
            body.appendChild(row);

            if (!pass && test.assertions) {
                test.assertions.forEach(function(a) {
                    if (a.result) return;
                    const detail = document.createElement("div");
                    detail.className = "test-detail";

                    const msg = document.createElement("div");
                    msg.className = "test-detail-msg";
                    msg.textContent = "\u26a0 " + (a.message || "Assertion \u00e9chou\u00e9e");
                    detail.appendChild(msg);

                    [["Attendu :", a.expected], ["Obtenu :", a.actual]].forEach(function(pair) {
                        const rowD = document.createElement("div");
                        rowD.className = "test-detail-row";
                        const lbl = document.createElement("span");
                        lbl.className = "test-detail-label";
                        lbl.textContent = pair[0];
                        const val = document.createElement("span");
                        val.className = "test-detail-val";
                        val.textContent = String(pair[1] !== undefined ? pair[1] : "\u2014");
                        rowD.appendChild(lbl);
                        rowD.appendChild(val);
                        detail.appendChild(rowD);
                    });
                    body.appendChild(detail);
                });
            }
        });

        header.addEventListener("click", function() {
            body.classList.toggle("open");
            toggle.classList.toggle("open");
        });

        block.appendChild(header);
        block.appendChild(body);
        container.appendChild(block);
    });
}
</script>

<script src="../../speedtest.js"></script>
<script src="../../frontend/js/utils.js"></script>
<script src="../../frontend/js/header.js"></script>
<script src="test.speedtest.js"></script>
<script src="test.utils.js"></script>
<script src="test.header.js"></script>
<script>QUnit.start();</script>

</body>
</html>