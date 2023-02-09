<?php
class Config {
    //default settings
    public static $dbServer = 'dbServer';
    public static $dbUsername = 'dbUsername';
    public static $dbPassword = 'dbPassword';
    public static $dbName = 'job_manager';
    public static $checkNewJobsInterval = 1;
    public static $maxRunningJobs = 20;
    public static $killJobSignalFolder = '/tmp/';
    function buildEnv(){
        $env_name = getenv("JOB_MANAGER_ENV");
        switch($env_name){
            default:
            case "production":
                error_reporting(E_ERROR);
                self::$dbServer = "job_manager";
                break;
            case "stage":
                self::$dbServer = "dbStageName";
                break;
                break;
        }
    }
}