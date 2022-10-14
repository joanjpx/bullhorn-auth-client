<?php

$cli = "cat ClientCorporation_log.txt |grep '";
$cli.='"';
$cli.="589095";
$cli.='"';
$cli.=',';
$cli.='"';
$cli.="Harvard University";
$cli.='"';
$cli.="'";

$grep = shell_exec($cli);
$array = explode('","',$grep);


var_dump(intval($array[2]));exit;
// return $array;