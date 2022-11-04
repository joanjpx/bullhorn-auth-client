<?php
require "../vendor/autoload.php";
require "../config/database.php";
#Models
require "../Models/ModelJobOrder.php";
#Entity
use Illuminate\Database\Capsule\Manager as DB;

use jonathanraftery\Bullhorn\Rest\Authentication\Client;
use GuzzleHttp\Client as GuzzleClient;
use Models\ModelJobOrder;
use Models\ModelSubmission;

/**
 * getDataFromSqlServer
 *
 * @return void
 */
function getDataFromSqlServer()
{
    // SELECT T1.ApplicationID, T1.ContactID, T1.JobOrderID, T2.FullName FROM JobApplication AS T1 LEFT JOIN Contact AS T2 ON T2.ContactID=T1.ContactID ORDER BY ApplicationID ASC

    // SELECT T1.ApplicationID, T1.ContactID, T1.JobOrderID, T2.FullName FROM JobApplication AS T1 LEFT JOIN Contact AS T2 ON T2.ContactID=T1.ContactID WHERE T1.JobOrderID IS NOT NULL ORDER BY ApplicationID ASC

    $model = (new ModelSubmission())
    ->leftJoin(
        "Contact AS T2",
        "T2.ContactID",
        "=",
        "JobApplication.ContactID"
    )
    ->leftJoin(
        "JobOrder AS T3",
        "T3.JobOrderID",
        "=",
        "JobApplication.JobOrderID"
    )
    ->whereNotNull('JobApplication.JobOrderID')
    ->whereNotNull('T3.CompanyID')
    ->orderBy('JobApplication.ApplicationID','ASC');

    $rows = file(getcwd().'/JobSubmission_log2.txt');
    $last_row = array_pop($rows);
    $data = str_getcsv($last_row);
    
    if(!empty($data))
    {
        if($data[0]!='MSSQL_JobSubmissionID' && !empty($data[0]))
        {
            $model = $model->where('ApplicationID','>',$data[0]);
        }
    }
   
    $allRows = $model->select([
        "JobApplication.ApplicationID",
        "JobApplication.ContactID",
        "JobApplication.JobOrderID",
        "T2.FullName",
        "T3.CompanyID",
        "JobApplication.DateCreated"
    ])->get();


    print_r("####### Restantes...................: [".$allRows->count()."] ###### \n");
    sleep(3);

    
    foreach ($allRows as $row)
    {

        $cli = "cat JobSubmission_log.txt |grep "."'".'"' . $row->ApplicationID . '", "' . $row->ContactID . '"'."'";

        $prompt = shell_exec($cli);

        var_dump($prompt);
        
        if(!empty($prompt))
        {
            continue;
        }

        $candidateId = getBullhornCandidateId($row->ContactID);
        $jobOrderId = getBullhornJobOrderID($row->JobOrderID, $row->CompanyID);

        @shell_exec('echo "'.$row->ApplicationID.'", "'.$row->ContactID.'", "'.$row->JobOrderID.'", "'.$row->CompanyID.'", "'.$row->FullName.'", "'.$candidateId.'", "'.$jobOrderId.'", "'.'" >> JobSubmission_log2.txt');
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
    
    return null;
}




function getBullhornJobOrderID(?string $msJobOrderID, ?string $msCompanyID) : ?int
{
    $cli = "cat JobOrder_log.txt |grep '";
    $cli.='"';
    $cli.=$msJobOrderID;
    $cli.='"';
    $cli.=', ';
    $cli.='"';
    $cli.=$msCompanyID;
    $cli.='"';
    $cli.="'";

    $grep = shell_exec($cli);
    $array = explode('", "',$grep);

    return !empty($array[5]) ? intval($array[5]) : null;
}



getDataFromSqlServer();