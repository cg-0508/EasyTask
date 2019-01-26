<?php
namespace Cg\Jobs;

class Job1 extends JobAbstract{



    public function logic($worker)
    {
        sleep(1);



        $this->is_fin = 1;
    }

}