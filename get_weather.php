<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'db_config.php';

define('OPENWEATHER_API_KEY', '9477ed8eda5d46a0ad74324cfadc20c5'); // your key

$city = isset($_GET['city']) ? trim($_GET['city']) : '';
if (empty($city)) {
    echo json_encode(['error' => 'City name is required']);
    exit;
}

// 1. Check cache (valid for 10 minutes)
$stmt = $pdo->prepare("SELECT data FROM weather_cache WHERE city = ? AND timestamp > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
$stmt->execute([$city]);
$cached = $stmt->fetch(PDO::FETCH_ASSOC);

if ($cached) {
    echo $cached['data'];
    logSearch($city, $pdo);
    exit;
}

// 2. Fetch from OpenWeatherMap
$url = "https://api.openweathermap.org/data/2.5/weather?q=" . urlencode($city) . "&appid=" . OPENWEATHER_API_KEY . "&units=metric";
$response = @file_get_contents($url);

if ($response === false) {
    echo json_encode(['error' => 'Unable to fetch weather data']);
    exit;
}

$data = json_decode($response, true);
if (isset($data['cod']) && $data['cod'] != 200) {
    echo json_encode(['error' => $data['message'] ?? 'City not found']);
    exit;
}

// 3. Store in cache
$stmt = $pdo->prepare("REPLACE INTO weather_cache (city, data, timestamp) VALUES (?, ?, NOW())");
$stmt->execute([$city, $response]);

// 4. Log search
logSearch($city, $pdo);

echo $response;

function logSearch($city, $pdo) {
    session_start();
    $sessionId = session_id();
    $stmt = $pdo->prepare("INSERT INTO search_history (city, session_id, searched_at) VALUES (?, ?, NOW())");
    $stmt->execute([$city, $sessionId]);
}
?>
