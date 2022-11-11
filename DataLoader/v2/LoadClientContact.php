<?php
require "../../vendor/autoload.php";
require "../../config/database.php";
#Models
require "../../Models/ModelClientContact.php";
#Entity
use Illuminate\Database\Capsule\Manager as DB;

use jonathanraftery\Bullhorn\Rest\Authentication\Client;
use GuzzleHttp\Client as GuzzleClient;
use Models\ModelClientContact;

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

     
    $rows = file(getcwd().'/ClientContact_logv2.csv');
    $last_row = array_pop($rows);
    $data = str_getcsv($last_row);
    
    if(!empty($data[0]) && $data[0]!='ContactID')
    {
        $model = $model->where('Contact.ContactID','>',$data[0]);
    }

    $model = $model->select([
        "Contact.ContactID",
        "Contact.CompanyID",
        "Contact.Position",
        "Contact.FirstName",
        "Contact.LastName",
        "Contact.Mobile",
        "Contact.Phone",
        "Contact.FullName",
        "Contact.Email",
        "Candidate.AddressLine1",
        "Candidate.AddressLine2",
        "Candidate.AddressSuburb",
        "Candidate.AddressState",
        "Candidate.AddressPostcode"
    ]);

    $allRows = $model->orderBy('Contact.ContactID','ASC')->get();
    
    print_r("####### Restantes...................: [".$allRows->count()."] ###### \n");

    foreach ($allRows as $row)
    {
        $search = '"'.$row->ContactID.'","'.$row->FullName.'","';
        $cli = "cat ./ClientContact_log.txt |grep '".$search."'";
        $prompt = shell_exec($cli);

        if(!empty($prompt))
        {
            print_r("####### ALREADY LOADED #######"."\n");

            $data = explode('","',$prompt);

            $data = array_map(function($e){
                return trim(str_replace('"','',$e));
            }, $data);

            @shell_exec('echo "'.$data[0].'","'.$data[1].'","'.$data[2].'" >> ClientContact_logv2.csv');
            continue;
            
        }else{
            print_r("####### NOT LOADED #######"."\n");
        }

        try{

            $bhId = formatData($row);
            @shell_exec('echo "'.$row->ContactID.'","'.$row->FullName.'","'.$bhId.'" >> ClientContact_logv2.csv');
            
        }catch(Exception $e)
        {
            @shell_exec('echo "'.$row->ContactID.'","'.$row->FullName.'","'.'" >> NotLoadedClientContact_log.csv');
        }
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
        $rows = fopen(getcwd().'/ClientCorporation_logv2.csv','r');
        
        while (($line = fgetcsv($rows)) !== false) 
        {            
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
            "id" => !empty($company[2]) ? (int)$company[2] : 496
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

    return uploadDataToBullhorn($requestBody);
}



/**
 * uploadDataToBullhorn
 *
 * @param  mixed $data
 * @return void
 */
function uploadDataToBullhorn(array $contact) : int
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

    $response = $httpClient->request('PUT', 'entity/ClientContact',
        [
            'json' => $contact
        ]
    );

    $data = json_decode($response->getBody());
    $client->refreshSession();
    
    return $data->changedEntityId;
}

getDataFromSqlServer();