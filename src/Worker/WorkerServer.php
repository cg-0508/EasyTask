<?php

namespace Cg\Worker;

use Cg\Jobs\JobAbstract;


class WorkerServer extends Process{

    /**
     * worker进程id
     */
    private $_worker_pids = [];

    /**
     * 是否以守护进程运行
     */
    public $deamon = false;

    /**
     * 任务集合
     */
    public $jobs = [];


    public function run()
    {
        $this->checkEnv();
        $this->parseCommand();
        $this->hungup();
    }

    /**
     * Master
     */
    public function hungup($xxx = false)
    {
        while(1){
            $res = pcntl_wait($status);
            pcntl_signal_dispatch();

            if($res > 0){
                // 进程退出
                echo '进程退出' . $res .PHP_EOL; 
                unset($this->_worker_pids[array_search($res, $this->_worker_pids)]);
            }else{
                if(count($this->_worker_pids) == 0){
                    echo '进程全部退出' .PHP_EOL; 
                    exit();
                }
            }
            usleep(100000);
        }
    }

    /**
     * 注册Master进程信号
     */
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
                // 向worker进程发送平滑退出信号
                foreach($this->_worker_pids as $pid){
                    if(!posix_kill($pid, SIGINT)){
                        exit("posix_kill faild.");
                    }
                }

                break;
            case SIGTERM:
                // drop 
                foreach($this->_worker_pids as $pid){
                    if(!posix_kill($pid, SIGKILL)){
                        exit("posix_kill faild.");
                    }
                }
            break;
            case SIGUSR1:
                //status


            break;
        }
    }


    public function parseCommand()
    {
        global $argv, $argc;
        if($argc < 2){
            exit("请输入：php 执行脚本 [ start stop status drop restart reload ]\n");
        }
        switch(trim($argv[1])){
            case "start": 
                $this->startServer();
            break;
            case "restart":
                $script = $argv[0]; 
                echo "正在重启...\n";
                if(file_exists($this->master_pid_file)){
                    exec("php $script drop && php $script start");exit;
                }else{
                    exec("php $script start");exit;
                }
                
            break;
            case "stop": 
                $master_pid = $this->getMasterPid();
                if(!posix_kill($master_pid, SIGINT)){
                    exit("posix_kill faild.");
                }
                while (1) {
                    $master_is_alive = posix_kill($master_pid, 0);
                    if ($master_is_alive) {
                        usleep(100000);
                        continue;
                    }
                    break;
                }
                exit("平滑停止成功！\n");
            break;
            case "drop":
                // 强制停止
                $master_pid = $this->getMasterPid();
                if(!posix_kill($master_pid, SIGTERM)){
                    exit("posix_kill faild.");
                }
                while (1) {
                    $master_is_alive = posix_kill($master_pid, 0);
                    if ($master_is_alive) {
                        usleep(100000);
                        continue;
                    }
                    break;
                }
                exit("强制停止成功！\n");
            break;
            case "reload":
                $script = $argv[0]; 
                echo "正在平滑重启...\n";
                if(file_exists($this->master_pid_file)){
                    exec("php $script stop && php $script start");exit;
                }else{
                    exec("php $script start");exit;
                }
                break;
            case "status":
                echo "status\n";
                posix_kill($this->getMasterPid(), SIGUSR1);
                exit();
            break;
            default: 
                exit("请输入：php 执行脚本 [ start stop status drop restart reload ]\n");
        }
    }


    public function startServer()
    {
        if($this->deamon){
            $this->deamonize();
        }
        $this->createWorkers();
        $this->saveMasterPid();
        $this->installSignal();
    }

    public function createWorkers()
    {
        if(count($this->jobs) < 1){
            exit("请添加任务！");
        }
        foreach($this->jobs as $job){
            if(!$job instanceof JobAbstract) exit('Job类必须继承自JobAbstract');
            for($i=0; $i < $job->count; $i++){
                $this->fork($job);
            }
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


    protected function stopAllWorkers()
    {
        if(!file_exists($this->master_pid_file)){
            exit("未运行！");
        }
        $this->clearPipe();
        foreach($this->_worker_pids as $pid){
            exec("kill -9 $pid");
        }
        exec("kill -9 {$this->getMasterPid()}");
        $this->clearMasterPid();
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

    /**
     * 开启守护进程
     */
    protected function deamonize() {
        $pid = pcntl_fork();
        if($pid < 0) {
            exit("pcntl_fork() failed\n");
        } else if($pid > 0) {
            exit(0);
        } else {
            $sid = posix_setsid();
            if($sid < 0) {
                exit("deamon failed\n");
            }
            umask(0);
            $pid = pcntl_fork();
            if($pid < 0) {
                exit("pcntl_fork() failed\n");
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

}