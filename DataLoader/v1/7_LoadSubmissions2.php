<?php
require "../vendor/autoload.php";
require "../config/database.php";
#Models
require "../Models/ModelJobOrder.php";
#Entity
use Illuminate\Database\Capsule\Manager as DB;

use jonathanraftery\Bullhorn\Rest\Authentication\Client;
use GuzzleHttp\Client as GuzzleClient;
use Models\ModelJobOrder;
use Models\ModelSubmission;

/**
 * getDataFromSqlServer
 *
 * @return void
 */
function getDataFromSqlServer()
{
    // SELECT T1.ApplicationID, T1.ContactID, T1.JobOrderID, T2.FullName FROM JobApplication AS T1 LEFT JOIN Contact AS T2 ON T2.ContactID=T1.ContactID ORDER BY ApplicationID ASC

    // SELECT T1.ApplicationID, T1.ContactID, T1.JobOrderID, T2.FullName FROM JobApplication AS T1 LEFT JOIN Contact AS T2 ON T2.ContactID=T1.ContactID WHERE T1.JobOrderID IS NOT NULL ORDER BY ApplicationID ASC

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
    ->whereNotNull('JobApplication.JobOrderID')
    ->whereNotNull('T3.CompanyID')
    ->orderBy('JobApplication.ApplicationID','ASC');

    $rows = file(getcwd().'/JobSubmission_log2.txt');
    $last_row = array_pop($rows);
    $data = str_getcsv($last_row);
    
    if(!empty($data))
    {
        if($data[0]!='MSSQL_JobSubmissionID' && !empty($data[0]))
        {
            $model = $model->where('ApplicationID','>',$data[0]);
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
    sleep(3);

    
    foreach ($allRows as $row)
    {

        $cli = "cat JobSubmission_log.txt |grep "."'".'"' . $row->ApplicationID . '", "' . $row->ContactID . '"'."'";

        $prompt = shell_exec($cli);

        var_dump($prompt);
        
        if(!empty($prompt))
        {
            continue;
        }

        $candidateId = getBullhornCandidateId($row->ContactID);
        $jobOrderId = getBullhornJobOrderID($row->JobOrderID, $row->CompanyID);


        if(!empty($candidateId) && !empty($jobOrderId))
        {
            $changedEntityId = uploadDataToBullhorn($row, $candidateId, $jobOrderId);

            if($changedEntityId)
            {

                // "MSSQL_JobSubmissionID", "MSSQL_ContactID", "MSSQL_JobOrderID", "MSSQL_CompanyID", "MSSQL_FullName", "BH_CandidateID", "BH_JobOrderID", "BH_SubmissionID"

                @shell_exec('echo "'.$row->ApplicationID.'", "'.$row->ContactID.'", "'.$row->JobOrderID.'", "'.$row->CompanyID.'", "'.$row->FullName.'", "'.$candidateId.'", "'.$jobOrderId.'", "'.$changedEntityId.'" >> JobSubmission_log2.txt');
            
            }else{
                
                @shell_exec('echo "'.$row->ApplicationID.'", "'.$row->ContactID.'", "'.$row->JobOrderID.'", "'.$row->CompanyID.'", "'.$row->FullName.'", "'.$candidateId.'", "'.$jobOrderId.'", "'.$changedEntityId.'" >> NotLoadedJobSubmission_log.txt');
            }
            
            sleep(2);
        }else{
                
            @shell_exec('echo "'.$row->ApplicationID.'", "'.$row->ContactID.'", "'.$row->JobOrderID.'", "'.$row->CompanyID.'", "'.$row->FullName.'", "'.$candidateId.'", "'.$jobOrderId.'", "'.'" >> NotLoadedJobSubmission_log.txt');
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