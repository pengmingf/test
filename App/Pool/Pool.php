<?php
namespace App\Pool;

use App\Pool\MysqlPool;
use EasySwoole\Pool\Config;
use EasySwoole\Pool\Manager;
use EasySwoole\Mysqli\Config as MysqlConfig;
use EasySwoole\Mysqli\Client;

class Pool
{
    public static function register_pool()
    {
        $config = new Config();

        $mysqlConfig1 = new MysqlConfig(\EasySwoole\EasySwoole\Config::getInstance()->getConf('lanmao.main_db'));
        $mysqlConfig2 = new MysqlConfig(\EasySwoole\EasySwoole\Config::getInstance()->getConf('lanmao.lanmaoqc1_db'));
        $mysqlConfig3 = new MysqlConfig(\EasySwoole\EasySwoole\Config::getInstance()->getConf('lanmao.lanmaoqc2_db'));
        $mysqlConfig4 = new MysqlConfig(\EasySwoole\EasySwoole\Config::getInstance()->getConf('lanmao.lanmaoqc3_db'));

        Manager::getInstance()->register(new MysqlPool($config,$mysqlConfig1),'main_db');
        Manager::getInstance()->register(new MysqlPool($config,$mysqlConfig2),'lanmaoqc1_db');
        Manager::getInstance()->register(new MysqlPool($config,$mysqlConfig3),'lanmaoqc2_db');
        Manager::getInstance()->register(new MysqlPool($config,$mysqlConfig4),'lanmaoqc3_db');
    }
}