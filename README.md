## EasyTask
用 PHP 写的多进程的任务派遣系统,可以在后台执行一些需要重复执行的任务,采用Master-Worker模型。

- 服务启动(start)
- 平滑重启(reload)
- 状态监控(status)
- 强制重启(restart)
- 强制退出(drop)
- 平滑退出(stop)

## 入口文件

```php
// start.php
use Cg\Jobs\Job1;
use Cg\Jobs\Job2;

require_once __DIR__ . '/vendor/autoload.php';

$server = new \Cg\Worker\WorkerServer();

// 作为守护进程运行
$server->deamon = true;

// 添加任务
$server->jobs = [
    new Job1, new Job2
];

// 开始执行
$server->run();
```
## 运行
```
php start.php start < stop | drop | status | reload | restart >
```
## Job
```php
// Jobs/Job.php
namespace Cg\Jobs;

class Job extends JobAbstract{

    // 任务进程数量设置
    public $count = 2;
    
    // 任务名称
    public $job_name = 'Job_test';
    
    // 执行逻辑(将传入 Worker 对象)
    public function logic($worker)
    {
        sleep(2);
        // 若开启守护进程, 则输出的内容将写入 Logs\log.txt 文件中
        echo '执行' . $worker->job_run_times . '次数' . PHP_EOL;

    }

}
```

