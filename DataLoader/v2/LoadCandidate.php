<?php
require "../../vendor/autoload.php";
require "../../config/database.php";
#Models
require "../../Models/ModelCandidate.php";
#Entity
use Illuminate\Database\Capsule\Manager as DB;

use jonathanraftery\Bullhorn\Rest\Authentication\Client;
use GuzzleHttp\Client as GuzzleClient;
use Models\ModelCandidate;

/**
 * getDataFromSqlServer
 *
 * @return void
 */
function getDataFromSqlServer()
{
    $model = ModelCandidate::leftJoin(
        "Contact",
        "Contact.ContactID",
        "=",
        "Candidate.ContactID"
    )->where('IsCandidateOnly','1');
    
    $rows = file(getcwd().'/Candidate_logv2.csv');
    $last_row = array_pop($rows);
    $data = str_getcsv($last_row);

    
    if(!empty($data))
    {
        if($data[0]!='MSSQL_CandidateID')
        {
            $model = $model->where('Contact.ContactID','>',$data[0]);
        }
    }
    
    $allRows = $model->orderBy('ContactID','ASC')->select(['Contact.*'])->get();

    
    print_r("####### Restantes...................: [".$allRows->count()."] ###### \n");
    
    foreach ($allRows as $row)
    {
        
        $search = '"'.$row->ContactID.'","';
        
        $cli = "cat ./Candidate_log.txt |grep '".$search."'";
        
        $prompt = shell_exec($cli);

        // print_r($cli);
        // print_r($prompt);
        
        if(!empty($prompt))
        {
            print_r("####### ALREADY LOADED #######"."\n");

            $data = explode('","',$prompt);

            $data = array_map(function($e){

                return trim(str_replace('"','',$e));

            }, $data);

            @shell_exec('echo "'.$data[0].'","'.$data[1].'","'.$data[2].'" >> Candidate_logv2.csv');

            continue;
            
        }else{
            
            print_r("####### NOT LOADED #######"."\n");
        }

        try{

            $bhId = formatData($row);
            
            @shell_exec('echo "'.$row->ContactID.'","'.$row->FullName.'","'.$bhId.'" >> Candidate_logv2.csv');
            
        }catch(Exception $e)
        {
            @shell_exec('echo "'.$row->ContactID.'","'.$row->FullName.'","'.'" >> NotLoadedCandidate_log.csv');   
        }
        
        sleep(3);
    }
}

/**
 * uploadDataToBullhorn
 *
 * @param  mixed $data
 * @return void
 */
function formatData(ModelCandidate $data)
{   
    
    $requestBody = [
        "companyName" => $data->CurrentEmployer,
        "occupation" => $data->Position ?? '',
        "firstName" => $data->FirstName ?? '',
        "lastName" => $data->LastName ?? '',
        "mobile" => $data->Mobile ?? '',
        "phone" => $data->Phone ?? '',
        "name" => $data->FullName ?? '',
        "email" => $data->Email ?? '',
        "dateOfBirth" => $data->DateOfBirth,
        "companyURL" => $data->LinkedInUrl,
        "fax" => null,
        "status" => "Active",
        "address" => [
            "address1" => $data->AddressLine1 ?? '',
            "address2" => $data->AddressLine2 ?? '',
            "city" => $data->AddressSuburb ?? '',
            "state" => $data->AddressState ?? '',
            "zip" => $data->AddressPostcode ?? '',
            "countryName" => "United States",
            "countryID" => null
        ]
    ];

    return uploadDataToBullhorn($requestBody);
}



/**
 * uploadDataToBullhorn
 *
 * @param  mixed $data
 * @return void
 */
function uploadDataToBullhorn(array $candidate) : int
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

    $response = $httpClient->request('PUT', 'entity/Candidate',
        [
            'json' => $candidate
        ]
    );

    $data = json_decode($response->getBody());
    $client->refreshSession();

    return $data->changedEntityId;
}



getDataFromSqlServer();