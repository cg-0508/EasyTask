<?php

require_once __DIR__ . '/../vendor/autoload.php';

$server = new \Cg\Worker\WorkerServer();

$server->worker_count = 2;
$server->deamon = false;





$server->run();