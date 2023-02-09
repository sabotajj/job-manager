<?php
require_once __DIR__."/../config.php";
class FileSystemLayer{
    private $folder = "";
    function createKillJobFile($jobId){
        file_put_contents($this->folder.$jobId.".kill",'');
    }
    function getPendingKillRequests(){
        $fileList = scandir($this->folder);
        //filter out if . or ..
        $fileList = array_shift($fileList); //removed .
        $fileList = array_shift($fileList); //removed ..
        return array_map(function($file){
            return str_replace(".kill",$file); //removing .kill extension

        },$fileList);
    }
    function removeKillRequest($jobId){
        unlink($this->folder."$jobId.kill");
    }
    function __construct()
    {
        $this->folder = Config::$killJobSignalFolder;
    }
}