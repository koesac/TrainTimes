<?php
header('Content-Type: text/plain');

// Get the 'from' and 'to' parameters from the query string, defaulting if not set
$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;

// Build the URL for trainTimes.php
// This assumes trainTimes.php is accessible on the same server.
// You might need to adjust 'localhost' to your server's domain name if running on a different virtual host.
$url = 'http://localhost/trainTimes.php';
$queryParams = [];
if ($from) {
    $queryParams['from'] = $from;
}
if ($to) {
    $queryParams['to'] = $to;
}
if (!empty($queryParams)) {
    $url .= '?' . http_build_query($queryParams);
}

// Fetch the JSON data from the existing trainTimes.php endpoint
$jsonData = @file_get_contents($url);

if ($jsonData === false) {
    http_response_code(500);
    echo "Error: Could not fetch train data.";
    exit;
}

// Decode the JSON
$data = json_decode($jsonData, true);

if (!$data || !isset($data['trains'])) {
    http_response_code(500);
    echo "Error: Invalid train data format.";
    exit;
}

//$responseText = "{$data['from']} to {$data['to']}\n\n";
$responseText = '';

if (empty($data['trains'])) {
    $responseText .= "No trains found.";
} else {
    foreach ($data['trains'] as $train) {
        $departure = $train['scheduledDepartureString'];
        if ($train['actualDeparture'] !== 'On time' && $train['actualDeparture'] !== 'Delayed') {
            $departure = "!{$train['actualDeparture']}";
        }

        $arrival = $train['scheduledArrival'];
        if ($train['estimatedArrival'] !== 'On time' && $train['estimatedArrival'] !== 'Delayed') {
            $arrival .= " ({$train['estimatedArrival']})";
        }

        $minsUntilDeparture = floor(($train['actualDepartureTimestamp'] - (new DateTime())->getTimestamp() * 1000) / (1000 * 60));

        $duration = round($train['journeyTime']);

        $responseText .= "{$departure}";
        $responseText .= ($train['platform'] ? " p{$train['platform']}" : "");
        $responseText .= " {$duration}m";
        $responseText .= "\n";
    }
}

echo rtrim($responseText);
?>
