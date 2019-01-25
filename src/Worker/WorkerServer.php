<?php

namespace Cg\Worker;

class WorkerServer extends Process{
    /**
     * 工作进程数量
     */
    public $worker_count = 1;

    public $master_pid;

    /**
     * 标准输出重定向的文件
     */
    public $std_out_file = __DIR__ . '/../logs/log.txt';
    /**
     * worker进程id
     */
    private $_worker_pids = [];

    /**
     * 是否以守护进程运行
     */
    public $deamon = false;

    public $jobs = [];

    /**
     * worker进程集合
     * @var array
     */
    public $workers = [];

    public function run()
    {
        $this->checkEnv();
        $this->init();
        $this->parseCommand();
        $this->hungup();
    }

    private function init()
    {
        $this->master_pid = posix_getpid();
    }

    protected function hungup($xxx = false)
    {
        while(1){
            pcntl_signal_dispatch();
            foreach($this->_worker_pids as $key => $pid){
                $res = pcntl_waitpid($pid, $status, WNOHANG);
                if($res > 0){
                    unset($this->_worker_pids[$key]);
                }
            }
            usleep(500000);
        }
    }

    public function installSignal()
    {
        // 平滑退出
        pcntl_signal(SIGINT, [__CLASS__, "handleSignal"], false);
        // 直接退出
        pcntl_signal(SIGTERM, [__CLASS__, "handleSignal"], false);
        // 查看进程状态
        pcntl_signal(SIGUSR1, [__CLASS__, "handleSignal"], false);
    }

    public function handleSignal($signal)
    {
        switch($signal){
            case SIGINT:
                // CTRL-C
                $this->stopAllWorkers();
                break;
            case SIGTERM:
            break;
            case SIGUSR1:
            
            break;
        }
    }


    public function parseCommand()
    {
        global $argv, $argc;
        if($argc < 2){
            exit("请输入：php 执行脚本 [ start stop status ]\n");
        }
        switch(trim($argv[1])){
            case "start": 
                $this->startServer();
            break;
            case "stop": 
                echo "stop\n";
            break;
            case "status": 
                echo "status\n";
            break;
            default: 
                exit("请输入：php 执行脚本 [ start stop status ]\n");
        }
    }


    private function startServer()
    {
        $this->createWorkers();
        $this->installSignal();
    }

    private function createWorkers()
    {
        if(count($this->jobs) < 1){
            exit("请添加任务！");
        }
        for($i = 0; $i < count($this->jobs); $i++){
            $this->fork($this->dispatch());
        }
    }


    private function fork($job)
    {
        $pid = pcntl_fork();
        if ($pid == -1) {
            exit("pcntl_fork faild.");
        }else if($pid == 0){
            // worker
            $worker = new Worker([
                'pid' => posix_getpid(),
            ]);
            $worker->hungup($job);
        }else{
            // master
            array_push($this->_worker_pids, $pid);
        }
    }

    /**
     * 向worker派遣任务
     */
    private function dispatch()
    {
        foreach($this->jobs as $key => $job){
            if ($job->is_run == 1){
                continue;
            }else{
                $job->is_run = 1;
                return $job;
            }
        }
    }


    public function stopAllWorkers()
    {
        foreach($this->_worker_pids as $pid){
            exec("kill -9 $pid");
        }
        exec("kill -9 {$this->master_pid}");
    }


    public function checkEnv()
    {
        if(php_sapi_name() != "cli"){
            exit("仅支持php-cli模式！\n");
        }
        if(PATH_SEPARATOR == ";"){
            exit("仅支持linux系统运行！\n");
        }
    }

}