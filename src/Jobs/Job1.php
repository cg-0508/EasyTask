<?php
namespace Cg\Jobs;

use Cg\Worker\Worker;

class Job1 extends JobAbstract{


    public $count = 2;

    public $job_name = 'job1';

    public function logic(Worker $worker)
    {
        sleep(2);
        //echo 'JOB1执行' . $worker->job_run_times . '次数' . PHP_EOL;


    }

}