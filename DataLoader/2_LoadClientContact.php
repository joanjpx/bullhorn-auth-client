<?php
require "../vendor/autoload.php";
require "../config/database.php";
#Models
require "../Models/ModelClientCorporation.php";
#Entity
use Illuminate\Database\Capsule\Manager as DB;
use Models\ModelClientCorporation;


function getDataFromSqlServer()
{
    $model = new ModelClientContact();

    $allRows = $model::all();

    print_r($allRows);exit;

    foreach($allRows as $row)
    {

    }
}

function uploadDataToBullhorn(array $data)
{

}

getDataFromSqlServer();