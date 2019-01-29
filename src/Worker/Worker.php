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
        $this->installWorkerSignal();

        $this->job_start_time = time();

        while (1) {
            // 注册Worker信号
            pcntl_signal_dispatch();

            if($this->stoping == 1){
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


    public function installWorkerSignal()
    {
        // 平滑退出
        pcntl_signal(SIGINT, [__CLASS__, "handleWorkerSignal"], false);
        // 查看进程状态
        pcntl_signal(SIGUSR1, [__CLASS__, "handleWorkerSignal"], false);
    }

    public function handleWorkerSignal($signal)
    {
        switch($signal){
            case SIGINT:
                // 平滑退出信号
                $this->stoping = 1;
            break;
            case SIGUSR1:
                // 查看进程状态

            break;
        }
    }



}