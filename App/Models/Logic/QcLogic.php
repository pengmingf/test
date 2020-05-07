<?php 
namespace App\Models\Logic;

use EasySwoole\Component\Singleton;
use EasySwoole\Validate\Validate;
use App\HttpController\Common\Base;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\Pool\Manager;
use App\Vendor\ip\ip;
use EasySwoole\ORM\Db\MysqliClient;

class QcLogic extends Base
{
    use Singleton;
    /**
     * 检查请求参数
     * @param array param 传入参数
     * @param array wf_source 外放渠道
     * @return true|string 错误提示信息
     */
    public function checkParam(array $param,array $wf_source)
    {
        $valitor = new Validate();
        // $wf_source = ['lanmao','moneyds'];
        $valitor->addColumn('source')->required('没有权限')->notEmpty('没有权限')->inArray($wf_source,false,'来源错误');
        $valitor->addColumn('udid')->required('udid参数缺失')->notEmpty('udid不能为空')->lengthMax(40,'udid格式错误');
        $valitor->addColumn('appiosid')->required('应用id不能为空')->notEmpty('应用id不能为空')->numeric('appiosid格式错误');
        $valitor->addColumn('idfa')->required('idfa参数缺失')->notEmpty('idfa参数不能为空')->length(36,'idfa格式错误');
        $valitor->addColumn('ip')->required('ip参数缺失')->notEmpty('ip参数不能为空')->isIp('ip格式错误');
        $valitor->addColumn('keywords')->required('keywords参数缺失')->notEmpty('keywords参数不能为空');
        $valitor->addColumn('os')->required('os参数缺失')->notEmpty('os参数不能为空');
        $valitor->addColumn('platform')->required('platform参数缺失')->notEmpty('platform参数不能为空');
        $bool = $valitor->validate($param);
        return $bool?true:$valitor->getError()->__toString();
    }

    /**
     * 检查产品是否外放
     * @param array wf 外放信息
     * @param array param 传入参数
     * @return true|string 错误信息
     */
    public function checkWf(array $wf,array $param)
    {
        if(empty($wf) && !in_array($param['source'],['moneyds','daxiasw','fift'])) {
            Logger::getInstance()->error(date('YmdHim')."notstart_qudao:".$param['source'].",id:".$param['appiosid'].',wf_watch'.$wf);
            return "非法请求!";
        }
        $sourcewf = $wf[0];
        foreach($sourcewf as $val) {
            $des = json_decode($val,true);
            if(in_array($param['source'],array_column($des,'source'))) {
                $sourcewfwatch = $val;
                break;
            }
        }
        $wfwatch=$sourcewfwatch;
        //任务进行中的，查看渠道是否满足
        if(!empty($wfwatch['description'])&&!empty(json_decode($wfwatch['description'],true))){
            $descriptions=json_decode($wfwatch['description'],true);
            if(!in_array($param['source'],array_column($descriptions,'source'))) {
                if (!in_array($param['source'], ['moneyds', 'dxshiwan', 'fift','mmpig'])) {
                    //日志记录这种请求的渠道信息
                    Logger::getInstance()->error(date('YmdHim') . "no_qudao:" . $param['source'] . ",id:" . $param['appiosid']);
                    return "非法请求";
                }
            }
        }
        return true;   
    }

    /**
     * idfa检测接口
     * @param string idfa 
     * @return true|string 异常idfa信息
     */
    public function checkIdfa(string $idfa)
    {
        $url = 'http://reyun.dxshiwan.com/index.php/Home/Apis/checkcheat?idfa='.$idfa.'&sign='.md5($idfa.'reyuncheck');
        $res = $this->curl($url);
        if(!empty($res)) {
            $result = json_decode($res,true);
            if($result['data'] == true) {
                return $res;
            }
        }
        return true;
    }

    /**
     * 检验适应广告主的特殊要求
     * @param array appios 参数值
     * @param array param 传入参数
     * @param object main_db 主库链接
     * @return array ['flag'=>false|true,'msg'=>'']
     */
    public function dealWithParam(array $appios,array $param,$main_db)
    {
        //先处理特殊广告主需求
        //七麦特殊ip判断
        if($appios['auser_id'] == 36) {
            $ip = new ip();
            $country = $ip->ip2addr($param['ip'])['country'];
            $limitcountry=substr($country,0,6).'省';
            $auserDay=date('Y-m-d').$appios['auser_id'];
            $main_db->where(['task_id'=>$param['appiosid'],'area'=>$limitcountry,'auser_day'=>$auserDay])->getOne('task_area_wf');
            $taskarea=$main_db->execBuilder()[0];
            if(!empty($taskarea)) {
                //比例
                if(($taskarea['num']/$taskarea['amount'])*100>5){
                    return ['flag'=>false,'msg'=>'ip超限'];
                }
            }
        }
        //处理qc参数
        $deal_param = $this->qcParam($appios['qc'],$param);
        //咪咕动漫特殊排重外放
        if($appios['id'] == 1634) {
            $deal_param['thridSystemRequestTime'] = date("YmdHis");
            $deal_param['thirdSystemAccount'] = 'fuje';
            $deal_param['data'] = json_encode(["idfa"=>$param['idfa'],"appId"=>'I100000002',"adId"=>'SRXB000002',"ip"=>'']);
            $deal_param['thridSystemMd5'] = strtoupper(md5($deal_param['data'].$deal_param['thirdSystemAccount'].'xJ3OYazZp'.$deal_param['thridSystemRequestTime']));
        }
        //boss直聘
        if($appios['id'] == 12047) {
            $sigData['v']=$deal_param['v'];
            $sigData['idfa']=$param['idfa'];
            $sigData['app_id']=$deal_param['app_id'];
            list($t1, $t2) = explode(' ', microtime());
            $micTime= (float)sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
            $configarray['req_time']=$micTime;
            $sigData['req_time']=$micTime;
            $sigData['uniqid']=$configarray['uniqid'];
            ksort($sigData);
            $sigStr='';
            foreach($sigData as $k=>$v){
                $sigStr.=$k.'='.$v;
            }
            $bsign='V2.0'.strtolower(md5('/api/zpaso/integralWall/idfaExist'.$sigStr.'23f12412a7a39a1cd4e469f4ea24f50a'));
            $configarray['sig']=$bsign;
        }
        //阿里巴巴
        if($appios['auser_id'] == 1673) {
            $alsignData['method']=$deal_param['method'];
            $alsignData['app_key']=$deal_param['app_key'];
            $alsignData['sign_method']=$deal_param['sign_method'];
            $alsignData['timestamp']=date('Y-m-d H:i:s');
            $alsignData['app_id']=$deal_param['app_id'];
            $alsignData['app_os']=$deal_param['app_os'];
            $alsignData['v']=$deal_param['v'];
            $alsignData['format']=$deal_param['format'];
            $alsignData['source']=$deal_param['source'];
            $alsignData['device_info_list']=json_encode(['idfa'=>$param['idfa']]);
            $sign=$this->generateSign($alsignData,'d1b7c8b7f485ec331af18afd02f872f4');
            $deal_param['timestamp']=date('Y-m-d H:i:s');
            $deal_param['sign']=$sign;
            unset($deal_param['datetime']);
        }
        //蓝城
        if($appios['auser_id'] == 1769) {
            $tmp_arr = ['idfa'=>$param['idfa'],'ip'=>$param['ip']];
            ksort($tmp_arr);
            $secret_key = '71e53705b25cf90716a95e17f2d65d0f';
            $tmp_str = '';
            foreach ($tmp_arr as $key => $val){
                $tmp_str .= $key.'='.$val.'&';
            }
            $params_str = strtoupper(md5(substr($tmp_str,0,-1)));
            //计算sign
            $sign_str ="params_str=$params_str&source_id=".$deal_param['source_id'].
                "&timestamp=".$deal_param['timestamp'].
                "&secret_key=".$secret_key;
            $deal_param['sign'] =strtoupper(md5($sign_str));
        }
        return ['flag'=>true,'msg'=>$deal_param];
    }

    /**
     * 处理qc参数
     * @param string $qc qc参数
     * @param array $param 传入参数
     * @return array 处理后的qc参数
     */
    public function qcParam(string $qc,array $param)
    {
        // $qc = '[{"name":"qc_url","value":"http://repeat.dxshiwan.com/Home/Union/qc"},{"name":"qc_number","value":"1"},{"name":"qc_method","value":"get"},{"name":"idfa_key","value":"idfa"},{"name":"key","value":"source"},{"name":"value","value":"lanmao"},{"name":"key","value":"appiosid"},{"name":"value","value":"2318"},{"name":"key","value":"mac"},{"name":"value","value":"mac"},{"name":"key","value":"os"},{"name":"value","value":"os"},{"name":"key","value":"platform"},{"name":"value","value":"platform"},{"name":"key","value":"ip"},{"name":"value","value":"ip"},{"name":"key","value":"lm_udid"},{"name":"value","value":"udid"},{"name":"key","value":"kw"},{"name":"value","value":"keywords"},{"name":"installedval","value":"1"},{"name":"idfamatchp","value":"1"},{"name":"valp","value":"2"},{"name":"qd_qc","value":"1"}]';
        // $param = ['ip'=>'13ip','keywords'=>"test",'mac'=>'02:00:00:00:00:00','os'=>'12.1','platform'=>'iphone11,2','udid'=>'dfniasf',"idfa"=>1545];
        $qcconfig = json_decode($qc, true);
        $configarray = [];
        //先把参数格式化
        foreach ($qcconfig as $key => $conf) {
            if ($conf['name'] == "key") {
                if(in_array($conf['value'],['kw','lm_udid'])) {
                    if($conf['value'] == 'kw') {
                        $configarray['keywords'] = $qcconfig[$key + 1]['value'];
                    }
                    if($conf['value'] == 'lm_udid') {
                        $configarray['udid'] = $qcconfig[$key + 1]['value'];
                    }
                }else{
                    $configarray[$conf['value']] = $qcconfig[$key + 1]['value'];
                }
                continue;
            }
            if ($conf['name'] == "value") {
                continue;
            }
            $configarray[$conf['name']] = $conf['value'];
        }
        //将参数和传入的参数相结合
        foreach ($configarray as $key => $val) {
            if(in_array($key,['ip','os','platform','osversion','keywords','mac','udid','datetime','time'])) {
                $qc_param[$val] = $param[$key];
                continue;
            }
            if($key === "idfa_key") {
                $qc_param[$val] = $param['idfa'];
                continue;
            }
            $qc_param[$key] = $val;
        }
        // var_dump($qc_param);
        return $qc_param;
    }

    /**
     * 外部去重逻辑
     * @param array $option 去重参数
     * @param string $idfa
     * @return array $urlresult
     */
    public function waibuQc(array $option,string $idfa)
    {
        /**此判断暂时无用
        if(!empty($option['qc_fun'])){
            if (function_exists($option['qc_fun'])) {
                $return = $option['qc_fun']($idfa);
                return ['flag'=>true,'msg'=>$return];
            }else{
                return ['flag'=>false,'msg'=>'去重函数不存在'];
            }
        } */
        if(empty($option['qc_url'])) {
            $urlresult["return"] = ['flag'=>false,'msg'=>'去重地址不能为空'];
            return $urlresult;
        }
        if($option['idfa_key']=='device_info_list'){
            $idfa=json_encode(['idfa'=>$idfa]);
        }
        $url = $option['qc_url'];
        $option['qc_method'] = strtolower($option['qc_method']);
        //容错处理
        if(!($option['qc_method'] == "get" || $option['qc_method']== "post")){
            $option['qc_method'] == "get";
        }
        //参数处理
        $params = [];
        foreach ($option as $key => $op) {
            if ($key == "qc_url" || $key == "qc_number" || $key == "qc_method" || $key == "idfa_key" || $key == "pattern" || $key == "installedval" || $key == "idfamatchp" || $key == "valp" || $key == "qd_qc" || $key == "match_rule" || $key == "can_key" || $key == "notcan_val" || $key == "notcan_key" || $key == "notcan_val"){
                continue;
            }
            $params[$key] = $op;
        }
        //加密
        if(isset($params['secret_key'])) {
            if(!isset($params['encryption'])){
                $urlresult["return"] = ['flag'=>false,'msg'=>"加密方式encryption不能为空"];
                return $urlresult;
            }
            $signstr = '';
            $i = 1;
            $separator = isset($params['separator'])?$params['separator']:'';
            foreach ($params as $k=>$v) {
                if($k=='idfa'){
                    continue;
                }
                if($i == $params['idfapos']){
                    $signstr .= $idfa.$separator;
                }
                if($k == 'idfapos'){
                    unset($params['idfapos']);//  删除参数中的idfa所在加密位置
                    break;
                }
                $signstr .= $v.$separator;
                $i++;
            }
            $signtolower = isset($params['signtolower'])?$params['signtolower']:0;
			$sign_name = isset($params['sign_name'])?$params['sign_name']:'sign';
            $encryption = $params['encryption'];
            unset($params['secret_key']);// 为了安全 删除参数中的秘钥
            unset($params['encryption']);//  删除参数中的加密方式
            unset($params['separator']);//  删除参数中的分割线
            unset($params['signtolower']);
            unset($params['sign_name']);
            $signstr = $separator!=''?substr($signstr,0,-1):$signstr;
			if($signtolower) {
				$params[$sign_name] = strtolower($encryption($signstr));
			}else{
				$params[$sign_name] = strtoupper($encryption($signstr));
			}
        }
        if(isset($params['ggz_secret_key'])){
			$params['secret_key'] = $params['ggz_secret_key'];
			unset($params['ggz_secret_key']);
        }
        //请求
        if($option['qc_method'] == "post") {
			if(isset($option['jsonpost']) && $option['jsonpost'] == 1){
                unset($params['jsonpost']);
                if(isset($params['sign_param_post']) && !empty($params['sign_param_post'])) {
                    //post参数体，也是加密参数体,其余参数作为urlquery参数
                    $sign_param=explode(',',$params['sign_param_post']);
                    unset($params['sign_param_post']);
                    foreach($sign_param as $v){
                        $newParams[$v]=$params[$v];
                        unset($params[$v]);
                    }
                    if(!empty($params)){
                        $url.='?'.http_build_query($params);
                    }
                    $params=$newParams;
                }
				$params = json_encode($params);
			}
            $result = $this->curl($url,"POST",$params);
        }
        if($option['qc_method'] == "get"){
            if(count($params) > 0){
                $matches = parse_url($url);
                if(empty($matches ['query'])){
                    $matches ['query'] = http_build_query($params);
                }else{
                    $matches ['query'] .= "&".http_build_query($params);
                }
                $scheme = $matches ['scheme'];
                $host = $matches ['host'];
                $path = $matches ['path'] ? $matches ['path'] . (@$matches ['query'] ? '?' . $matches ['query'] : '') : '/';
                if($scheme == 'https'){
                    $port = !empty ($matches ['port']) ? $matches ['port'] : 443;
                }else{
                    $port = !empty ($matches ['port']) ? $matches ['port'] : 80;
                }
                $url = $scheme . '://' . $host . ':' . $port . $path;
                $result = $this->curl($url);
            }else{
                $result = $this->curl($url);
            }
        }

        $urlresult["urlout"] = $result;//去重原始数据
        if($option['idfamatchp'] == 0 && $option['qc_number'] == 1){
            $result = "__" . $idfa . "__". $result;
            $option['idfamatchp'] = 1;
        }
        $urlresult["sendurl"] = $option['qc_method'] == "post"?$url.$params:$url;//去重的原始URL
        $urlresult["params"] = $params;//去重的参数
        $option['installedval'] = explode(',',$option['installedval']);
        if($option['match_rule']=='json'){
            $urlresult["return"]=$this->getjsresult($option,$result,$idfa);
        }else{
            $urlresult["return"] = $this->getresult($option['pattern'],$result,$option['installedval'],$option['idfamatchp'],$option['valp']);
        }
        return $urlresult;
    }

    /**
     * 获取json匹配的结果（暂时不知道啥用，可能没用）
     */
    private function getjsresult($option,$result,$idfastr)
    {
        $canKey=$option['can_key'];
        $canVal=$option['can_val'];
        $notcanKey=$option['notcan_key'];
        $notcanVal=$option['notcan_val'];
        $results=json_decode($result,true);
        $return = array();
        if(!empty($results)){
           if(isset($results[$canKey])){
               if($results[$canKey]==$canVal){
                   $return[$idfastr]=0;
               }else{
                   $return[$idfastr]=1;
               }
           }else{
               return ['flag'=>false,'msg'=>"没有匹配上".$result];
           }
        }else{
            return ['flag'=>false,'msg'=>'解析错误'];
        }
        return ['flag'=>true,'msg'=>$return];
    }

    /**
     * 正则匹配Url请求的结果
     * 需要4个参数，第一，正则，第二字符串，idfa匹配的数据在匹配后的位置，$valp,值默认位置
     */
    private function getresult($pattern, $subject, $installedval = 1, $idfamatchp = 1, $valp = 2) {
        preg_match_all($pattern, $subject, $mathces);

        if (count($mathces) == 0) {
            return ['flag'=>false,'msg'=>"1没有匹配上" . "，内容为：$subject"];
        }
        if (empty($mathces[$idfamatchp]) || empty($mathces[$valp])) {
            return ['flag'=>false,'msg'=>"2没有匹配上" . "，内容为：$subject"];
        }
        if (count($mathces[$idfamatchp]) != count($mathces[$valp])) {
            return ['flag'=>false,'msg'=>"3没有匹配上" . "，内容为：$subject"];
        }
        $return = [];
        foreach ($mathces[$idfamatchp] as $key => $idfa) {
            if (in_array($mathces[$valp][$key],$installedval)) {
                $return[$idfa] = 1;
            } else {
                $return[$idfa] = 0;
            }
        }
        return ['flag'=>true,'msg'=>$return];
    }

    /**
     * 阿里巴巴加密
     */
    private function generateSign($params,$secretKey)
    {
        ksort($params);
        $stringToBeSigned = $secretKey;
        foreach ($params as $k => $v)
        {
            if(!is_array($v) && "@" != substr($v, 0, 1)) {
                $stringToBeSigned .= "$k$v";
            }
        }
        unset($k, $v);
        $stringToBeSigned .= $secretKey;
        return strtoupper(md5($stringToBeSigned));
    }

    // public function error(string $msg):array
    // {
    //     return ['falg'=>false,'message'=>$msg];
    // }

    // public function success(string $msg):array
    // {
    //     return ['falg'=>true,'message'=>$msg];
    // }
}