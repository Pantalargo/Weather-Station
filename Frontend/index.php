<?php

$dbPath = '/opt/weather/weather.db';
$jsonForecastPath = '/opt/weather/forecast.json';

$ultimoRilevamento = null;
$storico = [];
$previsioni = null;
$errore = null;

function getWmoIconAndDesc($code) {
    $codes = [
        0 => ['Cielo sereno', '01d'],
        1 => ['Prevalentemente sereno', '02d'],
        2 => ['Parzialmente nuvoloso', '03d'],
        3 => ['Nuvoloso', '04d'],
        45 => ['Nebbia', '50d'], 48 => ['Nebbia', '50d'],
        51 => ['Pioviggine', '09d'], 53 => ['Pioviggine', '09d'], 55 => ['Pioviggine', '09d'],
        61 => ['Pioggia debole', '10d'], 63 => ['Pioggia', '10d'], 65 => ['Pioggia forte', '10d'],
        71 => ['Neve', '13d'], 73 => ['Neve', '13d'], 75 => ['Neve forte', '13d'],
        80 => ['Acquazzone', '09d'], 81 => ['Acquazzone', '09d'], 82 => ['Acquazzone forte', '09d'],
        95 => ['Temporale', '11d'], 96 => ['Temporale', '11d'], 99 => ['Temporale', '11d']
    ];
    return $codes[$code] ?? ['Sconosciuto', '03d'];
}

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $query = $db->query('SELECT * FROM dati_meteo ORDER BY data_ora DESC LIMIT 20');
    $storico = $query->fetchAll();

    if (!empty($storico)) {
        $ultimoRilevamento = $storico[0];
    }
} catch (PDOException $e) {
    $errore = 'Errore DB: ' . $e->getMessage();
}

if (file_exists($jsonForecastPath)) {
    $rawJson = file_get_contents($jsonForecastPath);
    $previsioni = json_decode($rawJson, true);
}

function dataMeteo($data) {
    return date('d/m/Y H:i', strtotime($data) + 7200);
}

function valore($dato, $decimali = 1) {
    return number_format((float) $dato, $decimali);
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stazione Meteo</title>
    <link rel="stylesheet" href="stile.css?v=<?= time() ?>">
</head>
<body>

<div class="container">

    <header class="header">
        <h1>Stazione Meteo</h1>
        <a href="mostraDB.php" class="btn-archive">Archivio Dati &rarr;</a>
    </header>

    <?php if ($errore): ?>
        <div class="error-message">
            <p class="error-title">Impossibile caricare i dati</p>
            <p class="error-text"><?= htmlspecialchars($errore) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($ultimoRilevamento): ?>
        <section class="glass-panel weather-widget">
            <h2 class="city-name"><?= htmlspecialchars($ultimoRilevamento['localita']) ?></h2>
            <p class="update-time">Rilevato il <?= dataMeteo($ultimoRilevamento['data_ora']) ?></p>

            <div class="current-weather">
                <div class="temp-main"><?= valore($ultimoRilevamento['temperatura']) ?>&deg;</div>
                
                <?php if (!empty($ultimoRilevamento['icona_codice'])): ?>
                    <div class="condition">
                        <img 
                            src="https://openweathermap.org/img/wn/<?= htmlspecialchars($ultimoRilevamento['icona_codice']) ?>@2x.png" 
                            alt="<?= htmlspecialchars($ultimoRilevamento['descrizione']) ?>" 
                            class="weather-icon-placeholder"
                        >
                        <span class="condition-text"><?= htmlspecialchars($ultimoRilevamento['descrizione']) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="weather-details-grid">
                <div class="detail-item">
                    <span class="detail-label">Percepita</span>
                    <span class="detail-value"><?= valore($ultimoRilevamento['percepita']) ?>&deg;</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Umidità</span>
                    <span class="detail-value"><?= htmlspecialchars($ultimoRilevamento['umidita']) ?>%</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Pressione</span>
                    <span class="detail-value"><?= htmlspecialchars($ultimoRilevamento['pressione']) ?> <span class="unit">hPa</span></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Vento</span>
                    <span class="detail-value"><?= valore($ultimoRilevamento['velocita_vento']) ?> <span class="unit">m/s</span></span>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if (isset($previsioni['hourly'])): ?>
        <section class="hourly-section">
            <h3>Previsioni prossime 24 ore</h3>
            <div class="hourly-slider">
                <?php 
                $hourly = $previsioni['hourly'];
                $oraAttuale = date('Y-m-d\TH:00'); 
                $startIndex = array_search($oraAttuale, $hourly['time']);
                if ($startIndex === false) {
                    $startIndex = 0;
                }

                $fineIndex = min($startIndex + 24, count($hourly['time']));
                for ($i = $startIndex; $i < $fineIndex; $i++): 
                    $oraFormatted = date('H:i', strtotime($hourly['time'][$i]));
                    $code = $hourly['weather_code'][$i];
                    list($desc, $icon) = getWmoIconAndDesc($code);
                    $temp = round($hourly['temperature_2m'][$i]);
                    $probRain = $hourly['precipitation_probability'][$i] ?? 0;
                ?>
                    <div class="glass-panel hourly-card">
                        <span class="hourly-time"><?= ($i === $startIndex) ? 'Ora' : $oraFormatted ?></span>
                        <img src="https://openweathermap.org/img/wn/<?= $icon ?>.png" alt="<?= $desc ?>" class="hourly-icon" title="<?= $desc ?>">
                        <span class="hourly-temp"><?= $temp ?>°</span>
                        <?php if ($probRain > 0): ?>
                            <span class="hourly-rain">☔ <?= $probRain ?>%</span>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (isset($previsioni['daily'])): ?>
        <section class="forecast-section">
            <h3>Previsioni per i prossimi giorni</h3>
            <div class="forecast-grid">
                <?php 
                $daily = $previsioni['daily'];
                for ($i = 0; $i < count($daily['time']); $i++): 
                    $dataGiorno = date('d/m', strtotime($daily['time'][$i]));
                    $giornoSettimana = date('D', strtotime($daily['time'][$i]));
                    $code = $daily['weather_code'][$i];
                    list($desc, $icon) = getWmoIconAndDesc($code);
                    $tMax = round($daily['temperature_2m_max'][$i]);
                    $tMin = round($daily['temperature_2m_min'][$i]);
                    $probRain = $daily['precipitation_probability_max'][$i] ?? 0;
                ?>
                    <div class="glass-panel forecast-card">
                        <span class="forecast-day"><?= $giornoSettimana ?> <?= $dataGiorno ?></span>
                        <img src="https://openweathermap.org/img/wn/<?= $icon ?>.png" alt="<?= $desc ?>" class="forecast-icon">
                        <span class="forecast-desc"><?= $desc ?></span>
                        <div class="forecast-temp">
                            <span class="temp-max"><?= $tMax ?>°</span>
                            <span class="temp-min"><?= $tMin ?>°</span>
                        </div>
                        <?php if ($probRain > 0): ?>
                            <span class="forecast-rain">☔ <?= $probRain ?>%</span>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="history-section">
        <h3>Tendenze recenti (Archivio DB)</h3>
        <?php if (!empty($storico)): ?>
            <div class="glass-panel table-container">
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Orario</th>
                                <th>Temp</th>
                                <th>Umidità</th>
                                <th>Vento</th>
                                <th>Condizioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($storico as $rilevamento): ?>
                                <tr>
                                    <td class="fw-medium"><?= dataMeteo($rilevamento['data_ora']) ?></td>
                                    <td><?= valore($rilevamento['temperatura']) ?>&deg;</td>
                                    <td><?= htmlspecialchars($rilevamento['umidita']) ?>%</td>
                                    <td><?= valore($rilevamento['velocita_vento']) ?> m/s</td>
                                    <td class="capitalize"><?= htmlspecialchars($rilevamento['descrizione']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <footer style="text-align: center; opacity: 0.7; font-size: 13px; margin-top: 30px; margin-bottom: 20px;">
        Dati meteo forniti da <a href="https://open-meteo.com/" target="_blank" rel="noopener noreferrer" style="color: #fff; text-decoration: underline;">Open-Meteo</a>
    </footer>

</div>

</body>
</html>
