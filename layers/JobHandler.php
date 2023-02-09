<?php

require_once __DIR__ . "/../layers/DbLayer.php";
require_once __DIR__ . "/../models/JobModel.php";
class JobHandler
{
    /**
     * @var JobDbLayer
     */
    private $dbLayer;
    private $job;
    function __construct($dbLayer)
    {
        $this->dbLayer = $dbLayer;
    }

    function startJob($jobId,$isRawCommand=false){

        try {
            $command = '';
            if(!$isRawCommand) {
                //Get job object, or command
                $this->job = new Job($this->dbLayer);
                $this->job->getJob($jobId);
                //Update start time,update status.calculate nextRun if interval is not 0
                $this->job->updateStart();
                $this->job->state = 'running';
                //when calculating nextRun , before that check if nextRun is already before now() .
                if($this->job->interval>0) {
                    $this->job->calculateNextRun();
                }
                $this->job->saveJob();

                $command = $this->job->command.' '.$this->job->commandParams;
            }
            else{
                $command = $jobId;
            }

            //Do the command
            $output = [];
            $exitCode = 0;
            exec($command,$output,$exitCode);


            if(!$isRawCommand) {
                $cleanupCommand = $this->job->cleanupScript;
                $cleanupOutput = [];
                $cleanupExitCode = 0;
                $recoveryCommand = $this->job->recoveryScript;
                $recoveryOutput = [];
                $recoveryExitCode = 0;
                //Check if finished good
                if($exitCode == 0){
                    //if yes cleanup, and write last_end

                    if($cleanupCommand != '') {
                        exec($cleanupCommand, $cleanupOutput, $cleanupExitCode);
                    }
                }

                if ($exitCode + $cleanupExitCode != 0) {

                    if($recoveryCommand != '') {
                        exec($recoveryCommand, $recoveryOutput, $recoveryExitCode);
                    }
                    $this->job->state = 'fail';
                    $this->job->lastError = end($output); //last line in the console output
                }
                else{
                    $this->job->state = 'success';
                    $this->job->lastError = '';
                }
                //Update status and if success , success , if error , fail
                $this->job->saveJob();
                $this->job->updateEnd();
                $result = $exitCode+$cleanupExitCode+$recoveryExitCode == 0;
            }
            //only a command provided
            else{
                $this->job->lastError = end($output);
                $this->job->state = 'fail';
                $this->job->updateEnd();
                $this->job->saveJob();
                $result = false;
            }
            return $result;
        }
        catch(Exception $e){
            //in any exception write it to lastError, and update status=error
            $this->job->lastError = $e->getMessage();
            $this->job->state = 'fail';
            $this->job->updateEnd();
            $this->job->saveJob();
            return false;
        }


    }
    function executeCleanup(){
        if($this->job->cleanupScript != '') {
            $cleanupOutput = [];
            $cleanupExitCode = 0;
            exec($this->job->cleanupScript, $cleanupOutput, $cleanupExitCode);
        }
    }
    //in exits , we will be sure that connection will be closed
    function __destruct()
    {
        // execute cleanup
        $this->executeCleanup();
        @$this->dbLayer->__destruct();
    }


}
if($argc!=2)
{
    exit(1);
}
$job = $argv[1];
if(is_numeric($job)){
    //if job is numeric , then input is an id , we get the job info from db
    $dbLayer = new DbLayer();
    $jobDbLayer = new JobDbLayer($dbLayer);
    $jobHandler = new JobHandler($jobDbLayer);
    $result = $jobHandler->startJob($job);
}
else{
    //$job is a command
    $jobHandler = new JobHandler(null);
    $result = $jobHandler->startJob($job,$isRawCommand = true);
}
$exitCode = $result?0:2;

exit($exitCode); // finish with relevant exitcode