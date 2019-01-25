<?php

namespace Cg\Worker;

class WorkerServer {
    /**
     * 工作进程数量
     */
    public $worker_count = 1;

    /**
     * 标准输出重定向的文件
     */
    public $std_out_file = __DIR__ . '/../logs/log.txt';
    /**
     * worker进程id
     */
    private $_worker_pids = [];

    private $pid_file = __DIR__ . '/../../logs/master.pid';

    /**
     * 是否以守护进程运行
     */
    public $deamon = false;

    public $start_time;

    public function run()
    {
        $this->checkEnv();
        $this->init();
        $this->parseCommand();
    }

    public function init()
    {
        $this->savePidToFile();
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