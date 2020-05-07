<?php
namespace App\HttpController\Common;

use EasySwoole\EasySwoole\Logger;
use Easyswoole\Pool\Manager;
use EasySwoole\Http\AbstractInterface\Controller;
use EasySwoole\HttpClient\HttpClient;
use Exception;

class Base extends Controller
{
    //所有方法都需要登录才能访问(true是/false否)
    protected $all_login= true;
    //不需要登录访问的方法
    protected $no_need_login = [];
    //需要登录访问的方法
    protected $need_login = [];

    public function onrequest(?string $action):?bool
    {
        // $this->response()->write($action);
        return true;
    }

    public function index()
    {
        $a = $this->request()->getRequestParam('a');
        $b = $this->request()->getRequestParam('b');
        $this->response()->write($a.'-b:'.$b);
    }

    public function curl(string $url,?string $method = "GET",?array $param = [],?array $cookie = [],?int $timeout = 5)
    {
        $matches = parse_url($url);
        $scheme = $matches['scheme'];
        $host = $matches['host'];
        $path = $matches ['path'] . (isset($matches ['query']) ? '?' . $matches ['query'] : '') ?? '/';
        if($scheme == 'https'){
            $port = $matches ['port'] ?? 443;
        }else{
            $port = $matches ['port'] ?? 80;
        }
        $url = $scheme . '://' . $host  . ':' . $port . $path;
        
        $client = new HttpClient();
        if($method == 'POST') {
            $client->post($param);
        }elseif($method == 'GET') {
            $url .= http_build_query($param);
        }
        
        $client->setUrl($url);
        $client->addCookies($cookie);
        $client->setEnableSSL(true);            //ssl
        $client->setSslVerifyPeer(true,true);   //ssl
        $client->setMethod($method);
        $client->setTimeout($timeout);
        $client->setConnectTimeout(3);
        $client->setHeader('User-Agent','Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/38.0.2125.122 Safari/537.36 SE 2.X MetaSr 1.0');

        $response = $client->get();
        $res_code = $response->getStatusCode();
        $err_code = $response->getErrCode();
        $err_msg = $response->getErrMsg();
    
        if($res_code != 200 || $err_msg || $err_code) {
            throw new Exception("$method $url $err_code:$err_msg".PHP_EOL);
            return;
        }else{
            return $response->getBody();
        }
    }

    public function onException(\Throwable $throwable):void
    {
        Logger::getInstance()->error($throwable->getMessage());
    }

    public function info()
    {
        $url = 'https://47.107.45.21:9601/common/base/index';
        // $url ='https://apimanage.qianniuhd.com/active?product=100906&channel=800021&idfa=6D722711-DE09-4936-B797-C3957ECC63B7&ip=119.133.252.88&version=13.3.1&device=iPhone8%2C1&words=%E8%AF%86%E5%AD%97&udid=f9e52ff96f094b2b05852538b1033cb840459480';
        $a = $this->curl($url);
        $this->response()->write($a);
    }


    /**
     * 失败返回值
     */
    public function error($message)
    {
        $this->response()->write(json_encode(['data'=>0,'info'=>$message,'status'=>0]));
    }
}
