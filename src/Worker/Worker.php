<?php
namespace Cg\Worker;



class Worker extends Process{

    /**
     * 单个Worker的Job运行次数
     */
    public $job_run_times = 0;

    public function __construct(array $config)
    {
        $this->pid = $config['pid'];
        $this->job = $config['job'];
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
                // 获取worker进程状态
                $pid = posix_getpid();
                $memory = round(memory_get_usage(true) / (1024 * 1024), 2) . "M";
                $run_times = $this->job_run_times;
                $time = time();
                $start_time = date("Y-m-d H:i:s", $this->hungup_time);
                $run_day = floor(($time - $this->hungup_time) / (24 * 60 * 60));
                $run_hour = floor((($time - $this->hungup_time) % (24 * 60 * 60)) / (60 * 60));
                $run_min = floor(((($time - $this->hungup_time) % (24 * 60 * 60)) % (60 * 60)) / 60);
                $status = $this->job->job_name . "\n";
                $status .= str_pad($pid, 10)
                    .str_pad($memory, 15)
                    .str_pad($run_times, 15)
                    .str_pad($start_time, 25)
                    .str_pad("{$run_day} 天 {$run_hour} 时 {$run_min} 分", 30)
                    ."\n";
                
                file_put_contents($this->status_file, $status, FILE_APPEND);
            break;
        }
    }



}