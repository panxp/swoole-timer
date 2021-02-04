<?php

class Redisdb
{

    public $connect = null;

    public function getRedis()
    {
        if (!$this->connect) {
            $this->connect = new Swoole\Coroutine\Redis();
            $this->connect->connect("redis", 6379);
            //$this->connect->connect("41.106.118.56", 6379);
        }
        return $this->connect;
    }

}