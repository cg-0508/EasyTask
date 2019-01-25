<?php

namespace Cg\Worker;
use Cg\Jobs\Job;

abstract class Process{
    /**
     * 进程id
     */
    public $pid;

    public $pipe_path = __DIR__ . '/../../logs/easy_task.pipe';

    public $job;

    /**
     * 进程挂起时间
     */
    protected $hungup_time;

    public function __construct()
    {
        $this->hungup_time = time();

    }

    // 进程挂起
    abstract protected function hungup($xxx);

    public function makePipe(){

    }


}