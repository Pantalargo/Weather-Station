<?php
$db_path = '/opt/weather/weather.db';
$all_readings = [];
$error = null;

$date_filter = isset($_GET['data_filtro']) ? $_GET['data_filtro'] : '';
$temp_cond   = isset($_GET['temp_cond']) ? $_GET['temp_cond'] : 'greater';
$temp_val    = (isset($_GET['temp_val']) && $_GET['temp_val'] !== '') ? $_GET['temp_val'] : '';

try {
    $db = new PDO("sqlite:" . $db_path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $conditions = [];
    $params = [];

    if (!empty($date_filter)) {
        $conditions[] = "DATE(data_ora) = :date_filter";
        $params[':date_filter'] = $date_filter;
    }

    if ($temp_val !== '') {
        $operator = ($temp_cond === 'less') ? '<' : '>';
        $conditions[] = "percepita $operator :temp_val";
        $params[':temp_val'] = floatval($temp_val);
    }

    $sql = "SELECT * FROM dati_meteo";
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    $sql .= " ORDER BY data_ora DESC";

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
    <title>Full Database - Weather</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen font-sans">
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-white">Full Database Archive</h1>
            <a href="index.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors border border-gray-600">
                Back to Home
            </a>
        </div>
        
        <div class="bg-gray-800 p-6 rounded-2xl border border-gray-700 mb-6 shadow-lg">
            <form method="GET" action="mostraDB.php" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase mb-2">Date:</label>
                    <input type="date" name="data_filtro" value="<?php echo htmlspecialchars($date_filter); ?>" class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-2 text-white">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase mb-2">Condition:</label>
                    <select name="temp_cond" class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-2 text-white">
                        <option value="greater" <?php echo $temp_cond === 'greater' ? 'selected' : ''; ?>>Greater than (&gt;)</option>
                        <option value="less" <?php echo $temp_cond === 'less' ? 'selected' : ''; ?>>Less than (&lt;)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase mb-2">Feels Like Temp:</label>
                    <input type="number" name="temp_val" step="0.1" value="<?php echo htmlspecialchars($temp_val); ?>" class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-2 text-white">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-sm font-medium">Filter</button>
                    <a href="mostraDB.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-xl text-sm font-medium">Reset</a>
                </div>
            </form>
        </div>

        <section class="bg-gray-800 rounded-2xl p-6 shadow-xl border border-gray-700">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-700 text-xs uppercase text-gray-400">
                            <th class="py-3 px-2">ID</th>
                            <th class="py-3 px-4">Date and Time</th>
                            <th class="py-3 px-4">Temp (°C)</th>
                            <th class="py-3 px-4">Feels Like (°C)</th>
                            <th class="py-3 px-4">Humidity</th>
                            <th class="py-3 px-4">Description</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700 text-sm text-gray-300">
                        <?php foreach ($all_readings as $row): ?>
                            <tr>
                                <td class="py-3 px-2 text-gray-500"><?php echo htmlspecialchars($row['id']); ?></td>
                                <td class="py-3 px-4 font-mono"><?php echo date('d/m/Y H:i:s', strtotime($row['data_ora']) + 7200); ?></td>
                                <td class="py-3 px-4 text-orange-400"><?php echo number_format($row['temperatura'], 1); ?>°C</td>
                                <td class="py-3 px-4 text-red-400"><?php echo number_format($row['percepita'], 1); ?>°C</td>
                                <td class="py-3 px-4 text-blue-400"><?php echo htmlspecialchars($row['umidita']); ?>%</td>
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
