<?php

while(true){
    $ps = shell_exec('tasklist | grep php');
    
    $isRunningProcess = strpos($ps,"php.exe",7);

    if(!$isRunningProcess)
    {
        print_r("######### STARTING ETL PROCESS ##########");
        shell_exec('php 4_LoadCandidateResumeFile.php');
    }else{
        
        print_r("######### PROCESS ALREADY RUNNING ##########");
    }

    sleep(5);
}