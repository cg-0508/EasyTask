<?php

namespace Cg\Worker;


abstract class Process
{
    /**
     * 进程id
     */
    public $pid;

    public $log_path = __DIR__ . '/../Logs/log.txt';

    public $master_pid_file = __DIR__ . '/../Logs/master.pid';

    public $pipe_path = __DIR__ . '/../Logs/easy_task.pipe';
    /**
     * 标准输出重定向的文件
     */
    public $std_out_file = __DIR__ . '/../Logs/log.txt';

    public $job;
    public $signal;

    /**
     * 进程挂起时间
     */
    protected $hungup_time;

    /**
     * worker进程最大执行时间
     * @var
     */
    protected $hungup_max_time = 3600;

    /**
     *
     * Job开始执行时间.
     */
    public $job_start_time;

    /**
     * Job最长执行时间
     * @var int
     */
    public $job_max_run_seconds = 3600;



    public function __construct()
    {
        $this->hungup_time = time();
    }

    // 进程挂起
    abstract protected function hungup($xxx);

    public function makePipe()
    {
        posix_mkfifo($this->pipe_path, 0777);
    }

    public function pipeWrite($signal = '')
    {
        $pipe = fopen($this->pipe_path, 'w');
        fwrite($pipe, $signal . PHP_EOL);
        fclose($pipe);
    }

    public function pipeRead()
    {
        $workerPipe = fopen($this->pipe_path, 'r+');
        stream_set_blocking($workerPipe, false);
        $msg = fread($workerPipe, 1024);
        return $msg;
    }

    public function clearPipe()
    {
        exec("rm -f {$this->pipe_path}");
    }

    public function saveMasterPid()
    {
        $fp =  fopen($this->master_pid_file, 'w+');
        fwrite($fp, posix_getpid());
        @fclose($fp);
    }

    public function getMasterPid()
    {
        return file_get_contents($this->master_pid_file);
    }

    public function clearMasterPid()
    {
        if(file_exists($this->master_pid_file)){
            unlink($this->master_pid_file);
        }
    }

}