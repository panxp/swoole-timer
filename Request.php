<?php

use GuzzleHttp\Client;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
require_once './Redisdb.php';

class Request
{
    protected $client;
    protected $logger;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => '',
            'timeout' => 2.0,
        ]);
        $logger = new Logger('channel-timer');
        $this->logger = $logger->pushHandler(new StreamHandler('./logs/timer.log', Logger::DEBUG));
    }

    public function handler(array $task)
    {
        $method = strtolower($task['method']);
        if ($method == 'get') {
            $this->getData($task);
        }
        if ($method == 'post') {
            $this->postData($task);
        }
        if ($method == 'put') {
            $this->putData($task);
        }
        if ($method == 'delete') {
            $this->deleteData($task);
        }

        $redisdb = new Redisdb();
        $redis   = $redisdb->getRedis();
        $redis->hDel("delayTask", $task['task_id']);
    }


    public function getData($task)
    {

        $this->logger->info('execTimer', $task);
        echo "get==============".json_encode($task);
//        $result = $this->client->get($task['url'], ['query' => $task['params']]);
//        return $result->getBody()->getContents();
    }

    public function postData($task)
    {
        $body   = in_array('json', $task['headers']) ? 'json' : 'form_params';
        $result = $this->client->post($task['url'], [$body => $task['params']]);
        return $result->getBody()->getContents();
    }

    public function deleteData($task)
    {
        $body   = in_array('json', $task['headers']) ? 'json' : 'form_params';
        $result = $this->client->delete($task['url'], [$body => $task['params']]);
        return $result->getBody()->getContents();
    }

    public function putData($task)
    {
        $body   = in_array('json', $task['headers']) ? 'json' : 'form_params';
        $result = $this->client->put($task['url'], [$body => $task['params']]);
        return $result->getBody()->getContents();
    }


}