<?php
namespace App\HttpController\Home;

use App\HttpController\Common\Base;
use EasySwoole\Pool\Manager;
use App\Models\Logic\QcLogic;
use EasySwoole\Component\Di;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\FastCache\Cache;
use App\Vendor\ip\ip;

class Union extends Base
{
    public function testindex()
    {
	$this->writeJson(200,['blance'=>'hello','name'=>'word'],'请求成功');
    }
    public function onrequest(?string $action):bool
    {
        return true;
    }

    public function qc()
    {
        $main_db = Manager::getInstance()->get("main_db")->defer();     //从连接池获取数据
        // $main_db->queryBuilder()->insert('task_main',['addres'=>4100029,'name'=>1110,'age'=>20,'id'=>3]);
        // $main_db->execBuilder();
        // $main_db->queryBuilder()->where('id',3)->update('task_main',['addres'=>101]);
        // $main_db->execBuilder();
        #$main_db->queryBuilder()->fields('adname')->where('id',285280,'>')->get('task_main');                      //sql预备
        #$this->writeJson(200,array_column($main_db->execBuilder(),'adname'),'success');
        
        // var_dump($main_db->queryBuilder()->get('task_main')->execBuilder());                               //获取执行结果
        // Manager::getInstance()->get("main_db")->recycleObj($main_db);    //回收对象到连接池
        #return;
        
        $this->response()->withHeader('Content-type','application/json;charset=utf-8');
        $param = $this->request()->getRequestParam();
        //校验接口参数
        $main_db->queryBuilder()->fields('source')->get("wf_source");
        $wf_source = $main_db->execBuilder();
	$msg = QcLogic::getInstance()->checkParam($param,array_column($wf_source,'source'));
   	if($msg !== true) {
            $this->error($msg);
        }
        //检验开关是否打开
        $main_db->queryBuilder()->fields('wf_switch')->where('id',$param['appiosid'])->get('app_ios');
        $wf_switch = $main_db->execBuilder()[0]['wf_switch'];
        if($wf_switch == 2) {
            $this->error('当前任务已暂停');
        }
        //查询是否配置了外放接口
        $main_db->queryBuilder()->where('appiosid',$param['appiosid'])->where('s_time',time(),'<')->where('e_time',time(),'>')->get('wf_watch');
        $wf_watch = $main_db->execBuilder();
        $wf_res = QcLogic::getInstance()->checkWf($wf_watch,$param);
	if($wf_res !== true) {
            $this->error($wf_res);
        }
        //是否需要校验idfa
        if($wf_watch[0]['is_checkidfa'] == 1 && $param['source'] != 'fift') {
            $main_db->queryBuilder()->where('idfa',$param['idfa'])->getOne('reyun_log');
            $reyun_log = $main_db->execBuilder()[0];
            if(empty($reyun_log)) {
                $reyun_res = QcLogic::getInstance()->checkIdfa($param['idfa']);
                if($reyun_res !== true) {
                    $new_data = ['idfa'=>$param['idfa'],'res'=>$reyun_res,'addtime'=>time(),'type'=>1,'is_out'=>1,'is_abnormal'=>1,'adddate'=>date('Y-m-d',time()),'source'=>$param['source'],'appiosid'=>$param['appiosid']];
                    $main_db->queryBuilder()->insert('reyun_log',$new_data);
                    $main_db->execBuilder();
                    return json_encode([$param['idfa'] => 1]);
                }
            }else{
                return json_encode([$param['idfa'] => 1]);
            }
        }
        //缓存该应用
        $appios = Cache::getInstance()->get('appios_'.$param['appiosid']);
        if(empty($appios)) {
            $main_db->queryBuilder()->where('id',$param['appiosid'])->getOne('app_ios');
            $appios = $main_db->execBuilder()[0];
            Cache::getInstance()->set('app_ios'.$param['appiosid'],$appios,60);
        }
        // $appios = Cache::getInstance()->get('appios_'.$param['appiosid']) ?: ($appios_db = $main_db->queryBuilder()->where('id',$param['appiosid'])->getOne('app_ios')->execBuilder()).(Cache::getInstance()->set('app_ios'.$param['appiosid'],$appios_db,60));
        if(empty($appios)) {
            $this->error('参数错误！');
        }
        //对qc参数进行处理。格式处理及必要的广告主逻辑处理
        $qcdeal_res = QcLogic::getInstance()->dealWithParam($appios,$param,$main_db);
        if(!$qcdeal_res['flag']) {
            $this->error($qcdeal_res['msg']);
        }
        $qc_param = $qcdeal_res['msg'];
	//缓存该应用
        $qcdbconfig = Cache::getInstance()->get('qcdbconfig'.$appios['boundid']);
        if(empty($qcdbconfig)) {
            $main_db->queryBuilder()->where('pgname',$appios['boundid'])->getOne('qdbconfig');
            $qcdbconfig = $main_db->execBuilder()[0];
            Cache::getInstance()->set('qcdbconfig'.$appios['boundid'],$qcdbconfig,60);
        }
        // $qcdbconfig = Cache::getInstance()->get('qcdbconfig'.$appios['boundid']) ?: ($qcdbconfig_db = $main_db->queryBuilder()->where('pgname',$appios['boundid'])->getOne('qdbconfig')->execBuilder()).(Cache::getInstance()->set('qcdbconfig'.$appios['boundid'],$qcdbconfig_db,60));
        if(empty($qcdbconfig)) {
            $this->error("该程序为配置好去重的表格");
        }
        //链接去重表
        $quchong = Manager::getInstance()->get($qcdbconfig['name'])->defer();
        $quchong->queryBuilder()->raw('select COLUMN_NAME from information_schema.columns where table_name=?',[$qcdbconfig['tablename']]);
        $fields = array_column($quchong->execBuilder(),'COLUMN_NAME');
        if(!in_array('source',$fields)) {
            $this->error("该应用暂不支持外放");
        }
        //开始去重。（内部去重都要走）
        $quchong->queryBuilder()->fields(['idfa','installed','localinstalled','fid','addtasktime','source'])->where('idfa',$param['idfa'])->getOne($qcdbconfig['tablename']);
        $systemidfas = $quchong->execBuilder()[0];
        $out = ((!empty($qc_param['qc_url']) || !empty($qc_param['qc_fun'])) && !empty($qc_param['qc_number']) && !empty($qc_param['pattern'])) ?: false;
	//先走内部去重(尝试融合内部和外部)
	if(empty($systemidfas)) {
            if($out) { //配置了去重接口的
                $waibu_res = QcLogic::getInstance()->waibuQc($qc_param,$param['idfa']);
		        if($waibu_res['return']['falg'] === false) {
                    $this->response()->write(json_encode(["data"=>0,"info"=>$waibu_res['return']['msg'],"status"=> 0]));
                }else{
                    $installed = $waibu_res["return"]['msg'][$param['idfa']]; 
                    //插入qc_log表
                    $qc_data = ['appiosid'=>$param['id'],'result'=>serialize($waibu_res['return']['msg']),'boundid'=>$appios['boundid'],'url'=>$waibu_res["sendurl"],'response'=>$waibu_res["urlout"].'_'.$param['source'],'time'=>time(),'idfa'=>$param['idfa'],'installed'=>$installed];
                    $main_db->queryBuilder()->insert($qcdbconfig['tablename'],$qc_data);
                    $main_db->execBuilder();
                    //加入新数据
                    $adddatas = ["fid" => 0, "tuserid" => 0, "idfa" => $param['idfa'], "checktime" => time(), "lchecktime" => 0, "localinstalled" => 0, "installed" => $installed, "tasking" => 0, "addtasktime" => time(),'source'=>$param['source'],'udid'=>$param['udid']];
                    $quchong->queryBuilder()->insert($qcdbconfig['tablename'],$adddatas);
                    $insert_res = $quchong->execBuilder();
                    if(!$insert_res) {
                        $this->response()->write(json_encode(["data"=>0,"info"=>"error","status"=> 0]));
                    }
                }
                $this->response()->write(json_encode($waibu_res["return"]['msg']));
            }else{  //未配置去重接口直接返回成功
                $insert_data = ["fid" => 0, "tuserid" => 0, "idfa" => $param['idfa'], "checktime" => 0, "lchecktime" => 0, "localinstalled" => 0, "installed" => 0, "tasking" => 0, "addtasktime" => time(),'source'=>$param['source'],'udid'=>$param['udid']];
                $quchong->queryBuilder()->insert($qcdbconfig['tablename'],$insert_data);
                $insert_res = $quchong->execBuilder();
                if(!$insert_res) {
                    $this->response()->write(json_encode(["data"=>0,"info"=>"error","status"=> 0]));
                }
                $this->response()->write(json_encode([$param['idfa'] => 0]));
            }
        }else{
            //如果三个鉴别完成的字段都通过
            if($systemidfas['installed'] == 0 && $systemidfas['localinstalled'] == 0 && $systemidfas['fid'] == 0) {
                if(time() > $systemidfas['addtasktime'] + 3600) {
                    if($out) {
                        //外部去重
                        $waibu_res = QcLogic::getInstance()->waibuQc($param['idfa']);
                        if($waibu_res['return']['falg'] === false) {
                            $this->response()->write(json_encode(["data"=>0,"info"=>$waibu_res['return']['msg'],"status"=> 0]));
                        }else{
                            //插入qc_log表
                            $qc_data = ['appiosid'=>$param['id'],'result'=>serialize($waibu_res['return']['msg']),'boundid'=>$appios['boundid'],'url'=>$waibu_res["sendurl"],'response'=>$waibu_res["urlout"].'_'.$param['source'],'time'=>time(),'idfa'=>$param['idfa'],'installed'=>$installed];
                            $main_db->queryBuilder()->insert($qcdbconfig['tablename'],$qc_data);
                            $main_db->execBuilder();
                            //更新数据
                            $installed = $waibu_res["return"]['msg'][$param['idfa']]; 
                            $adddatas = ["fid" => 0, "tuserid" => 0, "idfa" => $param['idfa'], "checktime" => time(), "lchecktime" => 0, "localinstalled" => 0, "installed" => $installed, "tasking" => 0, "addtasktime" => time(),'source'=>$param['source'],'udid'=>$param['udid']];
                            $quchong->queryBuilder()->where('idfa',$param['idfa'])->update($qcdbconfig['tablename'],$adddatas);
                            $insert_res = $quchong->execBuilder();
                            if(!$insert_res) {
                                $this->response()->write(json_encode(["data"=>0,"info"=>"error","status"=> 0]));
                            }
                        }
                        $this->response()->write(json_encode($waibu_res["return"]['msg']));
                    }else{
                        $quchong->queryBuilder()->where('idfa',$param['idfa'])->update($qcdbconfig['tablename'],['source'=>$param['source'],'addtasktime'=>time(),'udid'=>$param['udid']]);
                        $quchong->execBuilder();
                        $this->response()->write(json_encode([$param['idfa'] => 0]));
                    }
                }else{
                    if($systemidfas['source'] != 'aishenma' && $systemidfas['source'] == $param['source']) {
                        $quchong->queryBuilder()->where('idfa',$param['idfa'])->update($qcdbconfig['tablename'],['addtasktime'=>time()]);
                        $quchong->execBuilder();
                        $this->response()->write(json_encode([$param['idfa'] => 0]));
                    }else{
                        Logger::getInstance()->info(date('YmdHim').":".$qcdbconfig['tablename'].":".$param['idfa']);
                        $this->response()->write(json_encode([$param['idfa'] => 1]));
                    }
                }
            }else{
                if($systemidfas['installed'] == 0 && $systemidfas['localinstalled'] == 0 && $systemidfas['fid'] != 0 ) {
                    Logger::getInstance()->info(date('YmdHim').":".$qcdbconfig['tablename'].":".$param['idfa']);
                }
                $this->response()->write(json_encode([$param['idfa'] => 1]));
            }
        }
    }
}
