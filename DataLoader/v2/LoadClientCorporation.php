<?php
require "../../vendor/autoload.php";
require "../../config/database.php";
#Models
require "../../Models/ModelClientCorporation.php";
#Entity
use Illuminate\Database\Capsule\Manager as DB;

use jonathanraftery\Bullhorn\Rest\Authentication\Client;
use GuzzleHttp\Client as GuzzleClient;
use Models\ModelClientCorporation;

/**
 * getDataFromSqlServer
 *
 * @return void
 */
function getDataFromSqlServer()
{
    // SELECT
    // T1.JobOrderID,
    // T1.CompanyID,
    // T1.ContactID,
    // T1.JobTitle,
    // T1.JobDescription,
    // T2.FirstName,
    // T2.FullName,
    // T2.LastName
    // FROM JobOrder T1
    // LEFT JOIN Contact T2
    // ON T1.ContactID=T2.ContactID
    // ORDER BY T1.JobOrderID ASC

    $model = (new ModelClientCorporation());

     
    $rows = file(getcwd().'/ClientCorporation_logv2.csv');
    $last_row = array_pop($rows);
    $data = str_getcsv($last_row);
    // print_r($data);exit;
    
    if(!empty($data[0]) && $data[0]!='CompanyID')
    {
        $model = $model->where('CompanyID','>',$data[0]);
    }
    

    $allRows = $model->orderBy('CompanyID','ASC')->get();
    
    print_r("####### Restantes...................: [".$allRows->count()."] ###### \n");

    foreach ($allRows as $row)
    {
        $search = '"'.$row->CompanyID.'","'.$row->Name.'","';
        $cli = "cat ./ClientCorporation_log.txt |grep '".$search."'";
        $prompt = shell_exec($cli);

        if(!empty($prompt))
        {
            print_r("####### ALREADY LOADED #######"."\n");

            $data = explode('","',$prompt);

            $data = array_map(function($e){
                return trim(str_replace('"','',$e));
            }, $data);

            @shell_exec('echo "'.$data[0].'","'.$data[1].'","'.$data[2].'" >> ClientCorporation_logv2.csv');
            continue;
            
        }else{
            print_r("####### NOT LOADED #######"."\n");
        }

        try{

            $bhId = uploadDataToBullhorn($row->Name);
            @shell_exec('echo "'.$row->CompanyID.'","'.$row->Name.'","'.$bhId.'" >> ClientCorporation_logv2.csv');
            
        }catch(Exception $e)
        {
            @shell_exec('echo "'.$row->CompanyID.'","'.$row->Name.'","'.'" >> NotLoadedClientCorporation_log.csv');
        }
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

    $credentialsFileName = __DIR__ . '/../../client-credentials.json';
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

    $client->refreshSession();

    return $data->changedEntityId;
}

getDataFromSqlServer();