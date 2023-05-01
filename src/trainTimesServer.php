<?php
$route = $_GET['route'];
$url = "https://api.rtt.io/api/v1/json/search/$route";
$username = getenv('USERNAME');
$password = getenv('PASSWORD');



$authString = "$username:$password";
$encodedAuthString = base64_encode($authString); // Encode the auth string using Base64

$options = array(
    'http' => array(
        'method' => 'GET',
        'header' => "Authorization: Basic $encodedAuthString\r\n" 
      )
    );

$context = stream_context_create($options);
$response = file_get_contents($url, false, $context);

if ($response === false) {
  echo "Error fetching data from Realtime Trains API " . error_get_last()['message'];
} else {
    echo $response;
}
?>
