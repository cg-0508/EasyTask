<?php

namespace Cg\Worker;

use Cg\Jobs\JobAbstract;


class WorkerServer extends Process{

    /**
     * worker进程id
     * [
     *    pid => $job,
     * ]
     * 
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
                $this->del_worker_pid($res);
            }else{
                if($this->get_worker_count() == 0){
                    $this->clearMasterPid();
                    exit();
                }
            }
            usleep(100000);
        }
    }

    private function del_worker_pid($pid){
        unset($this->_worker_pids[$pid]);
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
                foreach($this->_worker_pids as $pid => $job){
                    if(!posix_kill($pid, SIGINT)){
                        exit("posix_kill faild.");
                    }
                }
                break;
            case SIGTERM:
                // drop 
                foreach($this->_worker_pids as $pid => $job){
                    if(!posix_kill($pid, SIGKILL)){
                        exit("posix_kill faild.");
                    }
                }
            break;
            case SIGUSR1:
                //status
                // 获取master进程状态
                $pid = posix_getpid();
                $memory = round(memory_get_usage(true) / (1024 * 1024), 2) . "M";
                $time = time();
                $class_name = get_class($this);
                $start_time = date("Y-m-d H:i:s", $this->hungup_time);
                $run_day = floor(($time - $this->hungup_time) / (24 * 60 * 60));
                $run_hour = floor((($time - $this->hungup_time) % (24 * 60 * 60)) / (60 * 60));
                $run_min = floor(((($time - $this->hungup_time) % (24 * 60 * 60)) % (60 * 60)) / 60);

                $status = "Process [{$class_name}] 信息: \n"
                    ."-------------------------------- master进程状态 --------------------------------\n"
                    .str_pad("pid", 10)
                    .str_pad("占用内存", 19)
                    .str_pad("处理次数", 19)
                    .str_pad("开始时间", 29)
                    .str_pad("运行时间", 34)
                    ."\n"
                    .str_pad($pid, 10)
                    .str_pad($memory, 15)
                    .str_pad("--", 15)
                    .str_pad($start_time, 25)
                    .str_pad("{$run_day} 天 {$run_hour} 时 {$run_min} 分", 30)
                    ."\n"
                    . $this->get_worker_count() . " worker\n";

                file_put_contents($this->status_file, $status."\n");

                $json_workers_pid = json_encode($this->_worker_pids);
                file_put_contents($this->status_file, $json_workers_pid."\n", FILE_APPEND);

                foreach ($this->_worker_pids as $pid => $job) {
                    posix_kill($pid, SIGUSR1);
                }

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
                if(file_exists($this->master_pid_file)){
                    exit("server is running!");
                }
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
                if(file_exists($this->status_file)){
                    unlink($this->status_file);
                }
                $master_pid = $this->getMasterPid();
                if(!posix_kill($master_pid, SIGUSR1)){
                    exit("posix_kill faild.");
                }

                // master 7行 +  get_worker_count
                $worker_info_line =  (int)$this->get_jobs_sum_worker_count()*2;
                while(!file_exists($this->status_file) || count(file($this->status_file)) < (7 + $worker_info_line)) {
                    sleep(1);
                }
                $lines = file($this->status_file);
                $master_lines = array_slice($lines, 0, 5);
                echo implode("", $master_lines);
                echo "-------------------------------- Worker进程状态 --------------------------------";
                $worker_lines = array_slice($lines, - $this->get_jobs_sum_worker_count()*2);
                echo implode("", $worker_lines);
                unlink($this->status_file);
                exit();
            break;
            default: 
                exit("请输入：php 执行脚本 [ start stop status drop restart reload ]\n");
        }
    }


    public function get_worker_count(){
        return count($this->_worker_pids);
    }

    public function get_jobs_sum_worker_count()
    {
        $sum = 0;
        foreach($this->jobs as $job){
            $sum += $job->count;
        }
        return $sum;
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
            if($job->job_name == '') exit('job_name属性必填');
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
                'job' => $job
            ]);
            $worker->hungup($job);
        }else{
            // master
            $this->_worker_pids[$pid] = $job;
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