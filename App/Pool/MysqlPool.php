<?php
namespace App\Pool;

use EasySwoole\Pool\AbstractPool;
use EasySwoole\Mysqli\Config as MysqliConfig;
use EasySwoole\Pool\Config;
use EasySwoole\Mysqli\Client;

class MysqlPool extends AbstractPool
{
    protected $mysqlConfig;
    public function __construct(Config $conf,MysqliConfig $mysqlConfig)
    {
        parent::__construct($conf);
        $this->mysqlConfig = $mysqlConfig;
    }

    protected function createObject()
    {
        //根据传入的redis配置进行new 一个redis
        $client = new Client($this->mysqlConfig);
        return $client;
    }
}