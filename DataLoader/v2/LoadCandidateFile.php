<?php
require "../../vendor/autoload.php";
require "../../config/database.php";
#Models
require "../../Models/ModelCandidate.php";
#Entity
use Illuminate\Database\Capsule\Manager as DB;

use jonathanraftery\Bullhorn\Rest\Authentication\Client;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Utils;
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
        "Contact",
        "Contact.ContactID",
        "=",
        "Candidate.ContactID"
    )
    ->leftJoin(
        'CandidateAttachment',
        'Contact.ContactID',
        '=',
        'CandidateAttachment.ContactID'
    )
    ->leftJoin(
        'Attachment',
        'CandidateAttachment.AttachmentID',
        '=',
        'Attachment.CandidateAttachmentID'
    )
    ->where('IsCandidateOnly','1')
    ->whereNotNull('CandidateAttachmentID');
   
    $rows = file(getcwd().'/CandidateFile_logv2.csv');
    $last_row = array_pop($rows);
    $data = str_getcsv($last_row);
    
    if(!empty($data))
    {
        if($data[0]!='CandidateAttachmentID')
        {
            $model = $model->where('Attachment.CandidateAttachmentID','>',$data[0]);
        }
    }
    
    $allRows = $model->orderBy('Attachment.CandidateAttachmentID','ASC')->select([
        'Candidate.ContactID',
        'CandidateAttachment.FileName',
        'Attachment.*'
    ])->get();

    print_r("####### Restantes...................: [".$allRows->count()."] ###### \n");
    
    foreach ($allRows as $row)
    {

        $search = '"'.$row->CandidateAttachmentID.'", "'.$row->ContactID.'", "';

        $cli = "cat ./CandidateResumeFile_log.txt |grep '".$search."'";
        
        $prompt = shell_exec($cli);
        
        if(!empty($prompt))
        {
            print_r("####### ALREADY LOADED #######"."\n");

            $data = explode('", "',$prompt);

            $data = array_map(function($e){
                return trim(str_replace('"','',$e));
            }, $data);

            
            @shell_exec("echo ".'"'.$data[0].'","'.$data[1].'","'.$data[2].'","'.$data[3].'","'.$data[4].'","'.$data[5].'"'." >> CandidateFile_logv2.csv");
            
            continue;
            
        }else{
            print_r("####### NOT LOADED #######"."\n");
        }
        
        // exit;


        $fileName = $row->StorageName;

        if($fileName)
        {
            $parts = explode('/',$fileName);
            $fileName = $parts[2];
            $folder = $parts[0].$parts[1];

            $fullPath = "Backup/".$folder."/".$fileName;

            if(file_exists($fullPath))
            {
                print_r($row);
                $CandidateBullhornID = getBullhornCandidateId($row->ContactID);

                print_r($CandidateBullhornID);exit;

                if($CandidateBullhornID)
                {
                    try{

                        $uploadedFileId = uploadDataToBullhorn($fullPath, $CandidateBullhornID, $row->FileName);
                        @shell_exec('echo "'.$row->CandidateAttachmentID.'","'.$row->ContactID.'","'.$fullPath.'","'.$row->FileName.'","'.$CandidateBullhornID.'","'.$uploadedFileId.'" >> CandidateFile_logv2.csv');
                        print_r("LOADED: ".$fullPath."\n");
                    
                    }catch(Exception $e)
                    {
                        @shell_exec('echo "'.$row->ContactID.'","'.$fullPath.'","'.$row->FileName.'" >> NotLoadedCandidateResumeFile.txt');
                        continue;
                    }
                }
                sleep(1);
            }
        }
    }
}



function getBullhornCandidateId(int $mssqlId)
{
    $rows = fopen(getcwd().'/Candidate_logv2.csv','r');
        
    while (($line = fgetcsv($rows,0,',','"')) !== FALSE) 
    {
        if($line[0]==$mssqlId) return $line[2]; 
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
function uploadDataToBullhorn(string $fullPath, int $CandidateId, string $fileName) : int
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

    $response = $httpClient->request(
        'PUT',
        'file/Candidate/'.$CandidateId.'/raw?&externalID=-1',
        [
            'multipart' => [
                [
                    'name' => $fileName,
                    'contents' => Utils::tryFopen($fullPath, 'r'),
                    'headers'  => [
                        'Content-Type' => 'application/json'
                    ]
                ]
            ]
        ]
    );

    $data = json_decode($response->getBody()->getContents());

    return $data->fileId;
}



getDataFromSqlServer();