<?php
namespace Cg\Jobs;

class Job1 extends JobAbstract{


    public $job_id = 1;

    public $count = 2;

    public $job_name = 'job1';

    public function logic($worker)
    {
        sleep(2);
        echo 'JOB1执行' . $worker->job_run_times . '次数' . PHP_EOL;


    }

}