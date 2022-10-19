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
    // SELECT 
    //     T1.ContactID,
    //     T0.FullName,
    //     T3.*
    // FROM Candidate AS T1 
    // LEFT JOIN Contact AS T0 ON T1.ContactID=T0.ContactID
    // LEFT JOIN CandidateNote AS T2 ON T1.ContactID=T2.ContactID
    // LEFT JOIN Note T3 ON T2.NoteID = T3.NoteID
    // WHERE T0.IsCandidateOnly=1
    // AND T3.Text IS NOT NULL
    // ORDER BY T0.ContactID,T3.DateCreated ASC

    $model = (new ModelCandidate)
        ->leftJoin(
            "Contact AS T1",
            "T1.ContactID",
            "=",
            "Candidate.ContactID"
        )->leftJoin(
            'CandidateNote AS T2',
            'Candidate.ContactID',
            '=',
            'T2.ContactID'
        )->leftJoin(
            'Note AS T3',
            'T3.NoteID',
            '=',
            'T2.NoteID'
        )
        ->where('IsCandidateOnly', '1')
        ->whereNotNull('T3.Text')
        ->orderBy('T3.UniqueID', 'ASC');

    $rows = file(getcwd() . '/CandidateNotes_log.txt');
    $last_row = array_pop($rows);
    $data = str_getcsv($last_row);

    if (!empty($data)) {
        if ($data[0] != 'UniqueID' && !empty($data[0])) {
            $model = $model->where('T3.UniqueID', '>', $data[0]);
        }
    }

    $allRows = $model->select([
        "T3.UniqueID",
        "T3.NoteID",
        "T1.ContactID",
        "T1.FullName",
        "T3.Text"
    ])->get();


    print_r("####### Restantes...................: [" . $allRows->count() . "] ###### \n");


    foreach ($allRows as $row) {
        // UniqueID
        // NoteID
        // ContactID
        // FullName
        // Text        
        $CandidateBullhornID = getBullhornCandidateId($row->ContactID);

        if ($CandidateBullhornID) {
            $changedEntityId = uploadDataToBullhorn($CandidateBullhornID, $row->Text);

            if ($changedEntityId) {
                @shell_exec('echo "' . $row->UniqueID . '", "' . $row->NoteID . '", "' . $row->ContactID . '", "' . $row->FullName . '", "' . '' . '", "' . $CandidateBullhornID . '", "' . $changedEntityId . '" >> CandidateNotes_log.txt');
            } else {

                @shell_exec('echo "' . $row->UniqueID . '", "' . $row->NoteID . '", "' . $row->ContactID . '", "' . $row->FullName . '", "' . '' . '", "' . $CandidateBullhornID . '", "' . $changedEntityId . '" >> NotLoadedCandidateNotes_log.txt');
            }
            sleep(2);
        }
    }
}


function getBullhornCandidateId(int $mssqlId)
{
    $rows = fopen(getcwd() . '/Candidate_log.txt', 'r');

    while (($line = fgetcsv($rows, 0, ',', '"')) !== FALSE) {
        if ($line[0] == $mssqlId) return $line[2];
    }

    fclose($rows);

    return false;
}

/**
 * uploadDataToBullhorn
 *
 * @param  mixed $data
 * @return void
 */
function uploadDataToBullhorn(int $CandidateId, string $comment): int
{

    $credentialsFileName = getcwd() . '/../client-credentials.json';
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
        "comments" => $comment,
        "multipleNotes" => false,
        "personReference" => [
            "id" => $CandidateId,
            "searchEntity" => "Candidate",
            "firstName" => "",
            "lastName" => "",
        ],
        "action" => "Other",
        "nextAction" => "None",
        "minutesSpent" => 0
    ];

    print_r($requestBody);

    $response = $httpClient->request(
        'PUT',
        'entity/Note',
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