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


$httpClient = new GuzzleClient([
    'base_uri' => $client->getRestUrl(),
    'headers' => [
        'BhRestToken' => $client->getRestToken(),
    ],
]);

// make request 

// mass update
// $response = $httpClient->request('GET', 'massUpdate');

// Candidate

// request body



$response = $httpClient->request('PUT', 'entity/JobOrder',
    [
        
        'json' => [
            "clientContact" => [
                "id" => 16969,
            ],
            "clientCorporation" => [
                "id" => 495,
            ],
            "title" => "Dated Job",
            "description" => "Free Description of Job Order",
            "employmentType" => "Temporary",
            "isOpen" => true,
            "status" => "Accepting Candidates",
            "salaryUnit" => "per month",
            "startDate" => strtotime("2020-01-16 23:59:59") * 1000
        ]
    ]
);


// $response = $httpClient->request('GET', 'entity/Candidate/93?fields=id,firstName,middleName,lastName,status');

// return json
echo $response->getBody()->getContents();
exit;


// https://rest34.bullhornstaffing.com/rest-services/9r1i90/massUpdate?BhRestToken=86334f0e-d42f-416b-b4cb-026caa03d6df -> URL de Example como consumir logeado