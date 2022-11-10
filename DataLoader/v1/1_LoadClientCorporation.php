<?php
require "../vendor/autoload.php";
require "../config/database.php";

#Models
require "../Models/ModelClientCorporation.php";

#Entity
use Illuminate\Database\Capsule\Manager as DB;
use Models\ModelClientCorporation;
use jonathanraftery\Bullhorn\Rest\Authentication\Client;
use GuzzleHttp\Client as GuzzleClient;

/**
 * getDataFromSqlServer
 *
 * @return void
 */
function getDataFromSqlServer()
{
    // echo __DIR__, ' | ', getcwd();exit;
    $model = new ModelClientCorporation();

    $rows = file(getcwd().'/ClientCorporation_log.txt');
    $last_row = array_pop($rows);
    $data = str_getcsv($last_row);

    if($last_row)
    {
        $latestId = $data[0];
        $allRows = $model::where('CompanyID','>',$latestId)->get();
    }else{
        $latestId = 0;
        $allRows = $model::all();
    }

    foreach($allRows as $row)
    {
        $bhId = uploadDataToBullhorn($row->Name);

        @shell_exec('echo "'.$row->CompanyID.'","'.$row->Name.'","'.$bhId.'" >> ClientCorporation_log.txt');
        sleep(3);
    }
}

/**
 * uploadDataToBullhorn
 *
 * @param  mixed $data
 * @return void
 */
function uploadDataToBullhorn(string $companyName) : int
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

    $requestBody = [
        "name" => $companyName
    ];
    
    $response = $httpClient->request('PUT', 'entity/ClientCorporation',
        [
            'json' => $requestBody
        ]
    );

    $data = json_decode($response->getBody());

    return $data->changedEntityId;
}

getDataFromSqlServer();