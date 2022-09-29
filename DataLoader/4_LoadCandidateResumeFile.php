<?php
require "../vendor/autoload.php";
require "../config/database.php";
#Models
require "../Models/ModelCandidate.php";
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
    ->where('IsCandidateOnly','1');
   
    $rows = file(getcwd().'/CandidateResumeFile_log.txt');
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

        print_r($row);

        $fileName = $row->StorageName;

        if($fileName)
        {
            $parts = explode('/',$fileName);
            $fileName = $parts[2];
            $folder = $parts[0].$parts[1];

            $fullPath = "Backup/".$folder."/".$fileName;

            if(file_exists($fullPath))
            {

                $CandidateBullhornID = getBullhornCandidateId($row->ContactID);

                if($CandidateBullhornID)
                {
                    $uploadedFileId = uploadDataToBullhorn($fullPath, $CandidateBullhornID, $row->FileName);

                }
                // $file = Utils::tryFopen($fullPath, 'r');

                @shell_exec('echo "'.$row->CandidateAttachmentID.'", "'.$row->ContactID.'", "'.$fullPath.'", "'.$row->FileName.'", "'.$CandidateBullhornID.'", "'.$uploadedFileId.'" >> CandidateResumeFile_log.txt');
                // var_dump($file);
                print_r("LOADED: ".$fullPath."\n");

                sleep(3);

            }else{
                
                @shell_exec('echo "'.$row->ContactID.'", "'.$fullPath.'", "'.$row->FileName.'" >> NotLoadedCandidateResumeFile.txt');
            }
        }

        

        
        // $bhId = formatData($row);
        
        // sleep(3);
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

    // print_r($response);
    // print_r($response->getBody()->getContents());exit;


    $data = json_decode($response->getBody()->getContents());
    // print_r("aaaaa");
    $client->refreshSession();

    return $data->fileId;
}



getDataFromSqlServer();