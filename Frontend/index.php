<?php
$db_path = '/opt/weather/weather.db'; // Sqlite3 database path
$last_reading = null;
$history_readings = [];
$error = null;

try {
    $db = new PDO("sqlite:" . $db_path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $stmt_last = $db->query("SELECT * FROM dati_meteo ORDER BY data_ora DESC LIMIT 1");
    $last_reading = $stmt_last->fetch();

    $stmt_history = $db->query("SELECT * FROM dati_meteo ORDER BY data_ora DESC LIMIT 20");
    $history_readings = $stmt_history->fetchAll();

} catch (PDOException $e) {
    $error = "Database connection error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weather</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen font-sans">
    <div class="container mx-auto px-4 py-8 max-w-5xl">
        
        <div class="flex justify-end mb-6">
            <a href="mostraDB.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-xl text-sm font-medium transition-colors border border-blue-500 shadow-lg">
                View Full Database
            </a>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-900/50 border border-red-500 text-red-200 p-4 rounded-lg mb-6">
                <p class="font-semibold">Warning:</p>
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($last_reading): ?>
            <section class="bg-gray-800 rounded-2xl p-6 shadow-xl border border-gray-700 mb-10">
                <div class="flex flex-col md:flex-row justify-between items-center mb-6">
                    <div>
                        <h2 class="text-2xl font-semibold text-white">
                            Latest reading at: <span class="text-blue-400"><?php echo htmlspecialchars($last_reading['localita']); ?></span>
                        </h2>
                        <p class="text-gray-400 text-sm mt-1">
                            Updated on: <?php echo date('d/m/Y H:i:s', strtotime($last_reading['data_ora']) + 7200); ?>
                        </p>
                    </div>
                    <?php if (!empty($last_reading['icona_codice'])): ?>
                        <div class="flex items-center bg-gray-700/50 px-4 py-2 rounded-xl mt-4 md:mt-0">
                            <img src="https://openweathermap.org/img/wn/<?php echo htmlspecialchars($last_reading['icona_codice']); ?>.png"
                                 alt="<?php echo htmlspecialchars($last_reading['descrizione']); ?>"
                                 class="w-16 h-16">
                            <span class="text-lg capitalize font-medium text-gray-300 ml-2">
                                <?php echo htmlspecialchars($last_reading['descrizione']); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div class="bg-gray-700/30 p-4 rounded-xl text-center border border-gray-700">
                        <span class="text-xs text-gray-400 uppercase font-semibold">Temperature</span>
                        <div class="text-2xl font-bold text-orange-400 mt-1"><?php echo number_format($last_reading['temperatura'], 1); ?>°C</div>
                    </div>
                    <div class="bg-gray-700/30 p-4 rounded-xl text-center border border-gray-700">
                        <span class="text-xs text-gray-400 uppercase font-semibold">Feels Like</span>
                        <div class="text-2xl font-bold text-red-400 mt-1"><?php echo number_format($last_reading['percepita'], 1); ?>°C</div>
                    </div>
                    <div class="bg-gray-700/30 p-4 rounded-xl text-center border border-gray-700">
                        <span class="text-xs text-gray-400 uppercase font-semibold">Humidity</span>
                        <div class="text-2xl font-bold text-blue-400 mt-1"><?php echo htmlspecialchars($last_reading['umidita']); ?>%</div>
                    </div>
                    <div class="bg-gray-700/30 p-4 rounded-xl text-center border border-gray-700">
                        <span class="text-xs text-gray-400 uppercase font-semibold">Pressure</span>
                        <div class="text-2xl font-bold text-emerald-400 mt-1"><?php echo htmlspecialchars($last_reading['pressione']); ?> hPa</div>
                    </div>
                    <div class="bg-gray-700/30 p-4 rounded-xl text-center border border-gray-700 col-span-2 md:col-span-1">
                        <span class="text-xs text-gray-400 uppercase font-semibold">Wind</span>
                        <div class="text-2xl font-bold text-purple-400 mt-1"><?php echo number_format($last_reading['velocita_vento'], 1); ?> m/s</div>
                    </div>
                </div>
            </section>
        <?php else: ?>
            <?php if (!$error): ?>
                <div class="bg-gray-800 p-6 rounded-xl text-center border border-gray-700 mb-10">
                    <p class="text-gray-400">No data available in the database.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <section class="bg-gray-800 rounded-2xl p-6 shadow-xl border border-gray-700">
            <h3 class="text-xl font-semibold text-white mb-4">History of last 20 readings</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-700 text-xs uppercase text-gray-400">
                            <th class="py-3 px-4">Date and Time</th>
                            <th class="py-3 px-4">Location</th>
                            <th class="py-3 px-4">Temp.</th>
                            <th class="py-3 px-4">Humidity</th>
                            <th class="py-3 px-4">Pressure</th>
                            <th class="py-3 px-4">Wind</th>
                            <th class="py-3 px-4">Conditions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700 text-sm text-gray-300">
                        <?php foreach ($history_readings as $row): ?>
                            <tr>
                                <td class="py-3 px-4 font-mono text-gray-400"><?php echo date('d/m/Y H:i', strtotime($row['data_ora']) + 7200); ?></td>
                                <td class="py-3 px-4"><?php echo htmlspecialchars($row['localita']); ?></td>
                                <td class="py-3 px-4 text-orange-400"><?php echo number_format($row['temperatura'], 1); ?>°</td>
                                <td class="py-3 px-4 text-blue-400"><?php echo htmlspecialchars($row['umidita']); ?>%</td>
                                <td class="py-3 px-4 text-emerald-400"><?php echo htmlspecialchars($row['pressione']); ?> hPa</td>
                                <td class="py-3 px-4 text-purple-400"><?php echo number_format($row['velocita_vento'], 1); ?> m/s</td>
                                <td class="py-3 px-4 capitalize"><?php echo htmlspecialchars($row['descrizione']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</body>
</html>
