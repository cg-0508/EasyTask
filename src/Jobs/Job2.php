<?php
namespace Cg\Jobs;

use Cg\Jobs\JobAbstract;

class Job2 extends JobAbstract{

    public function logic($worker)
    {
        sleep(5);
        echo 'job2';
        file_put_contents('./b.txt', 'job2');
    }

}