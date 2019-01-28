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

            // 检查worker进程是否超时运行
            if((time() - $this->hungup_time) >= $this->hungup_max_time){
                exit();
            }
            // 检查任务是否超时
            if((time() - $this->job_start_time) >= $this->job_max_run_seconds){
                exit();
            }
            

            // 执行业务逻辑
            $job->logic($this);
            
            // job运行次数+1
            $this->job_run_times++;




            // 检查是否有信号
//            if ($this->signal = $this->pipeRead()) {
//                $this->handleSign();
//            }


            usleep(50000);
        }
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