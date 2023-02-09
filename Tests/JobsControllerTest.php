<?php

require_once __DIR__ . "/../layers/DbLayer.php";
require_once __DIR__ . "/../layers/FileSystemLayer.php";
require_once __DIR__ . "/../controllers/JobsController.php";
require_once __DIR__ . "/../models/JobModel.php";

class MockJobDbLayer implements IJobDbLayer{
    private $jobs = [];
    function getAllJobs()
    {
        return $this->jobs;
    }

    function getRunningJobs()
    {
        return array_filter($this->jobs , function ($job) {
            return $job->state == 'running';
        });
    }

    function getQueuedJobs()
    {
        return array_filter($this->jobs , function($job){
            return $job->state != 'running' && strtotime($job->nextRun)<time();
        });
    }

    function getJobById($id)
    {
        return array_filter($this->jobs , function($job) use ($id){
            return $job->id = $id;
        });
    }

    function createJob($job)
    {
        $this->jobs[] = $job;
    }

    function updateJob($job)
    {
        $this->jobs = array_filter($this->jobs , function ($j) use ($job) {
            return $job->id != $j->id;
        });
        $this->createJob($job);
    }

    function updateStartJob($job)
    {
        array_walk($this->jobs , function (&$j) use ($job) {
            if($job->id == $j->id){
                $j->lastStart = date('Y-m-d H:i:s',time());
            }
        });
    }

    function updateEndJob($job)
    {
        array_walk($this->jobs , function (&$j) use ($job) {
            if($job->id == $j->id){
                $j->lastEnd = date('Y-m-d H:i:s',time());
            }
        });
    }

    function getNextJobsToRun($noOfJobs)
    {
        return array_slice($this->getQueuedJobs(),0,$noOfJobs);
    }
    function __destruct()
    {
        // TODO: Implement __destruct() method.
    }
}

class JobsControllerTest extends PHPUnit_Framework_TestCase
{
    private $jobs = [];
    /**
     * @var JobsController
     */
    private $jobsController;
    private $fileSystemLayer;
    private $dbLayer;
    function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->fileSystemLayer = new FileSystemLayer();
        $this->jobsController = new JobsController(null,$this->fileSystemLayer);
        Job::$dbLayerClass = MockJobDbLayer::class; //Job class will globally use mockJobDbLayer
        $this->jobs = $this->generate100TestJobs();
        $this->dbLayer = new MockJobDbLayer();

    }

    private function getTestJob($isDurationLong=false){
        $exampleJob = new Job($this->dbLayer);
        $exampleJob->name = "TestJob" . rand(0, 100);
        $duration = $isDurationLong?rand(100,200):rand(5,8);
        $exampleJob->command = "sleep ".$duration." && echo $exampleJob->name";
        $exampleJob->interval = rand(5,10);
        $exampleJob->state = 'idle';
        return $exampleJob;
    }
    private function generate100TestJobs()
    {
        $result = [];
        for($i=0;$i<100;$i++){
            // %30 of the tasks will be long duration
            $isDurationLong = filter_var(rand(1,100)<30,FILTER_VALIDATE_BOOLEAN);
            $testJob = $this->getTestJob($isDurationLong);
            $testJob->id = $i+1;
            $result[] = $testJob;
        }
        return $result;
    }
    public function testStartJob()
    {
        //not supported on windows
        //same job execution test
        $process1 = $this->jobsController->startJob($this->jobs[0]);
        $process2 = $this->jobsController->startJob($this->jobs[0]);

        //same jobs shouldn't work together
        \PHPUnit\Framework\Assert::isFalse(!$process1->isRunning() && $process2->isRunning());

        $job1 = $this->jobs[1];
        $job2 = $this->jobs[2];
        $job2->command = $job1->command;
        $job2->interval = $job1->interval;

        $process1 = $this->jobsController->startJob($job1);
        $process2 = $this->jobsController->startJob($job2);

        //same types shouldn't work together
        \PHPUnit\Framework\Assert::isFalse(!$process1->isRunning() && $process2->isRunning());






    }

    public function testKill()
    {
        $job = $this->jobs[10];

        $jobIdentifier = $job->getJobIdentifier();
        $this->jobsController->kill($job->id);

        //because no running jobs. file creation should be skipped.
        \PHPUnit\Framework\Assert::assertFileNotExists(config::$killJobSignalFolder.$jobIdentifier.".kill");
    }

    public function testCheckTerminateRequest()
    {
        $mockFileName = "aaaaaaa.kill";
        file_put_contents(Config::$killJobSignalFolder."/$mockFileName","");
        $this->jobsController->checkTerminateRequest();
        \PHPUnit\Framework\Assert::assertFileNotExists(config::$killJobSignalFolder.$mockFileName);

    }

    public function testIsJobRunning()
    {
        //not supported in windows
        $job = $this->jobs[3];
        $process1 = &$this->jobsController->startJob($job);

        \PHPUnit\Framework\Assert::assertSame($process1->isRunning(),$this->jobsController->isJobRunning($job));

    }

    public function testStatus()
    {

    }

    public function testCleanNonRunningJobs()
    {

    }
}
