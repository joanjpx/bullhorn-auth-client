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

    $rows = file(getcwd() . '/CandidateNote_logv2.csv');
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

    
    print_r("####### Restantes...................: [".$allRows->count()."] ###### \n");
    
    foreach ($allRows as $row)
    {   
        $cli = "cat CandidateNote_log.txt |grep '".$row->UniqueID.','.$row->NoteID.','.$row->ContactID."'";
        
        $prompt = shell_exec($cli);
        
        if(!empty($prompt))
        {
            print_r("####### ALREADY LOADED #######"."\n");

            $data = explode(',',$prompt);

            $data = array_map(function($e){

                return trim(str_replace('"','',$e));

            }, $data);

            @shell_exec('echo "'.$data[0].'","'.$data[1].'","'.$data[2].'","'.$data[3].'","'.$data[4].'" >> CandidateNote_logv2.csv');

            continue;
            
        }else{
            
            print_r("####### NOT LOADED #######"."\n");
        }

        try{

            $CandidateBullhornID = getBullhornCandidateId($row->ContactID);

            if (!empty($CandidateBullhornID)) 
            {
                $changedEntityId = uploadDataToBullhorn($CandidateBullhornID, $row->Text);

                if(!empty($changedEntityId))
                {
                    @shell_exec('echo "'.$row->UniqueID.'","'.$row->NoteID.'","'.$row->ContactID.'","'.$CandidateBullhornID.'","'.$changedEntityId.'" >> CandidateNote_logv2.csv');
                }else{
                    
                    @shell_exec('echo "'.$row->UniqueID.'","'.$row->NoteID.'","'.$row->ContactID.'","'.$CandidateBullhornID.'","'.$changedEntityId.'" >> NotLoadedCandidateNote_log.csv');
                }
            }
            
        }catch(Exception $e){

            @shell_exec('echo "'.$row->UniqueID.'","'.$row->NoteID.'","'.$row->ContactID.'","'.$CandidateBullhornID.'","'.$changedEntityId.'" >> NotLoadedCandidateNote_log.csv'); 
        }

        sleep(1);
    }
}



function getBullhornCandidateId(int $mssqlId)
{
    $rows = fopen(getcwd() . '/Candidate_logv2.csv', 'r');

    while (($line = fgetcsv($rows, 0, ',', '"')) !== FALSE) {
        if ($line[0] == $mssqlId) return intval($line[2]);
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

    $credentialsFileName = getcwd() . '/../../client-credentials.json';
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

    $response = $httpClient->request(
        'PUT',
        'entity/Note',
        [
            'json' => $requestBody
        ]
    );

    $data = json_decode($response->getBody()->getContents());

    $client->refreshSession();

    return $data->changedEntityId;
}



getDataFromSqlServer();