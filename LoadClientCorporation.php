<?php
include 'vendor/autoload.php';

use jonathanraftery\Bullhorn\Rest\Authentication\Client;
use GuzzleHttp\Client as GuzzleClient;


$credentialsFileName = __DIR__ . '/client-credentials.json';
$credentialsFile = fopen($credentialsFileName, 'r');
$credentialsJson = fread($credentialsFile, filesize($credentialsFileName));
$credentials = json_decode($credentialsJson);

$client = new Client(
    $credentials->clientId,
    $credentials->clientSecret
);
$client->initiateSession(
    $credentials->username,
    $credentials->password,
    ['ttl' => 1]
);

// echo "<pre>";
// print_r(json_encode([
//     "BhRestToken" => $client->getRestToken(),
//     "restUrl" => $client->getRestUrl(),
//     ]));
// exit;


$httpClient = new GuzzleClient([
    'base_uri' => $client->getRestUrl(),
    'headers' => [
        'BhRestToken' => $client->getRestToken(),
    ],
]);

// make request 

// mass update
$response = $httpClient->request('GET', 'massUpdate');

// Candidate

// request body
$requestBody = [
    "firstName" => "Jonah",
    "middleName" => "R",
    "lastName" => "Hill",
    "email" => "jhill@test.com",
    "status" => "02-Active"
];



// $response = $httpClient->request('PUT', 'entity/Candidate',
//     [
//         'json' => $requestBody
//     ]
// );

// myCandidates
// $response = $httpClient->request('GET', 'entity/Candidate/78/tasks?fields=*');


// return json
echo $response->getBody();
exit;


// https://rest34.bullhornstaffing.com/rest-services/9r1i90/massUpdate?BhRestToken=86334f0e-d42f-416b-b4cb-026caa03d6df -> URL de Example como consumir logeado