<?php
namespace Cg\Jobs;

abstract class JobAbstract {

    
    public $is_run = 0;
    abstract public function logic($worker);



}