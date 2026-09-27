<?php

$dbPath = '/opt/weather/weather.db';

$dataFiltro =$_GET['data_filtro'] ?? '';
$tempCondizione =$_GET['temp_cond'] ?? 'maggiore';
$tempValore =$_GET['temp_val'] ?? '';


if (isset($_GET['limite'])) {
    $limite = (int)$_GET['limite'];
} else {
    $limite = 100;
}

$rilevamenti = [];$errore = null;
$totaleFiltrati = 0;

try {
    $db = new PDO('sqlite:' .$dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $where = [];$parametri = [];

    if ($dataFiltro !== '') {$where[] = 'DATE(data_ora) = :data';
        $parametri[':data'] =$dataFiltro;
    }

    if ($tempValore !== '') {
        $operatore =$tempCondizione === 'minore' ? '<' : '>';
        $where[] = "percepita $operatore :temperatura";
        $parametri[':temperatura'] = (float)$tempValore;
    }

    $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';

   
    $stmtCount = $db->prepare('SELECT COUNT(*) FROM dati_meteo' .$sqlWhere);
    $stmtCount->execute($parametri);
    $totaleFiltrati = (int)$stmtCount->fetchColumn();

    
    if ($limite > 0) {$sql = 'SELECT * FROM dati_meteo' . $sqlWhere . ' ORDER BY data_ora DESC LIMIT :limite';$stmt = $db->prepare($sql);
        foreach ($parametri as$key => $val) {$stmt->bindValue($key,$val);
        }
        $stmt->bindValue(':limite',$limite, PDO::PARAM_INT);
    } else {
        $sql = 'SELECT * FROM dati_meteo' . $sqlWhere . ' ORDER BY data_ora DESC';$stmt = $db->prepare($sql);
        foreach ($parametri as$key => $val) {$stmt->bindValue($key,$val);
        }
    }

    $stmt->execute();
    $rilevamenti =$stmt->fetchAll();

} catch (PDOException $e) {
    $errore = 'Errore di connessione al database: ' .$e->getMessage();
}


if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    ob_start();
    if ($rilevamenti):
        foreach ($rilevamenti as$rilevamento): ?>
            <tr>
                <td class="text-id"><?= htmlspecialchars($rilevamento['id']) ?></td>
                <td class="font-mono"><?= date('d/m/Y H:i:s', strtotime($rilevamento['data_ora']) + 7200) ?></td>
                <td class="text-temp"><?= number_format((float) $rilevamento['temperatura'], 1) ?>°C</td>
                <td class="text-feels"><?= number_format((float) $rilevamento['percepita'], 1) ?>°C</td>
                <td class="text-humidity"><?= htmlspecialchars($rilevamento['umidita']) ?>%</td>
                <td class="capitalize"><?= htmlspecialchars($rilevamento['descrizione']) ?></td>
            </tr>
        <?php endforeach;
    else: ?>
        <tr>
            <td colspan="6" class="empty-message">
                <p>Nessun rilevamento trovato.</p>
            </td>
        </tr>
    <?php endif;
    $rowsHtml = ob_get_clean();

    $rimanenti = max(0, $totaleFiltrati - count($rilevamenti));

    echo json_encode([
        'success' => $errore === null,
        'errore' => $errore,
        'html' => $rowsHtml,
        'totale' => $totaleFiltrati,
        'caricati' => count($rilevamenti),
        'rimanenti' => $rimanenti,
        'limiteAttuale' => $limite
    ]);
    exit;
}

$rimanenti = max(0, $totaleFiltrati - count($rilevamenti));
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Completo - Meteo</title>
    <link rel="stylesheet" href="stile.css?v=<?= time() ?>">
</head>
<body>

<div class="container">

    <header class="header">
        <h1>Archivio totale database</h1>
        <a href="index.php" class="btn-archive">Torna alla home</a>
    </header>

    <div id="error-container">
        <?php if ($errore): ?>
            <div class="error-message">
                <div class="error-title">Attenzione</div>
                <div class="error-text"><?= htmlspecialchars($errore) ?></div>
            </div>
        <?php endif; ?>
    </div>

    <section class="glass-panel filter-panel">
        <form id="filter-form" method="GET" action="mostraDB.php" class="filter-grid">
            <input type="hidden" name="limite" id="input-limite" value="100">
            
            <div class="form-group">
                <label for="data_filtro">Giorno</label>
                <input type="date" id="data_filtro" name="data_filtro" value="<?= htmlspecialchars($dataFiltro) ?>" class="form-control">
            </div>

            <div class="form-group">
                <label for="temp_cond">Condizione</label>
                <select id="temp_cond" name="temp_cond" class="form-control">
                    <option value="maggiore" <?= $tempCondizione === 'maggiore' ? 'selected' : '' ?>>Maggiore di (&gt;)</option>
                    <option value="minore" <?= $tempCondizione === 'minore' ? 'selected' : '' ?>>Minore di (&lt;)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="temp_val">Temperatura percepita</label>
                <input type="number" id="temp_val" name="temp_val" step="0.1" placeholder="Es. 22.5" value="<?= htmlspecialchars($tempValore) ?>" class="form-control">
            </div>

            <div class="form-group btn-group">
                <button type="submit" class="btn-submit">Filtra</button>
                <button type="button" id="btn-reset" class="btn-reset">Reset</button>
            </div>
        </form>
    </section>

    <section class="glass-panel">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Data e ora</th>
                        <th>Temp. (°C)</th>
                        <th>Percepita (°C)</th>
                        <th>Umidità</th>
                        <th>Descrizione</th>
                    </tr>
                </thead>
                <tbody id="table-body">
                <?php if ($rilevamenti): ?>
                    <?php foreach ($rilevamenti as$rilevamento): ?>
                        <tr>
                            <td class="text-id"><?= htmlspecialchars($rilevamento['id']) ?></td>
                            <td class="font-mono"><?= date('d/m/Y H:i:s', strtotime($rilevamento['data_ora']) + 7200) ?></td>
                            <td class="text-temp"><?= number_format((float) $rilevamento['temperatura'], 1) ?>°C</td>
                            <td class="text-feels"><?= number_format((float) $rilevamento['percepita'], 1) ?>°C</td>
                            <td class="text-humidity"><?= htmlspecialchars($rilevamento['umidita']) ?>%</td>
                            <td class="capitalize"><?= htmlspecialchars($rilevamento['descrizione']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="empty-message">
                            <p>Nessun rilevamento trovato.</p>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div id="pagination-wrapper">
            <?php if ($rimanenti > 0): ?>
                <div class="pagination-panel">
                    <span class="pagination-info" id="pagination-info">
                        Mostrati <strong id="count-loaded"><?= count($rilevamenti) ?></strong> di <strong id="count-total"><?= $totaleFiltrati ?></strong> risultati
                    </span>
                    <div class="pagination-actions">
                        <button type="button" id="btn-more" class="btn-load-more">Carica altre 100</button>
                        <button type="button" id="btn-all" class="btn-load-all">Carica tutte le rimanenti (<?= $rimanenti ?>)</button>
                    </div>
                </div>
            <?php else: ?>
                <div class="pagination-panel">
                    <span class="pagination-info">
                        Mostrati <strong><?= count($rilevamenti) ?></strong> di <strong><?=$totaleFiltrati ?></strong> risultati (Tutti caricati)
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </section>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const filterForm = document.getElementById('filter-form');
    const tableBody = document.getElementById('table-body');
    const paginationWrapper = document.getElementById('pagination-wrapper');
    const inputLimite = document.getElementById('input-limite');
    const btnReset = document.getElementById('btn-reset');

    let currentLimit = 100;

    const fetchData = async (limite) => {
        currentLimit = limite;
        inputLimite.value = currentLimit;

        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData);
        params.append('ajax', '1');

        try {
            const response = await fetch(`mostraDB.php?${params.toString()}`);
            const data = await response.json();

            if (data.success) {
                tableBody.innerHTML = data.html;
                updatePaginationUI(data);
            }
        } catch (error) {
            console.error('Errore durante il caricamento dei dati:', error);
        }
    };

    const updatePaginationUI = (data) => {
        if (data.rimanenti > 0) {
            paginationWrapper.innerHTML = `
                <div class="pagination-panel">
                    <span class="pagination-info">
                        Mostrati <strong>${data.caricati}</strong> di <strong>${data.totale}</strong> risultati
                    </span>
                    <div class="pagination-actions">
                        <button type="button" id="btn-more" class="btn-load-more">Carica altre 100</button>
                        <button type="button" id="btn-all" class="btn-load-all">Carica tutte le rimanenti (${data.rimanenti})</button>
                    </div>
                </div>
            `;
            attachPaginationEvents();
        } else {
            paginationWrapper.innerHTML = `
                <div class="pagination-panel">
                    <span class="pagination-info">
                        Mostrati <strong>${data.caricati}</strong> di <strong>${data.totale}</strong> risultati (Tutti caricati)
                    </span>
                </div>
            `;
        }
    };

    const attachPaginationEvents = () => {
        const btnMore = document.getElementById('btn-more');
        const btnAll = document.getElementById('btn-all');

        if (btnMore) {
            btnMore.addEventListener('click', () => {
                fetchData(currentLimit + 100);
            });
        }

        if (btnAll) {
            btnAll.addEventListener('click', () => {
                fetchData(0); 
            });
        }
    };

    filterForm.addEventListener('submit', (e) => {
        e.preventDefault();
        fetchData(100);
    });

    btnReset.addEventListener('click', () => {
        filterForm.reset();
        document.getElementById('data_filtro').value = '';
        document.getElementById('temp_cond').value = 'maggiore';
        document.getElementById('temp_val').value = '';
        fetchData(100);
    });

    attachPaginationEvents();
});
</script>

</body>
</html>
