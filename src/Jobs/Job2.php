<?php
namespace Cg\Jobs;


use Cg\Worker\Worker;

class Job2 extends JobAbstract{
        
    public $count = 3;
    public $job_name = 'job2';

    public function logic(Worker $worker)
    {
        sleep(3);

        //echo 'JOB2执行' . $worker->job_run_times . '次数' . PHP_EOL;


    }

}