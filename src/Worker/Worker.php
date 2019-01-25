<?php
namespace Cg\Worker;

use Cg\Jobs\JobAbstract;


class Worker extends Process{
        
    

    public function __construct(array $config)
    {
        $this->pid = $config['pid'];
        
    }

    public function hungup(JobAbstract $job){
        while (1) {
            $job->logic($this);

            // 检查任务是否完成

            // 检查任务是否超时

            // 检查worker进程是否超时运行
            
            // 检查是否有信号

            // 增加活动worker

            usleep(50000);
        }
    }

}