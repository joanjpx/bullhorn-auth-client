<?php
include 'vendor/autoload.php';

use jonathanraftery\Bullhorn\Rest\Authentication\Client;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Utils;

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

$response = $httpClient->request(
    'PUT',
    'file/Candidate/455/raw?&externalID=-1',
    [
        'multipart' => [
            [
                'name' => 'Certificado Laboral - Gian.pdf',
                'contents' => Utils::tryFopen('C:/Certificado Laboral - Gian.pdf', 'r'),
                'headers'  => [
                    'Content-Type' => '<Content-type header>'
                ]
            ]
        ]
    ]
);

echo $response->getBody();
exit;