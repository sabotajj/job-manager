<?php

require_once __DIR__."/../vendor/autoload.php";
require_once __DIR__."/../layers/FileSystemLayer.php";
require_once __DIR__."/../models/JobModel.php";
use React\ChildProcess\Process;

class JobsController{
    private $eventLoop;
    //this will contain process objects
    private $runningProcesses = [];
    private $terminatorProcess = [];
    //this will store jobModels array
    private $runningJobs;
    private $loggerService;
    /** @var FileSystemLayer */
    private $fileSystemLayer;
    function kill($jobId){
        $runningJobs = Job::getRunningJobs();
        $requestedJob = array_filter($runningJobs,function($job) use ($jobId) {return $job->id == $jobId;});
        if(count($requestedJob)>0) {
            $requestedJob = $requestedJob[0];
            $this->fileSystemLayer->createKillJobFile($requestedJob->getJobIdentifier());
            return true;
        }
        else{
            //no running jobs found with this id. Display message
            $this->log("No running jobs found with id : $jobId");
            return false;
        }
    }
    function status($isDetailed=false){
        //returning running jobs
        $runningJobs = Job::getRunningJobs();
        $this->log("Running ".count($runningJobs)." job ");
        if($isDetailed) {
            foreach ($runningJobs as $job) {
                $this->log($job->id . "   " . $job->name . "  " . (time() - strtotime($job->lastStart)));
            }
        }
        $queuedJobs = Job::getQueuedJobs();
        $this->log(count($queuedJobs)." jobs in queue");
        if($isDetailed) {
            $this->log("Id, Name, Delay in sec");
            foreach ($queuedJobs as $job) {
                $this->log($job->id . "   " . $job->name . "  " . (time() - strtotime($job->nextRun)));
            }
        }
    }
    function waitTillJobsQueueFree(){
        $runningJobsCount = count(Job::getRunningJobs());
        while($runningJobsCount>Config::$maxRunningJobs){
            //$this->log("Waiting 10 secs. Exceeded max job queue. Current running jobs:".$runningJobsCount);
            sleep(3);
            $runningJobsCount = count(Job::getRunningJobs());
        }
    }
    function log($message){
        if(is_callable($this->loggerService)) {
            $loggerService = $this->loggerService;
            return $loggerService($message);
        }
    }

    function checkTerminateRequest()
    {
        //we will wait for kill.<id> files to appear. if we find it . we will terminate.
        $requests = scandir(Config::$killJobSignalFolder);
        //a kill process request received.
        $killProcessFileRegex = '/(\w+)\.kill/';
        $jobIdentifier = null;
        foreach($requests as $request) {
            if (preg_match($killProcessFileRegex, $request, $jobIdentifier)) {
                $jobIdentifier = $jobIdentifier[1];
                if ($this->isJobRunning($jobIdentifier)) {
                    $job = $this->runningJobs[$jobIdentifier];
                    $this->log("Terminating Job $job->name ($job->id)");
                    $this->runningProcesses[$jobIdentifier]->stdin->close();
                    $this->runningProcesses[$jobIdentifier]->stdout->close();
                    $this->runningProcesses[$jobIdentifier]->stderr->close();
                    $this->runningProcesses[$jobIdentifier]->terminate();

                    $job->getJob($job->id);//just for refresh
                    $job->state = 'terminated';
                    $job->saveJob();
                    $job->updateEnd();
                    $job->dbLayer->__destruct();
                    unset($this->runningProcesses[$jobIdentifier]);
                    unset($this->runningJobs[$jobIdentifier]);
                }
                $this->fileSystemLayer->removeKillRequest($jobIdentifier);
            }
        }
    }

    /**
     * we will use this function if exit event fails to work.
     */
    function cleanNonRunningJobs(){
        foreach($this->runningProcesses as $jobIdentifier=>&$runningProcess){
            //still exists in runningProcess but is not running meaning discrepancy. We should clean it
            if(!$runningProcess->isRunning()){
                unset($this->runningProcesses[$jobIdentifier]);
                //update in DB if status is running
                $job = $this->runningJobs[$jobIdentifier];
                if($job->state == 'running'){
                    $job->state = 'unknown';
                }
                $job->updateEnd();
                $job->saveJob();
                //to remove open connection
                $job->dbLayer->__destruct();
                unset($this->runningJobs[$jobIdentifier]);
            }
        }

    }
    function &startJob($jobModel)
    {
        //Here use jobHandler executing externally
        $this->waitTillJobsQueueFree();
        $process = null;
        //start the job here , with child process lib.display message on console
        $process = new \React\ChildProcess\Process("php ".__DIR__."/../layers/JobHandler.php $jobModel->id");
        $process->start($this->eventLoop);

        //when finished execute callback function ,removing for running jobs,displaying message on console
        $process->on('exit', function ($exitcode,$termSignal) use ($jobModel) {
            $jobIdentifier = $jobModel->getJobIdentifier();
            unset($this->runningProcesses[$jobIdentifier]);
            unset($this->runningJobs[$jobIdentifier]);
            $this->log("Job $jobModel->name ($jobModel->id) exit with " . $exitcode);

        });
        $process->stdout->on('data',function($chunk) use ($jobModel){
            $this->log("event from $jobModel->id. $chunk");
        });

        //return child-process object
        return $process;

    }

    function work()
    {
        //start job terminator job
        //here scan for new jobs to work and start them, with a loop
        while (true) {
            $this->checkTerminateRequest();
            $this->cleanNonRunningJobs();
            $jobToPull = Config::$maxRunningJobs - count(array_keys($this->runningProcesses));
            if($jobToPull>0) {
                $newJobs = Job::getNewJobs($jobToPull);
                foreach ($newJobs as $job) {
                    //check here if job with same identifier is already running, if yes skip
                    $jobIdentifier = $job->getJobIdentifier();
                    if (!$this->isJobRunning($jobIdentifier)) {
                        //Adding it to running jobs
                        $this->log($job->name . " ($job->id) starting");
                        $this->runningProcesses[$jobIdentifier] = &$this->startJob($job);
                        $this->runningJobs[$jobIdentifier] = $job;

                    }
                }

            }
            //$this->eventLoop->run();

            sleep(Config::$checkNewJobsInterval);
        }
    }
    function isJobRunning($jobIdentifier){
        return (isset($this->runningProcesses[$jobIdentifier]) && $this->runningProcesses[$jobIdentifier]->isRunning());
    }
    function __construct($loggerFunc,$fileSystemLayer)
    {
        //init eventloop
        $this->loggerService = $loggerFunc;
        $this->eventLoop = React\EventLoop\Factory::create();
        $this->fileSystemLayer = $fileSystemLayer;
    }
    function __destruct()
    {
        if(count($this->runningProcesses)>0) {
            //terminate all jobs
            foreach ($this->runningProcesses as $identifier => $process) {
                $process->stdin->close();
                $process->stdout->close();
                $process->stderr->close();
                $process->terminate();
                $this->runningJobs[$identifier]->state="terminated";
                $this->runningJobs[$identifier]->saveJob();

            }
            $this->terminatorProcess->stdin->close();
            $this->terminatorProcess->stdout->close();
            $this->terminatorProcess->stderr->close();
            $this->terminatorProcess->terminate();
            //stop the event loop
            $this->eventLoop->stop();
        }


    }

}
