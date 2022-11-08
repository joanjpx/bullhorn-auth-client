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

/**
 * getDataFromSqlServer
 *
 * @return void
 */
function getDataFromSqlServer()
{
    $model = (new ModelPlacement())
    ->leftJoin(
        "Contact AS T2",
        "T2.ContactID",
        "=",
        "Placement.CandidateContactID"
    )
    ->leftJoin(
        "JobOrder AS T3",
        "T3.JobOrderID",
        "=",
        "Placement.JobOrderID"
    )
    ->whereNotNull('Placement.JobOrderID')
    ->whereNotNull('T3.CompanyID')
    ->orderBy('Placement.PlacementID','ASC');

    // $rows = file(getcwd().'/JobPlacement_log.txt');
    // $last_row = array_pop($rows);
    // $data = str_getcsv($last_row);
    
    // if(!empty($data))
    // {
    //     if($data[0]!='MSSQL_JobSubmissionID' && !empty($data[0]))
    //     {
    //         $model = $model->where('ApplicationID','>',$data[0]);
    //     }
    // }
   
    $allRows = $model->select([
        "Placement.ApplicationID",
        "Placement.CandidateContactID AS ContactID",
        "Placement.JobOrderID",
        "T2.FullName",
        "T3.CompanyID",
        "Placement.DateCreated",
        "Placement.StartDate"
    ])->get();


    print_r("####### Restantes...................: [".$allRows->count()."] ###### \n");
    sleep(3);

    $csvToWrite = fopen("./JobPlacement2Log.csv", "w");
    
    foreach ($allRows as $row)
    {

        $cli = "cat JobPlacement_log.txt |grep "."'".'"' . $row->ApplicationID . '", "' . $row->ContactID . '"'."'";

        $prompt = shell_exec($cli);

        var_dump($prompt);
        
        if(!empty($prompt))
        {
            continue;
        }

        $candidateId = getBullhornCandidateId($row->ContactID);
        $jobOrderId = getBullhornJobOrderID($row->JobOrderID, $row->CompanyID);

        $csvRow = [
            $row->ApplicationID,
            $row->ContactID,
            $row->JobOrderID,
            $row->CompanyID,
            $row->FullName,
            $candidateId,
            $jobOrderId,
            "",
            $row->DateCreated,
            $row->StartDate
        ];

        fputcsv($csvToWrite, $csvRow, ',','"');

        // @shell_exec('echo "'.$row->ApplicationID.'", "'.$row->ContactID.'", "'.$row->JobOrderID.'", "'.$row->CompanyID.'", "'.$row->FullName.'", "'.$candidateId.'", "'.$jobOrderId.'", "'.'" >> JobPlacement2_log.txt');

    }
}



function getBullhornCandidateId(int $mssqlId)
{
    $rows = fopen(getcwd().'/Candidate_log.txt','r');
        
    while (($line = fgetcsv($rows,0,',','"')) !== FALSE) 
    {
        if($line[0]==$mssqlId) return $line[2]; 
    }

    fclose($rows);
    
    return null;
}




function getBullhornJobOrderID(?string $msJobOrderID, ?string $msCompanyID) : ?int
{
    $cli = "cat JobOrder_log.txt |grep '";
    $cli.='"';
    $cli.=$msJobOrderID;
    $cli.='"';
    $cli.=', ';
    $cli.='"';
    $cli.=$msCompanyID;
    $cli.='"';
    $cli.="'";

    $grep = shell_exec($cli);
    $array = explode('", "',$grep);

    return !empty($array[5]) ? intval($array[5]) : null;
}

/**
 * uploadDataToBullhorn
 *
 * @param  mixed $data
 * @return void
 */
function uploadDataToBullhorn($row, $candidateId, $jobOrderId) : int
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
        "jobOrder" => [
            'id' => $jobOrderId
        ],
        "candidate" => [
            "id" => $candidateId,
        ],
        "status" => "Submitted",
    ];

    print_r($requestBody);

    $response = $httpClient->request('PUT', 'entity/JobSubmission',
        [
            'json' => [
                "jobOrder" => [
                    'id' => intval($jobOrderId)
                ],
                "candidate" => [
                    "id" => intval($candidateId),
                ],
                "status" => "Submitted",
            ]
        ]
    );

    $data = json_decode($response->getBody()->getContents());

    $client->refreshSession();
    
    return $data->changedEntityId;
}



getDataFromSqlServer();