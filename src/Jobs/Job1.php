<?php
namespace Cg\Jobs;
use Cg\Jobs\JobAbstract;

class Job1 extends JobAbstract{

    public function logic($worker)
    {
        sleep(3);
        echo 'job1';
        file_put_contents('./a.txt', 'job1');
    }

}