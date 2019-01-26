<?php
namespace Cg\Jobs;


class Job2 extends JobAbstract{
    public $job_id = 2;
    public function logic($worker)
    {
        sleep(5);




        $this->complete();
    }

}