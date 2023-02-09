<?php
require_once __DIR__."/../config.php";
class DbLayer{
    protected $dbConnection;
    function __construct(){
        $this->connect();
    }
    function connect(){
        $dbServer = Config::$dbServer;
        $dbUsername = Config::$dbUsername;
        $dbPassword = Config::$dbPassword;
        $dbName = Config::$dbName;
        $this->dbConnection = new mysqli($dbServer,$dbUsername,$dbPassword,$dbName,3306);
        if($this->dbConnection->connect_errno){
            throw new Exception("DB Connection error: ".$this->dbConnection->connect_error);
        }
    }
    function disconnect(){
        $this->dbConnection->close();
    }
    public function execute($sqlQuery){
            $result = $this->dbConnection->query($sqlQuery);
            if(!$result){
                throw new Exception("DB query execute error: ".$this->dbConnection->error);
            }
            else if(is_bool($result)){
                //result is boolean true , no need to fetch
                return $result;
            }
            return $result->fetch_all(MYSQLI_ASSOC);
    }
    function __destruct()
    {
        @$this->disconnect();
    }
     

}
interface IJobDbLayer{
    function getAllJobs();
    function getRunningJobs();
    function getQueuedJobs();
    function getJobById($id);
    function createJob($job);
    function updateJob($job);
    function updateStartJob($job);
    function updateEndJob($job);
    function getNextJobsToRun($noOfJobs);
    function __destruct();

}
class JobDbLayer implements IJobDbLayer{
    /**
     * @var DbLayer
     */
    private $dbLayer;
    function __construct($dbConnection){
        $this->dbLayer = $dbConnection;
    }
    function getAllJobs(){
        return $this->dbLayer->execute("Select * from jobs");
    }
    function getRunningJobs(){
        return $this->dbLayer->execute("Select * from jobs where state='running'");
    }
    function getQueuedJobs(){
        return $this->dbLayer->execute("Select * from jobs where state<>'running' and next_run<now()");
    }
    function getJobById($id){
        return $this->dbLayer->execute("Select * from jobs where id = $id");
    }

    /**
     * @param $jobModel Job
     * @return mixed
     * @throws Exception
     */
    function createJob($jobModel){
        $query = "Insert into jobs (name,interval_minutes,command,command_params,cleanup_script,recovery_script)
        values ('$jobModel->name',$jobModel->interval,'$jobModel->command','$jobModel->commandParams','$jobModel->cleanupScript','$jobModel->recoveryScript')";
        return $this->dbLayer->execute($query);
    }

    /**
     * @param $jobModel Job
     * @return mixed
     */
    function updateJob($jobModel){
        $jobId = $jobModel->id;
        $query = "
        Update jobs set name = '$jobModel->name',interval_minutes=$jobModel->interval,command='$jobModel->command',command_params='$jobModel->commandParams',state='$jobModel->state',
        cleanup_script='$jobModel->cleanupScript',recovery_script='$jobModel->recoveryScript',next_run='$jobModel->nextRun'
        where id = $jobId
        ";
        return $this->dbLayer->execute($query);
    }
    function updateStartJob($jobModel){
        $jobId = $jobModel->id;
        $query = "update jobs set last_start=now() where id = $jobId";
        $this->dbLayer->execute($query);
    }
    function updateEndJob($jobModel){
        $jobId = $jobModel->id;
        $query = "update jobs set last_end=now() where id = $jobId";
        $this->dbLayer->execute($query);
    }
    function getNextJobsToRun($numberOfJobsToPull){
        $query = "Select * from jobs where next_run<now() and state <> 'running' order by next_run limit ".$numberOfJobsToPull;
        return $this->dbLayer->execute($query);
    }

    function __destruct()
    {
        @$this->dbLayer->__destruct();
    }

}