<?php
require "../vendor/autoload.php";
require "../config/database.php";
#Models
require "../Models/ModelClientContact.php";
#Entity
use Illuminate\Database\Capsule\Manager as DB;
use Models\ModelClientContact;

use jonathanraftery\Bullhorn\Rest\Authentication\Client;
use GuzzleHttp\Client as GuzzleClient;

/**
 * getDataFromSqlServer
 *
 * @return void
 */
function getDataFromSqlServer()
{
    $model = ModelClientContact::leftJoin(
        "Candidate",
        "Contact.ContactID",
        "=",
        "Candidate.ContactID"
    )->where('IsCandidateOnly','0');
    
    $rows = file(getcwd().'/ClientContact_log.txt');
    $last_row = array_pop($rows);
    $data = str_getcsv($last_row);

    
    if(!empty($data))
    {
        if($data[0]!='MSSQL_ContactID')
        {
            $model = $model->where('Contact.ContactID','>',$data[0]);
        }
    }
    
    $allRows = $model->orderBy('ContactID','ASC')->select(['Contact.*'])->get();

    
    
    foreach ($allRows as $row)
    {
        if(empty($row->CompanyID))
        {
            @shell_exec('echo "'.$row->ContactID.'","'.$row->FullName.'" >> NotLoadedClientContact.txt');
            continue;
        }

        $bhId = formatData($row);
        
        @shell_exec('echo "'.$row->ContactID.'","'.$row->FullName.'","'.$bhId.'" >> ClientContact_log.txt');
        sleep(3);
    }
}

/**
 * uploadDataToBullhorn
 *
 * @param  mixed $data
 * @return void
 */
function formatData(ModelClientContact $data)
{
    $company = [];

    if($data->CompanyID)
    {
        $rows = fopen(getcwd().'/ClientCorporation_log.txt','r');
        
        while (($line = fgetcsv($rows)) !== FALSE) 
        {
            if($line[0]=='MSSQL_CompanyID') continue; 
            
            if($data->CompanyID==$line[0])
            {
                $company = $line;
            }
        }
        
        fclose($rows);
    }
    
    
    $requestBody = [
        "occupation" => $data->Position ?? '',
        "firstName" => $data->FirstName ?? '',
        "lastName" => $data->LastName ?? '',
        "mobile" => $data->Mobile ?? '',
        "phone" => $data->Phone ?? '',
        "name" => $data->FullName ?? '',
        "email" => $data->Email ?? '',
        "fax" => null,
        "status" => "Active",
        "clientCorporation" => [
            "id" => (int)$company[2]
        ],
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

    print_r($candidate);

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

    $response = $httpClient->request('PUT', 'entity/ClientContact',
        [
            'json' => $candidate
        ]
    );

    $data = json_decode($response->getBody());

    return $data->changedEntityId;
}



getDataFromSqlServer();