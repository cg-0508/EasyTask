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
        $this->parseCommand();
        $this->hungup();
    }

    public function hungup($xxx = false)
    {
        while(1){
            pcntl_signal_dispatch();
            foreach($this->_worker_pids as $key => $pid){
                $res = pcntl_waitpid($pid, $status, WNOHANG);
                
                if($res > 0 || $res == -1){
                    echo '$res:' . $res . '|' .'status:' . $status . PHP_EOL;
                    unset($this->_worker_pids[$key]);
                }
                // if(count($this->_worker_pids) == 0){
                //     exit();
                // }
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
                echo 'worker 接收到平滑退出信号'.PHP_EOL;
                $this->stoping = true;
            break;
            case SIGUSR1:
                // 查看进程状态

            break;
        }
    }

    public function handleSignal($signal)
    {
        switch($signal){
            case SIGINT:
                // 向worker进程发送平滑退出信号
                foreach($this->_worker_pids as $pid){
                    echo '向worker进程发送平滑退出信号'.$pid.PHP_EOL;
                    posix_kill($pid, SIGINT);
                }
                break;
            case SIGTERM:
                // drop
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
                echo "start\n";
                $this->startServer();
            break;
            case "restart":
                $script = $argv[0]; 
                if(file_exists($this->master_pid_file)){
                    exec("php $script stop && php $script start");exit;
                }else{
                    exec("php $script start");exit;
                }
                
            break;
            case "stop": 
                echo "stop\n";
                $master_pid = $this->getMasterPid();
                posix_kill($master_pid, SIGINT);
                exit();
            break;
            case "drop":
                // 强制停止
                echo "drop\n";

                break;
            case "reload":
                echo "reload\n";

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
        $this->installSignal();
    }

    public function createWorkers()
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
            $this->installWorkerSignal();
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