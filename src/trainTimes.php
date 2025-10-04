<?php
header('Content-Type: application/json');

function convertString($str) {
    if (strlen($str) !== 4) {
        return "Invalid input";
    }
    $firstPart = substr($str, 0, 2);
    $secondPart = substr($str, 2);
    return "{$firstPart}:{$secondPart}";
}

function getTimeString($basicTime, $tomorrow, $today, $timezoneOffsetString) {
    $day = "";
    if (substr($basicTime, 0, 2) < 3) {
        $day = $tomorrow;
    } else {
        $day = $today;
    }
    return "{$day}T{$basicTime}:00{$timezoneOffsetString}";
}

$from = 'WAT';
$to = 'SUR';

$now = new DateTime();
$hours = $now->format('H');

if ($hours < 12) {
    $from = 'SUR';
    $to = 'WAT';
} else {
    $from = 'WAT';
    $to = 'SUR';
}

if (isset($_GET['from'])) {
    $from = $_GET['from'];
}
if (isset($_GET['to'])) {
    $to = $_GET['to'];
}

$route = "{$from}/to/{$to}";

// Get Train Times
$trainTimesUrl = "https://huxley2.azurewebsites.net/departures/{$route}?expand=true";
$trainTimesResponse = @file_get_contents($trainTimesUrl);
if ($trainTimesResponse === false) {
    http_response_code(502); // Bad Gateway
    echo json_encode(['error' => 'Could not retrieve train times from the Huxley API.']);
    exit;
}

$trainTimesData = json_decode($trainTimesResponse, true) ?? [];

$trains = [];

if (isset($trainTimesData['trainServices'])) {
    $timezone = new DateTimeZone('Europe/London');
    $nowUtc = new DateTime('now', $timezone);
    $timezoneOffset = $timezone->getOffset($nowUtc) / 3600;
    $timezoneOffsetString = sprintf('%s%02d:00', $timezoneOffset > 0 ? '+' : '-', abs($timezoneOffset));
    $today = (new DateTime('now', $timezone))->format('Y-m-d');
    $tomorrow = (new DateTime('tomorrow', $timezone))->format('Y-m-d');

    foreach ($trainTimesData['trainServices'] as $trainService) {
        if (isset($trainService['destination'][0]) && isset($trainService['subsequentCallingPoints'][0])) {
            $surStation = null;
            foreach ($trainService['subsequentCallingPoints'][0]['callingPoint'] as $callingPoint) {
                if ($callingPoint['crs'] === $to) {
                    $surStation = $callingPoint;
                    break;
                }
            }

            if ($surStation) {
                $std = $trainService['std'];
                $etd = $trainService['etd'];
                $actualDepartureString = ($etd !== 'On time' && $etd !== 'Delayed' && $etd !== 'Cancelled') ? $etd : $std;
                $actualDepartureTime = new DateTime(getTimeString($actualDepartureString, $tomorrow, $today, $timezoneOffsetString));

                $st = $surStation['st'];
                $et = $surStation['et'];
                $estimatedArrivalString = ($et !== 'On time' && $et !== 'Delayed' && $et !== 'Cancelled') ? $et : $st;
                $estimatedArrivalTime = new DateTime(getTimeString($estimatedArrivalString, $tomorrow, $today, $timezoneOffsetString));

                $journeyTime = ($estimatedArrivalTime->getTimestamp() - $actualDepartureTime->getTimestamp()) / 60;

                $train = [
                    'destination' => $trainService['destination'][0]['locationName'],
                    'scheduledDepartureString' => $std,
                    'actualDeparture' => $etd,
                    'platform' => $trainService['platform'],
                    'scheduledArrival' => $st,
                    'estimatedArrival' => $et,
                    'journeyTime' => $journeyTime,
                    'actualDepartureTimestamp' => $actualDepartureTime->getTimestamp() * 1000,
                    'estimatedArrivalTimestamp' => $estimatedArrivalTime->getTimestamp() * 1000,
                    'platformConfirmed' => null,
                    'platformChanged' => null,
                ];
                $trains[] = $train;
            }
        }
    }
}

// Get Platform Data
$platformUrl = "http://localhost/trainTimesServer.php?route={$route}"; // Assuming trainTimesServer.php is on the same server
@$platformResponse = file_get_contents($platformUrl);
if ($platformResponse !== false) {
    $platformData = json_decode($platformResponse, true);

    if (isset($platformData['services'])) {
        foreach ($platformData['services'] as $service) {
            $locationDetail = $service['locationDetail'];
            $gbttBookedDeparture = $locationDetail['gbttBookedDeparture'];
            $platformTrain = [
                'destination' => $locationDetail['destination'][0]['description'],
                'scheduledDepartureString' => convertString($gbttBookedDeparture),
                'platform' => $locationDetail['platform'],
                'platformConfirmed' => $locationDetail['platformConfirmed'],
                'platformChanged' => $locationDetail['platformChanged'],
            ];

            foreach ($trains as $i => $train) {
                if ($train['destination'] === $platformTrain['destination'] && $train['scheduledDepartureString'] === $platformTrain['scheduledDepartureString']) {
                    $trains[$i]['platform'] = $platformTrain['platform'];
                    $trains[$i]['platformConfirmed'] = $platformTrain['platformConfirmed'];
                    $trains[$i]['platformChanged'] = $platformTrain['platformChanged'];
                    break;
                }
            }
        }
    }
}

// Sort trains by estimated arrival time
usort($trains, function ($a, $b) {
    return $a['estimatedArrivalTimestamp'] - $b['estimatedArrivalTimestamp'];
});


$response_data = [
    'from' => $from,
    'to' => $to,
    'trains' => $trains
];

echo json_encode($response_data);

?>