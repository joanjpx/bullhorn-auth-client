<?php
require "../../vendor/autoload.php";
require "../../config/database.php";
#Models
require "../../Models/ModelJobOrder.php";
#Entity
use Illuminate\Database\Capsule\Manager as DB;

use jonathanraftery\Bullhorn\Rest\Authentication\Client;
use GuzzleHttp\Client as GuzzleClient;
use Models\ModelJobOrder;

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

    $model = (new ModelJobOrder)
    ->leftJoin(
        "Contact AS T2",
        "T2.ContactID",
        "=",
        "JobOrder.ContactID"
    )
    ->leftJoin(
        "Company AS T3",
        "T3.CompanyID",
        "=",
        "JobOrder.CompanyID"
    )
    ->whereNotNull('JobOrder.CompanyID')
    ->orderBy('JobOrder.JobOrderID','ASC');

    $rows = file(getcwd().'/JobOrder_logv2.csv');
    $last_row = array_pop($rows);
    $data = str_getcsv($last_row);
    
    if(!empty($data))
    {
        if($data[0]!='MSSQL_JobOrderID' && !empty($data[0]))
        {
            $model = $model->where('JobOrderID','>',$data[0]);
        }
    }
   
    $allRows = $model->select([
        "JobOrder.JobOrderID",
        "JobOrder.PlacementType",
        "JobOrder.CompanyID",
        "T3.Name",
        "JobOrder.ContactID",
        "JobOrder.JobTitle",
        "JobOrder.JobDescription",
        "T2.FirstName",
        "T2.FullName",
        "T2.LastName",
        "JobOrder.SalaryMinValue",
        "JobOrder.SalaryMaxValue",
        "JobOrder.SalaryType"
    ])->get();


    print_r("####### Restantes...................: [".$allRows->count()."] ###### \n");
    
    foreach ($allRows as $row)
    {

        
        $search = '"'.$row->JobOrderID.'","'.$row->CompanyID.'","';
        
        $cli = "cat ./JobOrder_log.txt |grep '".$search."'";
        
        $prompt = shell_exec($cli);

        if(!empty($prompt))
        {
            print_r("####### ALREADY LOADED #######"."\n");

            $data = explode('","',$prompt);

            $data = array_map(function($e){

                return trim(str_replace('"','',$e));

            }, $data);

            @shell_exec('echo "'.$data[0].'","'.$data[1].'","'.$data[2].'","'.$data[3].'","'.$data[4].'","'.$data[5].'" >> JobOrders_logv2.csv');

            continue;
            
        }else{
            
            print_r("####### NOT LOADED #######"."\n");
        }

        try{

            $ClientCorporationBhID = getBullhornClientCorporationID($row->CompanyID, $row->Name);
            $ClientContactBhID = getBullhornClientContactID($row->ContactID, $row->FullName);
    
            if(!empty($ClientCorporationBhID) && !empty($ClientContactBhID))
            {
                $changedEntityId = uploadDataToBullhorn($row, $ClientCorporationBhID, $ClientContactBhID);
    
                if($changedEntityId)
                {
                    @shell_exec('echo "'.$row->JobOrderID.'", "'.$row->CompanyID.'", "'.$row->ContactID.'", "'.$row->JobTitle.'", "'.$row->Name.'", "'.$changedEntityId.'" >> JobOrder_log.txt');
                }else{
    
                    @shell_exec('echo "'.$row->JobOrderID.'", "'.$row->CompanyID.'", "'.$row->ContactID.'", "'.$row->JobTitle.'", "'.$row->Name.'", "'.$changedEntityId.'" >> NotLoadedJobOrder_log.txt');
                }
                
                sleep(2);
            }
            
        }catch(Exception $e)
        {
            
        }
        
        sleep(3);
    }
}



function getBullhornClientCorporationID(string $clientCorporationId, string $description) : string
{
    $cli = "cat ./ClientCorporation_log.txt |grep '";
    $cli.='"';
    $cli.=$clientCorporationId;
    $cli.='"';
    $cli.=',';
    $cli.='"';
    $cli.=$description;
    $cli.='"';
    $cli.="'";

    $grep = shell_exec($cli);
    $array = explode('","',$grep);

    return intval($array[2]);
}


function getBullhornClientContactID(?string $clientContactId, ?string $fullName) : string
{
    if(empty($clientContactId)) return 16969;

    $cli = "cat ./ClientContact_log.txt |grep '";
    $cli.='"';
    $cli.=$clientContactId;
    $cli.='"';
    $cli.=',';
    $cli.='"';
    $cli.=$fullName;
    $cli.='"';
    $cli.="'";

    $grep = shell_exec($cli);
    $array = explode('","',$grep);

    return !empty($array[2]) ? intval($array[2]) : 16969;
}

/**
 * uploadDataToBullhorn
 *
 * @param  mixed $data
 * @return void
 */
function uploadDataToBullhorn($row, $corpId, $contactId) : int
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

    $employmentType = [
        "Contract" => "Contract",
        "Permanent" => "Direct Hire",
        "Temporary" => "Contract To Hire",
    ];

    $salaryUnit = [
        "PerHour" => "per hour",
        "PerYear" => "yearly",
        "PerMonth" => "per month"
    ];

    $requestBody = [
        "clientContact" => [
            "id" => $contactId,
        ],
        "clientCorporation" => [
            "id" => $corpId,
        ],
        "title" => $row->JobTitle,
        "description" => $row->JobDescription,
        "employmentType" => $employmentType[$row->PlacementType],
        "salary" =>  $row->SalaryMinValue, // salary low
        "customFloat1" =>  $row->SalaryMaxValue, // salary hig
        "startDate" => strtotime($row->StartDate." 23:59:59") * 1000
        // "isOpen" => true,
        // "status" => "Accepting Candidates"
    ];

    if(!empty($salaryUnit[$row->SalaryType])) $requestBody["salaryUnit"] = $salaryUnit[$row->SalaryType];

    print_r($requestBody);

    $response = $httpClient->request('PUT', 'entity/JobOrder',
        [
            'json' => $requestBody
        ]
    );

    $data = json_decode($response->getBody()->getContents());
    // print_r("aaaaa");
    // $client->refreshSession();

    return $data->changedEntityId;
}


getDataFromSqlServer();