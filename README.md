This is a Job Manager Application, allows to run several jobs concurrently , which several options

Options are provided in config.php file.
Default environment is "production".
Supported OS is linux

## Config options
##### dbServer
Hostname for db server (mysql)
##### dbName
Database name for db
##### dbPassword
Db password
##### checkNewJobsInterval
Amount of seconds to wait before adding a new job to the spot
##### maxRunningJobs
Maximum amount of jobs need to run concurrently
##### killJobSignalFolder
a writable temporary folder, to be able to send **Job Kill Request** to Job Manager

## Usage 
**main.php** is the main file to send several commands to Job Manager
To shorten the ``php main.php`` part, I advice to create alias
```
alias jobmanager="php <job-manager directory>/main.php"
```
#####
```
php main.php start
```
This will Job Manager to start working and start pulling Jobs from database.
```
php main.php kill <jobid>
```
To terminate a suspended job with a given id. State of the job will result as ```terminated``` on database.
```
php main.php status [detail]
``` 
To receive a status of the running jobs. (Amount of running jobs and waiting in the queue).
Adding "detail" option gives the list of the jobs with id, name and total runtime of the job.
In queue list, you will get id,name and delayed in seconds from running the job

_Tip:_
```watch "php main.php status"``` is a nice command so you can continuously the status of Job Manager

## Deploy
execute ./sql-scripts/create-db.sql

run ``composer install``
## Saving Jobs
Before saving jobs, sql script inside ./sql-scripts/create-db.sql should be executed.

After that make inserts with the values below

### Job values
##### name
Job Name
##### interval_minutes
The amount of interval job needs to run . Default is 0. This will make the job to be executed as soon as possible.

##### next_run
You can define when you want the job to run. During execution this field changes to the next execution time by calculating with ``next_run`` field.

##### command , command_params
Command and its params you want to run. You can enter here any command (bash commands or any scripts example : sh example.sh, php example.php, node example.js)

##### cleanup_script
Any command that you want to be executed after ``command`` part execution is finished.

##### recovery_script
Any command that you want to be executed if ``command`` or ``cleanup_script`` part executed unexpectedly.

##### state
Stores the state of the job. Initial value is _idle_

Other possible values : _success_ , _fail_ , _terminated_
##### last_start , last_end , last_error
Stores start and end datetime of the task . Filled automatically. last_end part is the end of all prosedures including cleanup and recovery
if any error occured will be saved to last_error field
