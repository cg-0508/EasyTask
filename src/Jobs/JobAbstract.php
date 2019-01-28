<?php
namespace Cg\Jobs;

abstract class JobAbstract {

    /**
     * @var JOB ID
     */
    public $job_id;
    /**
     * 是否正在执行
     * @var int
     */
    public $is_run = 0;

    /**
     * job的业务代码
     * @param $worker
     * @return mixed
     */
    abstract public function logic($worker);



}