<?php
namespace Cg\Worker;



class Worker extends Process{
        
    
    public function __construct(array $config)
    {
        $this->pid = $config['pid'];
    }

    public function hungup($job){
        while (1) {
            if($job->is_fin == 1){
                exit();
            }else{
                $job->logic($this);
            }
            // 检查任务是否超时

            // 检查worker进程是否超时运行
            
            // 检查是否有信号

            // 增加活动worker

            usleep(50000);
        }
    }

}