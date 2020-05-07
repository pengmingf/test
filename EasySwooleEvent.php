<?php
namespace EasySwoole\EasySwoole;


use EasySwoole\EasySwoole\Swoole\EventRegister;
use EasySwoole\EasySwoole\AbstractInterface\Event;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use EasySwoole\Component\Di;
use App\Pool\Pool;
use EasySwoole\Whoops\Handler\CallbackHandler;
use EasySwoole\Whoops\Handler\PrettyPageHandler;
use EasySwoole\Whoops\Run;
use EasySwoole\FastCache\Cache;

class EasySwooleEvent implements Event
{

    public static function initialize()
    {
        // TODO: Implement initialize() method.
        date_default_timezone_set('Asia/Shanghai');
        //连接池
        Pool::register_pool();

        if(\EasySwoole\EasySwoole\Core::getInstance()->isDev()){
            $whoops = new Run();
            $whoops->pushHandler(new PrettyPageHandler);  // 输出一个漂亮的页面
            $whoops->pushHandler(new CallbackHandler(function ($exception, $inspector, $run, $handle) {
                // 可以推进多个Handle 支持回调做更多后续处理
            }));
            $whoops->register();
        }
    }

    public static function mainServerCreate(EventRegister $register)
    {
        // 配置同上别忘了添加要检视的目录
        $hotReloadOptions = new \EasySwoole\HotReload\HotReloadOptions;
        $hotReload = new \EasySwoole\HotReload\HotReload($hotReloadOptions);
        $hotReloadOptions->setMonitorFolder([EASYSWOOLE_ROOT . '/App']);

        $server = ServerManager::getInstance()->getSwooleServer();
        $hotReload->attachToServer($server);
        // TODO: Implement mainServerCreate() method.
        if(\EasySwoole\EasySwoole\Core::getInstance()->isDev()){
            Run::attachTemplateRender(ServerManager::getInstance()->getSwooleServer());
        }

        Cache::getInstance()->setTempDir(EASYSWOOLE_TEMP_DIR)->attachToServer(ServerManager::getInstance()->getSwooleServer());
    }

    public static function onRequest(Request $request, Response $response): bool
    {
        // TODO: Implement onRequest() method.
        if(\EasySwoole\EasySwoole\Core::getInstance()->isDev()){
            Run::attachRequest($request, $response);
        }
        return true;
    }

    public static function afterRequest(Request $request, Response $response): void
    {
        // TODO: Implement afterAction() method.
    }
}