<?php

namespace Cg\Worker;

class WorkerServer extends Process{
    /**
     * 工作进程数量
     */
    public $worker_count = 1;

    public $master;

    /**
     * 标准输出重定向的文件
     */
    public $std_out_file = __DIR__ . '/../logs/log.txt';
    /**
     * worker进程id
     */
    private $_worker_pids = [];
    /**
     * Master进程id保存的文件
     */
    private $pid_file = __DIR__ . '/../../logs/master.pid';

    /**
     * 是否以守护进程运行
     */
    public $deamon = false;

    public $jobs = [];

    public $workers = [];

    public function run()
    {
        $this->checkEnv();
        $this->parseCommand();
        $this->installSignal();
        $this->hungup();
    }

    protected function hungup()
    {
        while(1){
            pcntl_signal_dispatch();
            foreach($this->workers as $worker){
                pcntl_waitpid($worker->pid, $status, WNOHANG);
                
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

            break;
            case SIGTERM:
                $this->stopAllWorkers();
            break;            
            case SIGUSR1:
            
            break;
        }
    }



    private function savePidToFile()
    {
        $pid = posix_getpid();
        $fp = fopen($this->pid_file, 'w');
        if($fp){
            fwrite($fp, $pid);
        }
        @fclose($fp);
    }

    private function removePidFile()
    {
        unlink($this->pid_file);
    }

    private function getMasterPid()
    {
        return file_get_contents($this->pid_file);
    }

    public function parseCommand()
    {
        global $argv, $argc;
        if($argc < 2){
            exit("请输入：php 执行脚本 [ start stop status ]\n");
        }
        switch(trim($argv[1])){
            case "start": 
                echo "start\n";
                if($this->isRunning()){
                    exit("运行中...");
                }
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
    }

    private function createWorkers()
    {
        if(count($this->jobs) < 1){
            exit("请添加任务！");
        }
        for($i = 0; $i < count($this->jobs); $i++){
            $this->fork();
        }

    }


    private function fork()
    {
        $pid = pcntl_fork();
        if ($pid == -1) {
            exit("pcntl_fork faild.");
        }else if($pid == 0){
            // worker
            $this->workers[] = $worker = new Worker([
                'pid' => $pid,
            ]);
            $job = $this->dispatch();
            echo $job;
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
        foreach($this->jobs as $job){
            if ($job->is_fun == 1){
                continue;
            }else{
                $job->is_fun = 1;
                return $job;
            }
        }
    }


    public function stopAllWorkers()
    {
        foreach($this->_worker_pids as $pid){
            exec("sudo kill $pid");
        }
    }

    public function isRunning()
    {
        return file_exists($this->pid_file);
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