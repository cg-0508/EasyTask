<?php
namespace Cg\Worker;



class Worker extends Process{


    public $job_run_times = 0;

    public function __construct(array $config)
    {
        $this->pid = $config['pid'];
        parent::__construct();
    }


    /**
     * worker执行job
     * @param $job
     */
    public function hungup($job){
        $this->job_start_time = time();

        while (1) {
            // 注册Worker信号
            pcntl_signal_dispatch();
            echo 'start check stop..'.PHP_EOL;
            if($this->stoping == true){
                echo 'Worker接收到退出信号，'.PHP_EOL;
                break;
            }
            // 检查worker进程是否超时运行
            if((time() - $this->hungup_time) >= $this->hungup_max_time){
                break;
            }
            // 检查任务是否超时
            if((time() - $this->job_start_time) >= $this->job_max_run_seconds){
                break;
            }
            
            // 执行业务逻辑
            $job->logic($this);
            
            // job运行次数+1
            $this->job_run_times++;

            usleep(50000);
        }
        exit();
    }

    public function handleSign(){
        switch ($this->signal) {
            case 'reload':

                break;
            case 'stop':
                
                break;
            default:
                break;
        }
    }


}