<?php
require "../../vendor/autoload.php";
require "../../config/database.php";
#Models
require "../../Models/ModelSubmission.php";
#Entity
use Illuminate\Database\Capsule\Manager as DB;

use jonathanraftery\Bullhorn\Rest\Authentication\Client;
use GuzzleHttp\Client as GuzzleClient;
use Models\ModelSubmission;

/**
 * getDataFromSqlServer
 *
 * @return void
 */
function getDataFromSqlServer()
{
    $model = (new ModelSubmission())
    ->leftJoin(
        "Contact AS T2",
        "T2.ContactID",
        "=",
        "JobApplication.ContactID"
    )
    ->leftJoin(
        "JobOrder AS T3",
        "T3.JobOrderID",
        "=",
        "JobApplication.JobOrderID"
    )
    ->orderBy('JobApplication.ApplicationID','ASC');

    $rows = file(getcwd().'/Submission_logv2.csv');
    $last_row = array_pop($rows);
    $data = str_getcsv($last_row);
    
    if(!empty($data))
    {
        if($data[0]!='MSSQL_JobSubmissionID' && !empty($data[0]))
        {
            $model = $model->where('JobApplication.ApplicationID','>',$data[0]);
        }
    }
   
    $allRows = $model->select([
        "JobApplication.ApplicationID",
        "JobApplication.ContactID",
        "JobApplication.JobOrderID",
        "T2.FullName",
        "T3.CompanyID",
        "JobApplication.DateCreated"
    ])->get();

    
    print_r("####### Restantes...................: [".$allRows->count()."] ###### \n");
    
    foreach ($allRows as $row)
    {
        
        $search = '"'.$row->ApplicationID.'","'.$row->ContactID.'","'.$row->JobOrderID.'","'.$row->CompanyID.'","';
        
        $cli = "cat ./Submission_log.txt |grep '".$search."'";
        
        $prompt = shell_exec($cli);

        
        
        if(!empty($prompt))
        {
            print_r("####### ALREADY LOADED #######"."\n");
            print_r($prompt."\n");


            $data = explode('","',$prompt);

            $data = array_map(function($e){

                return trim(str_replace('"','',$e));

            }, $data);

            @shell_exec("echo ".'"'.$data[0].'","'.$data[1].'","'.$data[2].'","'.$data[3].'","'.$data[4].'","'.$data[5].'","'.$data[6].'","'.$data[7].'"'." >> Submission_logv2.csv");

            continue;
            
        }else{
            
            print_r("####### NOT LOADED #######"."\n");
        }

        try{

            $candidateId = getBullhornCandidateId($row->ContactID);
            $jobOrderId = getBullhornJobOrderID($row->JobOrderID, $row->CompanyID);

            // if(empty($candidateId) || empty($jobOrderId)) continue;

            $changedEntityId = uploadDataToBullhorn($row, $candidateId, $jobOrderId);
            
            @shell_exec('echo "'.$row->ApplicationID.'","'.$row->ContactID.'","'.$row->JobOrderID.'","'.$row->CompanyID.'","'.trim(str_replace('"','',$row->FullName)).'","'.$candidateId.'","'.$jobOrderId.'","'.$changedEntityId.'" >> Submission_logv2.csv');
            
        }catch(Exception $e)
        {
            @shell_exec('echo "'.$row->ApplicationID.'","'.$row->ContactID.'","'.$row->JobOrderID.'","'.$row->CompanyID.'","'.trim(str_replace('"','',$row->FullName)).'","'.$candidateId.'","'.$jobOrderId.'","'.$changedEntityId.'" >> NotLoadedSubmission_log.csv');
        }

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
    $cli = "cat ./JobOrder_log.txt |grep '";
    $cli.='"';
    $cli.=$msJobOrderID;
    $cli.='"';
    $cli.=',';
    $cli.='"';
    $cli.=$msCompanyID;
    $cli.='"';
    $cli.="'";

    print_r($cli);exit;

    $grep = shell_exec($cli);
    $array = explode('", "',$grep);

    return !empty($array[5]) ? intval($array[5]) : null;
}


function uploadDataToBullhorn($row, $candidateId, $jobOrderId) : int
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
        "jobOrder" => [
            'id' => $jobOrderId
        ],
        "candidate" => [
            "id" => $candidateId,
        ],
        "status" => "Submitted",
    ];

    print_r($requestBody);
    exit;

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