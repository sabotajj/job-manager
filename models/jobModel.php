<?php
require_once __DIR__."/../layers/DbLayer.php";
class Job {
    public $id=0;
    public $name="";
    public $interval=0;
    public $command;
    public $commandParams;
    public $cleanupScript;
    public $recoveryScript;
    public $lastPID;
    public $lastStart;
    public $lastEnd;
    public $state;
    public $nextRun;
    public $lastError;
    /**
     * @var JobDbLayer
     */
    public $dbLayer;
    public static $dbLayerClass = null;
    function __construct($dbLayer = null)
    {
        if(!is_null($dbLayer)) {
            $this->dbLayer = $dbLayer;
        }
    }

    function calculateNextRun(){
        //Will calculate next run here.
        //initial value , lastStart
        $nextRun = strtotime($this->lastStart);
        while($nextRun<time()){
            $nextRun+=$this->interval*60;
        }
        //if we are out of while , nextRun is in the future in the closest date.
        $this->nextRun = date('Y-m-d H:i:s',$nextRun);
    }

    function saveJob(){
        if($this->id>0) {
            $this->dbLayer->updateJob($this);
        }
        else{
            $this->dbLayer->createJob($this);
        }
    }
    function getJob($id){
        $job = $this->dbLayer->getJobById($id)[0];
        $this->readFromDBObject($job);

    }
    static function getNewJobs($numberOfJobsToPull){
        $dbLayer = is_null(self::$dbLayerClass)?new JobDbLayer(new DbLayer()):new self::$dbLayerClass();
        $jobs = $dbLayer->getNextJobsToRun($numberOfJobsToPull);
        if(is_null($jobs)){
            return [];
        }
        else {
            return array_map(function ($jobDBObject) {
                $dbLayer = is_null(self::$dbLayerClass)?new JobDbLayer(new DbLayer()):new self::$dbLayerClass();
                $job = new Job($dbLayer);
                $job->readFromDBObject($jobDBObject);
                return $job;
            }, $jobs);
        }
    }
    static function getRunningJobs(){
        $dbLayer = is_null(self::$dbLayerClass)?new JobDbLayer(new DbLayer()):new self::$dbLayerClass();

        $jobs = $dbLayer->getRunningJobs();
        return array_map(function($jobDBObject) use ($dbLayer) {
            $job = new Job();
            $job->readFromDBObject($jobDBObject);
            return $job;
        },$jobs);

    }
    static function getQueuedJobs(){
        $dbLayer = is_null(self::$dbLayerClass)?new JobDbLayer(new DbLayer()):new self::$dbLayerClass();

        $jobs = $dbLayer->getQueuedJobs();
        return array_map(function($jobDBObject) use ($dbLayer) {
            $job = new Job();
            $job->readFromDBObject($jobDBObject);
            return $job;
        },$jobs);
    }
    function readFromDBObject($dbObject){
        $this->id = $dbObject["id"];
        $this->name = $dbObject["name"];
        $this->command = $dbObject["command"];
        $this->commandParams = $dbObject["command_params"];
        $this->recoveryScript = $dbObject["recovery_script"];
        $this->cleanupScript = $dbObject["cleanup_script"];
        $this->interval = is_null($dbObject["interval_minutes"])?0:$dbObject["interval_minutes"];
        $this->lastStart = $dbObject["last_start"];
        $this->lastEnd = $dbObject["last_end"];
        $this->lastError = $dbObject["last_error"];
        $this->state = $dbObject["state"];
        $this->nextRun = $dbObject["next_run"];
    }
    public function getJobIdentifier(){
        return md5($this->command.$this->interval);
    }
    public function updateStart(){
        return  $this->dbLayer->updateStartJob($this);
    }
    public function updateEnd(){
        return $this->dbLayer->updateEndJob($this);
    }

}