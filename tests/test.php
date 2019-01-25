<?php
use Cg\Jobs;
use Cg\Jobs\Job1;
use Cg\Jobs\Job2;

require_once __DIR__ . '/../vendor/autoload.php';

$server = new \Cg\Worker\WorkerServer();

$server->deamon = false;

// 添加任务
$server->jobs = [
    new Job1, new Job2
];


// 开始执行
$server->run();