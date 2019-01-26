<?php
namespace Cg\Jobs;

class Job1 extends JobAbstract{


    public $job_id = 1;

    public function logic($worker)
    {
        sleep(3);


        $this->complete();
    }

}