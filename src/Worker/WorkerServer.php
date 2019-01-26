<?php

namespace Cg\Worker;

class WorkerServer extends Process{
    /**
     * 工作进程数量
     */
    public $worker_count = 1;


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
        $this->makePipe();
    }

    protected function hungup($xxx = false)
    {
        while(1){
            pcntl_signal_dispatch();
            foreach($this->_worker_pids as $key => $pid){
                $res = pcntl_waitpid($pid, $status, WNOHANG);
//                if($res > 0){
//                    unset($this->_worker_pids[$key]);
//                }
            }
//            $this->pipeWrite("sign");
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

    /**
     * 开启守护进程
     */
    public function deamonize() {
        $pid = pcntl_fork();
        if($pid < 0) {
            exit("pcntl_fork() failed\n");
        } else if($pid > 0) {
            exit(0);
        } else {
            $sid = posix_setsid();
            if($sid < 0) {
                exit("deamon failed\r\n");
            }
            umask(0);
            $pid = pcntl_fork();
            if($pid < 0) {
                exit("pcntl_fork() failed\r\n");
            } else if($pid > 0) {
                exit(0); // 结束第一子进程，第二子进程继续
            }
            $this->resetStd();
        }
    }
    /**
     * 重定向标准输出
     */
    protected function resetStd()
    {
        global $STDOUT, $STDERR;
        $output = $this->std_out_file;
        $handle = fopen($output, "a+");
        if ($handle) {
            unset($handle);
            @fclose(STDOUT);
            @fclose(STDERR);
            $STDOUT = fopen($output, "a");
            $STDERR = fopen($output, "a");
        } else {
            throw new \Exception('can not open stdOutput file '.$output);
        }
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
                $this->stopAllWorkers();
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
        if($this->deamon){
            $this->deamonize();
        }
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
        $this->saveMasterPid();
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
            $this->makePipe();
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
        $this->clearPipe();
        foreach($this->_worker_pids as $pid){
            exec("kill -9 $pid");
        }
        exec("kill -9 {$this->getMasterPid()}");
        die;
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