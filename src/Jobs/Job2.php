<?php
namespace Cg\Jobs;


class Job2 extends JobAbstract{

    static $int = 1;
    public function logic($worker)
    {
        sleep(2);




        $this->is_fin = 1;
    }

}