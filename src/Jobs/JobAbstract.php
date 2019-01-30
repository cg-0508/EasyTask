<?php
namespace Cg\Jobs;
use Cg\Worker\Worker;

abstract class JobAbstract {

    /**
     * @var JOB ID
     */
    public $job_id;
    
    /**
     * 单个Job进程数量
     */
    public $count = 1;

    /**
     * Job名称
     */
    public $job_name = '';

    /**
     * job的业务代码
     * @param $worker
     * @return mixed
     */
    abstract public function logic(Worker $worker);



}