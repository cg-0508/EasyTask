<?php
namespace Cg\Jobs;


class Job2 extends JobAbstract{
    
    public $job_id = 2;

    public function logic($worker)
    {
        sleep(5);

        echo 'JOB2执行' . $worker->job_run_times . '次数' . PHP_EOL;


    }

}