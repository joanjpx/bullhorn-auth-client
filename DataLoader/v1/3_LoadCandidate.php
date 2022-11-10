<?php
require "../vendor/autoload.php";
require "../config/database.php";
#Models
require "../Models/ModelCandidate.php";
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
    
    $rows = file(getcwd().'/Candidate_log.txt');
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
        $bhId = formatData($row);
        
        @shell_exec('echo "'.$row->ContactID.'","'.$row->FullName.'","'.$bhId.'" >> Candidate_log.txt');
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

    print_r($requestBody);
    // exit;



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

    $response = $httpClient->request('PUT', 'entity/Candidate',
        [
            'json' => $candidate
        ]
    );

    $data = json_decode($response->getBody());

    return $data->changedEntityId;
}



getDataFromSqlServer();