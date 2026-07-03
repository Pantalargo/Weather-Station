<?php
$db_path = '/opt/weather/weather.db';
$all_readings = [];
$error = null;

$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';
$temp_cond   = isset($_GET['temp_cond']) ? $_GET['temp_cond'] : 'greater';
$temp_val    = (isset($_GET['temp_val']) && $_GET['temp_val'] !== '') ? $_GET['temp_val'] : '';

try {
    $db = new PDO("sqlite:" . $db_path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $conditions = [];
    $params = [];

    if (!empty($date_filter)) {
        $conditions[] = "DATE(timestamp) = :date_filter";
        $params[':date_filter'] = $date_filter;
    }

    if ($temp_val !== '') {
        $operator = ($temp_cond === 'less') ? '<' : '>';
        $conditions[] = "feels_like $operator :temp_val";
        $params[':temp_val'] = floatval($temp_val);
    }

    $sql = "SELECT * FROM weather_data";
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    $sql .= " ORDER BY timestamp DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $all_readings = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Database connection error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Database - Weather</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen font-sans">
    <div class="container mx-auto px-4 py-8 max-w-6xl">

        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Total Database Archive</h1>
                <p class="text-gray-400 text-sm mt-1">Advanced filtering and data visualization.</p>
            </div>
            <a href="index.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors border border-gray-600">
                Back to Home
            </a>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-900/50 border border-red-500 text-red-200 p-4 rounded-lg mb-6">
                <p class="font-semibold">Error:</p>
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>

        <div class="bg-gray-800 p-6 rounded-2xl border border-gray-700 mb-6 shadow-lg">
            <form method="GET" action="database.php" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label for="date_filter" class="block text-xs font-semibold text-gray-400 uppercase mb-2">Filter by date:</label>
                    <input type="date" id="date_filter" name="date_filter" value="<?php echo htmlspecialchars($date_filter); ?>" class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-blue-500 font-mono">
                </div>
                <div>
                    <label for="temp_cond" class="block text-xs font-semibold text-gray-400 uppercase mb-2">Temp Condition:</label>
                    <select id="temp_cond" name="temp_cond" class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                        <option value="greater" <?php echo $temp_cond === 'greater' ? 'selected' : ''; ?>>Greater than (&gt;)</option>
                        <option value="less" <?php echo $temp_cond === 'less' ? 'selected' : ''; ?>>Less than (&lt;)</option>
                    </select>
                </div>
                <div>
                    <label for="temp_val" class="block text-xs font-semibold text-gray-400 uppercase mb-2">Feels Like (°C):</label>
                    <input type="number" id="temp_val" name="temp_val" step="0.1" placeholder="e.g. 25.0" value="<?php echo htmlspecialchars($temp_val); ?>" class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors shadow-lg shadow-blue-600/20">Apply</button>
                    <?php if (!empty($date_filter) || $temp_val !== ''): ?>
                        <a href="database.php" class="bg-gray-700 hover:bg-gray-600 text-center text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors border border-gray-600 flex items-center justify-center">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <section class="bg-gray-800 rounded-2xl p-6 shadow-xl border border-gray-700">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-700 text-xs uppercase text-gray-400">
                            <th class="py-3 px-2 text-center">ID</th>
                            <th class="py-3 px-4">Date & Time</th>
                            <th class="py-3 px-4">Location</th>
                            <th class="py-3 px-4">Temp (°C)</th>
                            <th class="py-3 px-4">Feels Like</th>
                            <th class="py-3 px-4">Humidity</th>
                            <th class="py-3 px-4">Pressure</th>
                            <th class="py-3 px-4">Wind</th>
                            <th class="py-3 px-4">Description</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700 text-sm text-gray-300">
                        <?php if (count($all_readings) > 0): ?>
                            <?php foreach ($all_readings as $row): ?>
                                <tr class="hover:bg-gray-700/30 transition-colors">
                                    <td class="py-3 px-2 text-center font-mono text-gray-500 text-xs"><?php echo htmlspecialchars($row['id']); ?></td>
                                    <td class="py-3 px-4 whitespace-nowrap font-mono text-gray-400">
                                        <?php echo date('d/m/Y H:i:s', strtotime($row['timestamp']) + 7200); ?>
                                    </td>
                                    <td class="py-3 px-4 font-medium text-white"><?php echo htmlspecialchars($row['location']); ?></td>
                                    <td class="py-3 px-4 font-semibold text-orange-400"><?php echo number_format($row['temperature'], 1); ?>°C</td>
                                    <td class="py-3 px-4 font-semibold text-red-400"><?php echo number_format($row['feels_like'], 1); ?>°C</td>
                                    <td class="py-3 px-4 text-blue-400"><?php echo htmlspecialchars($row['humidity']); ?>%</td>
                                    <td class="py-3 px-4 text-emerald-400"><?php echo htmlspecialchars($row['pressure']); ?> hPa</td>
                                    <td class="py-3 px-4 text-purple-400"><?php echo number_format($row['wind_speed'], 1); ?> m/s</td>
                                    <td class="py-3 px-4 capitalize text-gray-300"><?php echo htmlspecialchars($row['description']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="py-8 text-center text-gray-500">No records found matching the active filters.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</body>
</html>
