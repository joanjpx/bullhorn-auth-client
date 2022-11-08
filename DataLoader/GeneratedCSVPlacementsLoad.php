<?php
require "../vendor/autoload.php";
require "../config/database.php";
#Models
require "../Models/ModelJobOrder.php";
require "../Models/ModelPlacement.php";
#Entity
use Illuminate\Database\Capsule\Manager as DB;

use jonathanraftery\Bullhorn\Rest\Authentication\Client;
use GuzzleHttp\Client as GuzzleClient;
use Models\ModelJobOrder;
use Models\ModelPlacement;



function readDataFromCsv()
{

    $file = fopen("./JobPlacement2Log.csv","r");
    $fileToWrite = fopen("./JobPlacement_log.txt","w");
            
    while($csv = fgetcsv($file))
    {
        if($csv[0]!='MSSQL_JobPlacementID')
        {
            $candidateId = intval($csv[5]);
            $jobOrderId = intval($csv[6]);

            $csv[7] = LoadToBullhornAndGetId($candidateId, $jobOrderId, $csv[8], $csv[9]);

            fputcsv($fileToWrite, $csv);

        }
    }
}

/**
 * uploadDataToBullhorn
 *
 * @param  mixed $data
 * @return void
 */
function LoadToBullhornAndGetId($candidateId, $jobOrderId, $dateCreated, $startDate) : int
{

    $credentialsFileName = __DIR__ . '/../client-credentials.json';
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
    
    $body = [
        // "id" => null,
        "candidate" => [
            "id" => intval($candidateId),
        ],
        "clientBillRate" => null,
        "clientCorporation" => [
            "id" => 7
        ],
        // "dateAdded" => 1660054929560,
        // "dateBegin" => 1660054929560,
        "dateEnd" => null,
        "employmentType" => "Direct Hire",
        "jobOrder" => [
            'id' => intval($jobOrderId)
        ],
        "payRate" => 0,
        "reportTo" => null,
        "salary" => 0,
        "status" => "Submitted",
        "bteSyncStatus" => ""
    ];

    var_dump($body);

    $response = $httpClient->request('PUT', 'entity/Placement',
        [
            'json' => $body
        ]
    );

    $data = json_decode($response->getBody()->getContents());

    print_r($data);

    $client->refreshSession();
    
    return $data->changedEntityId;
}



readDataFromCsv();