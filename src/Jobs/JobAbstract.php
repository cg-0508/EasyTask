<?php
namespace Cg\Jobs;

abstract class JobAbstract {

    
    public $is_run = 0;
    public $is_fin = 0;
    abstract public function logic($worker);



}