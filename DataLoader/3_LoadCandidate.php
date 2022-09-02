<?php
require "../vendor/autoload.php";
require "../config/database.php";
#Models
require "../Models/ModelCandidate.php";
#Entity
use Illuminate\Database\Capsule\Manager as DB;
use Models\ModelCandidate;

use jonathanraftery\Bullhorn\Rest\Authentication\Client;
use GuzzleHttp\Client as GuzzleClient;

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
    )->where('IsCandidateOnly','1')
    ->orderBy('FullName','ASC');

    $allRows = $model->get();

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
    $entity = [
        "address" => [
            "address1" => $data->AddressLine1,
            "address2" => $data->AddressLine2,
            "city" => $data->AddressSuburb,
            "state" => $data->AddressState,
            "zip" => $data->AddressPostcode,
            "countryID" => null,
        ],
        "companyName" => $data->CurrentEmployer,
        "firstName" => $data->FirstName,
        "name" => $data->FullName,
        "lastName" => $data->LastName,
        "occupation" => $data->Position,
        "mobile" => $data->Mobile,
        "phone" => $data->Phone,
        "email" => $data->Email,
        "occupation" => $data->Position,
        "dateOfBirth" => $data->DateOfBirth,
        "companyURL" => $data->LinkedInUrl
    ];

    return uploadDataToBullhorn($entity);
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