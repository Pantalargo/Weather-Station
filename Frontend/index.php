<?php
$db_path = '/opt/weather/weather.db';
$latest_reading = null;
$history_readings = [];
$error = null;

try {
    $db = new PDO("sqlite:" . $db_path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $stmt_latest = $db->query("SELECT * FROM weather_data ORDER BY timestamp DESC LIMIT 1");
    $latest_reading = $stmt_latest->fetch();

    $stmt_history = $db->query("SELECT * FROM weather_data ORDER BY timestamp DESC LIMIT 20");
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
    <title>Weather Station</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen font-sans">
    <div class="container mx-auto px-4 py-8 max-w-5xl">

        <?php if ($error): ?>
            <div class="bg-red-900/50 border border-red-500 text-red-200 p-4 rounded-lg mb-6">
                <p class="font-semibold">Warning:</p>
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($latest_reading): ?>
            <section class="bg-gray-800 rounded-2xl p-6 shadow-xl border border-gray-700 mb-10">
                <div class="flex flex-col md:flex-row justify-between items-center mb-6">
                    <div>
                        <h2 class="text-2xl font-semibold text-white">
                            Latest reading in: <span class="text-blue-400"><?php echo htmlspecialchars($latest_reading['location']); ?></span>
                        </h2>
                        <p class="text-gray-400 text-sm mt-1">
                            Updated at: <?php echo date('d/m/Y H:i:s', strtotime($latest_reading['timestamp']) + 7200); ?>
                        </p>
                    </div>

                    <?php if (!empty($latest_reading['icon_code'])): ?>
                        <div class="flex items-center bg-gray-700/50 px-4 py-2 rounded-xl mt-4 md:mt-0">
                            <img src="https://openweathermap.org/img/wn/<?php echo htmlspecialchars($latest_reading['icon_code']); ?>.png"
                                 alt="<?php echo htmlspecialchars($latest_reading['description']); ?>"
                                 class="w-16 h-16">
                            <span class="text-lg capitalize font-medium text-gray-300 ml-2">
                                <?php echo htmlspecialchars($latest_reading['description']); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div class="bg-gray-700/30 p-4 rounded-xl text-center border border-gray-700">
                        <span class="text-xs text-gray-400 uppercase font-semibold">Temperature</span>
                        <div class="text-2xl font-bold text-orange-400 mt-1"><?php echo number_format($latest_reading['temperature'], 1); ?>°C</div>
                    </div>
                    <div class="bg-gray-700/30 p-4 rounded-xl text-center border border-gray-700">
                        <span class="text-xs text-gray-400 uppercase font-semibold">Feels Like</span>
                        <div class="text-2xl font-bold text-red-400 mt-1"><?php echo number_format($latest_reading['feels_like'], 1); ?>°C</div>
                    </div>
                    <div class="bg-gray-700/30 p-4 rounded-xl text-center border border-gray-700">
                        <span class="text-xs text-gray-400 uppercase font-semibold">Humidity</span>
                        <div class="text-2xl font-bold text-blue-400 mt-1"><?php echo htmlspecialchars($latest_reading['humidity']); ?>%</div>
                    </div>
                    <div class="bg-gray-700/30 p-4 rounded-xl text-center border border-gray-700">
                        <span class="text-xs text-gray-400 uppercase font-semibold">Pressure</span>
                        <div class="text-2xl font-bold text-emerald-400 mt-1"><?php echo htmlspecialchars($latest_reading['pressure']); ?> hPa</div>
                    </div>
                    <div class="bg-gray-700/30 p-4 rounded-xl text-center border border-gray-700 col-span-2 md:col-span-1">
                        <span class="text-xs text-gray-400 uppercase font-semibold">Wind</span>
                        <div class="text-2xl font-bold text-purple-400 mt-1"><?php echo number_format($latest_reading['wind_speed'], 1); ?> m/s</div>
                    </div>
                </div>
            </section>
        <?php else: ?>
            <?php if (!$error): ?>
                <div class="bg-gray-800 p-6 rounded-xl text-center border border-gray-700 mb-10">
                    <p class="text-gray-400">No data found in the database. Please check the backend script.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <section class="bg-gray-800 rounded-2xl p-6 shadow-xl border border-gray-700">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-white">Last 20 Readings</h3>
                <a href="database.php" class="text-sm text-blue-400 hover:text-blue-300 transition-colors">View all data &rarr;</a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-700 text-xs uppercase text-gray-400">
                            <th class="py-3 px-4">Date & Time</th>
                            <th class="py-3 px-4">Location</th>
                            <th class="py-3 px-4">Temp (°C)</th>
                            <th class="py-3 px-4">Humidity</th>
                            <th class="py-3 px-4">Pressure</th>
                            <th class="py-3 px-4">Wind</th>
                            <th class="py-3 px-4">Conditions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700 text-sm text-gray-300">
                        <?php foreach ($history_readings as $row): ?>
                            <tr class="hover:bg-gray-700/30 transition-colors">
                                <td class="py-3 px-4 whitespace-nowrap font-mono text-gray-400">
                                    <?php echo date('d/m/Y H:i', strtotime($row['timestamp']) + 7200); ?>
                                </td>
                                <td class="py-3 px-4"><?php echo htmlspecialchars($row['location']); ?></td>
                                <td class="py-3 px-4 font-semibold text-orange-400"><?php echo number_format($row['temperature'], 1); ?>°</td>
                                <td class="py-3 px-4 text-blue-400"><?php echo htmlspecialchars($row['humidity']); ?>%</td>
                                <td class="py-3 px-4 text-emerald-400"><?php echo htmlspecialchars($row['pressure']); ?> hPa</td>
                                <td class="py-3 px-4 text-purple-400"><?php echo number_format($row['wind_speed'], 1); ?> m/s</td>
                                <td class="py-3 px-4 flex items-center capitalize whitespace-nowrap">
                                    <?php if (!empty($row['icon_code'])): ?>
                                        <img src="https://openweathermap.org/img/wn/<?php echo htmlspecialchars($row['icon_code']); ?>.png" 
                                             alt="icon" class="w-8 h-8 mr-1">
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($row['description']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</body>
</html>
