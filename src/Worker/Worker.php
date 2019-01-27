<?php
namespace Cg\Worker;



class Worker extends Process{

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
        while (1) {
            if($job->is_fin == 1){
                // 任务已是完成状态
                exit();
            }else{
                $this->job_start_time = time();
                // 检查worker进程是否超时运行
                if(time() - $this->hungup_time >= $this->hungup_max_time){
                    exit();
                }
                // 检查任务是否超时
                if(time() - $this->job_max_run_seconds >= $this->job_max_run_seconds){
                    exit();
                }


                // 执行业务逻辑
                $job->logic($this);



            }



            // 检查是否有信号
//            if ($this->signal = $this->pipeRead()) {
//                $this->handleSign();
//            }

            // 增加活动worker

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