<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

include "./vendor/autoload.php";
require_once './Request.php';
require_once './Redisdb.php';
$http   = new Swoole\Http\Server('0.0.0.0', 9501);
$logger = new Logger('channel-timer');
$logger->pushHandler(new StreamHandler('./logs/timer.log', Logger::DEBUG));

$http->on('request', function ($request, $response) use ($logger) {
    $path = explode('/', trim($request->server['request_uri'], '/'));
    //$request->get, $request->post
    $redisdb = new Redisdb();
    $redis   = $redisdb->getRedis();
    if (in_array('save', $path)) {
        $post = json_decode($request->rawContent(), true);
        $logger->info('postData', $post);
        $taskId           = uniqid(mt_rand(), true);
        $data             = [
            'method' => $post['method'],
            'url' => $post['url'],
            'params' => $post['params'],
            'headers' => $post['headers'],
            'run_time' => $post['run_time']
        ];
        //$data['run_time'] = time() + 30;

        $currentTime = time();
        $runLeftTime = $data['run_time'] - $currentTime;
        if ($runLeftTime > 0) {
            $data['task_id']  = $taskId;
            $timerId          = swoole_timer_after($runLeftTime * 1000, function () use ($data) {
                $req = new Request();
                $req->handler($data);
            });
            $data['timer_id'] = $timerId;
            $redis->hSet('delayTask', $taskId, json_encode($data));
            $logger->info('saveToRedis', $data);
        }
    }
    if (in_array('delete', $path)) {
        $taskId = $path[1];
        $task   = $redis->hGet("delayTask", $taskId);
        if ($task) {
            $task = json_decode($task, true);
            $redis->hDel("delayTask", $taskId);
            swoole_timer_clear($task['timer_id']);
            $timerId = $task['timer_id'];
        }
    }
    $response->header("Content-Type", "application/json; charset=utf-8");
    $response->end(json_encode(['timer_id' => $timerId]));
});

$http->on("start", function ($server) use ($logger) {
    echo "Swoole http server is started at http://127.0.0.1:9501\n";
    go(function () use ($logger) {
        echo "recover redis data\n";
        $redisdb = new Redisdb();
        $redis   = $redisdb->getRedis();
        $logger->debug('redis connect: ', get_class_methods($redis));
        $tasks   = $redis->hKeys("delayTask");
        $request = new Request();
        if ($tasks) {
            $logger->debug('allTaskIds', $tasks);
            foreach ($tasks as $taskId) {
                $task = $redis->hGet("delayTask", $taskId);
                if ($task) {
                    $task            = json_decode($task, true);
                    $task['task_id'] = $taskId;
                    $currentTime     = time();
                    $runLeftTime     = $task['run_time'] - $currentTime;
                    if ($runLeftTime > 0) {
                        $timerId          = swoole_timer_after($runLeftTime * 1000, function () use ($task, $request) {
                            $request->handler($task);
                        });
                        $task['timer_id'] = $timerId;
                        $redis->hSet('delayTask', $taskId, json_encode($task));
                        $logger->info('initTimer', $task);
                    } else {
                        $redis->hDel('delayTask', $taskId);
                    }
                }

            }
        }
    });

});

$http->start();

//POST http://localhost:9501/save
//{
//    "method":"GET",
//    "run_time":"1629528890",
//    "url":"http://local.chinafuturelink.org/fu/api/news?page=1&limit=10",
//    "headers":{"Content-type":"application/json"},
//    "params" : {"title":"title"}
//}


