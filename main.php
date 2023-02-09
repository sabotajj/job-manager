<?php
require_once __DIR__."/layers/FileSystemLayer.php";
require_once __DIR__."/controllers/JobsController.php";

$loggerFunc = function($message){
    echo $message.PHP_EOL;
};
$fileSystemLayer = new FileSystemLayer();
$jobManager = new JobsController($loggerFunc,$fileSystemLayer);
if($argc==1){
    echo "Please enter a command [start | kill <jobId> | status]";
    exit(1);
}
$command = $argv[1];
switch($command){
    default:
    case 'start':$jobManager->work();break;
    case 'kill': $jobId = $argv[2]; $jobManager->kill($jobId);break;
    case 'status': $isDetailed = $argc > 2 && $argv[2] == 'detail'; $jobManager->status($isDetailed);break;
}
