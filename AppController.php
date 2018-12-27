<?php

App::uses('BaseController', 'Controller');

class AppController extends BaseController {
    public $uses        = ['Showbm'];
    public $components  = ['Cookie', 'Session', 'Redis'];
    public $_YJK        = null;
    public $_YJK_MS     = null;

    //微信服务号
    private $AppID = 'wx1ace2c8f40fd6605';
    private $AppSecret = '6ba999c53b578fb9aecf4b07c17a4f81';

    public function beforeFilter() {
        $this->_YJK     = new ToolGlobal();
        $this->_YJK_MS  = new MsgSender();  // 即时消息推送类MsgSender
        header('Content-type: text/html; charset=utf-8');
        
        // Cookie
        $this->Cookie->name     = 'youjuke';
        $this->Cookie->domain   = PRIMARY_DOMAIN;
        $this->Cookie->path     = '/';
        $this->Cookie->httpOnly = true;

        //营销部门渠道推广标记
        $putin = 0;
        $child = 1;
        $get   = $this->_YJK->reqCheck($_GET);
        if( isset($get['putin']) && !empty($get['putin']) ){

            $putin = $get['putin'];

        }elseif( isset($get['bdjj']) && !empty($get['bdjj']) ){

            $putin = $get['bdjj'];
        }

        if( isset($get['child']) && !empty($get['child']) ){ $child = $get['child']; }

        $unionKey = $this->Cookie->read('unionKey');
        $this->m_putin = $putin;
        if (!empty($putin) && !empty($child) && (empty($unionKey) || strcasecmp($unionKey, $putin.'_'.$child))) {
            $this->Cookie->write('unionKey', $putin.'_'.$child, false, 86400);
        }

        //记录是否是广告推广过来的用户
        $this->cake24_set_unionOrigin($putin);

        //记录今日头条广告主跟踪参数
        $this->cake24_set_campaign();

        unset($putin, $child, $get, $unionKey);
    }

    //跨域获取信息
    public function curl_get($url)
    {
        $to=2;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_TIMEOUT, $to);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        $data = curl_exec($curl);
        curl_close($curl);
        return $data;
    }

    /*
     * 生成指定位数的随机字符串
     * @params type为0时生成字母和数字组合的字符串，为1时生成数字字符串
     * @stone
     */
    function get_rand_str($length, $type = 0) {
      $str = '';
      if ($type == 1) {
        $str_pool = '0123456789';
      } else {
        $str_pool = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz';
      }
      
      $max = strlen($str_pool)-1;

      for($i = 0;$i < $length;$i++){
        $str.=$str_pool[rand(0,$max)];
      }

      return $str;
    }

    public function randNum($len) {
        $seed = array(1, 2, 3, 4, 5, 6, 7, 8, 9);

        $str = '';

        for ($i = 1; $i <= $len; $i++) {
            $str .= $seed[intval(mt_rand(0, count($seed) - 1))];
        }

        return $str;
    }

    /*
     * 将数组转化为xml
     * @stone
     */
    function ToXml($arr)
    {
        if(!is_array($arr) 
            || count($arr) <= 0)
        {
            die("数组数据异常！");
        }
        
        $xml = "<xml>";
        foreach ($arr as $key=>$val)
        {
            if (is_numeric($val)){
                $xml.="<".$key.">".$val."</".$key.">";
            }else{
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
            }
        }
        $xml.="</xml>";
        return $xml; 
    }

    /*
     * 支付渠道自动退款
     * @stone
     */
    function returnPay($data, $trade_type = '')
    {
        $order_id = $data['id'];
        if ($data['pay_tool'] == 0) {
            
            require_once(APP . 'Vendor' . DS . 'Wxpay' . DS .'lib' . DS . 'WxPay.Config.php');

            $path = dirname(__FILE__).'/../Vendor/Wxpay/cert/';
            if ($trade_type == 'APP') {
                $appid = WxPayConfig::APP_APPID;
                $mch_id = WxPayConfig::APP_MCHID;
                $key = WxPayConfig::APP_KEY;

                $path = dirname(__FILE__).'/../Vendor/Wxpay/app-cert/';
            } else {
                $appid = WxPayConfig::APPID;
                $mch_id = WxPayConfig::MCHID;
                $key = WxPayConfig::KEY;
            }

            if ($data['no'] && $data['pay_no'] && $data['goods_price']) {
                
                $nonce_str = $this->get_rand_str(32);
                $out_refund_no = date('ymdHis', time()).$this->randNum(6);

                $out_trade_no = $data['no'];
                $refund_fee = $data['goods_price'] * 100;
                $total_fee = $data['goods_price'] * 100;
                $transaction_id = $data['pay_no'];

                $sign = strtoupper(MD5('appid='.$appid.'&mch_id='.$mch_id.'&nonce_str='.$nonce_str.'&op_user_id='.$mch_id.'&out_refund_no='.$out_refund_no.'&out_trade_no='.$out_trade_no.'&refund_fee='.$refund_fee.'&total_fee='.$total_fee.'&transaction_id='.$transaction_id.'&key='.$key));

                $arr = [
                    'appid' => $appid,
                    'mch_id' => $mch_id,
                    'nonce_str' => $nonce_str,
                    'op_user_id' => $mch_id,
                    'out_refund_no' => $out_refund_no,
                    'out_trade_no' => $out_trade_no,
                    'refund_fee' => $refund_fee,
                    'total_fee' => $total_fee,
                    'transaction_id' => $transaction_id,
                    'sign' => $sign
                ];

                $xml = $this->ToXml($arr);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://api.mch.weixin.qq.com/secapi/pay/refund');
                curl_setopt($ch, CURLOPT_HEADER, FALSE);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
                curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'pem');
                curl_setopt($ch, CURLOPT_SSLCERT, $path.'apiclient_cert.pem');
                curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'pem');
                curl_setopt($ch, CURLOPT_SSLKEY, $path.'apiclient_key.pem');
                curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'pem');
                curl_setopt($ch, CURLOPT_CAINFO, $path.'rootca.pem');
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);

                $data = curl_exec($ch);

                if ($data) {
                    curl_close($ch);

                    $return_data = json_decode(json_encode(simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
                    CakeLog::write('weixin', json_encode($return_data));
                    if ($return_data['return_code'] == 'SUCCESS' && $return_data['result_code'] == 'SUCCESS') {
                        CakeLog::write('weixin', '对订单'.$order_id.'的退款操作成功');
                        return true;
                    } else {
                        CakeLog::write('weixin', '对订单'.$order_id.'的退款操作失败：退款失败('.$return_data['err_code_des'].')');
                        return false;
                    }

                } else {
                    $error = curl_errno($ch);
                    CakeLog::write('weixin', '对订单'.$order_id.'的退款操作失败：curl出错，错误码('.$error.')');
                    curl_close($ch);
                }

            } else {
                CakeLog::write('weixin', '对订单'.$order_id.'的退款操作失败：订单信息缺失');
            }
        } else if ($data['pay_tool'] == 1) {
            require_once(APP . 'Vendor' . DS . 'Alipay' . DS .'alipay.config.php');
            require_once(APP . 'Vendor' . DS . 'Alipay' . DS .'lib' . DS . 'alipay_submit.class.php');

            /**************************请求参数**************************/

            //服务器异步通知页面路径
            $notify_url = 'http://'.$_SERVER['HTTP_HOST'].'/alipay/nopwd_refund_notify';
            //需http://格式的完整路径，不允许加?id=123这类自定义参数

            //退款批次号
            $batch_no = date('YmdHis', time()).$this->randNum(6);
            //必填，每进行一次即时到账批量退款，都需要提供一个批次号，必须保证唯一性

            //退款请求时间
            $refund_date = date('Y-m-d H:i:s', time());
            //必填，格式为：yyyy-MM-dd hh:mm:ss

            //退款总笔数
            $batch_num = 1;
            //必填，即参数detail_data的值中，“#”字符出现的数量加1，最大支持1000笔（即“#”字符出现的最大数量999个）

            //单笔数据集
            $detail_data = $data['pay_no'].'^'.$data['goods_price'].'^退货';
            //必填，格式详见“4.3 单笔数据集参数说明”


            /************************************************************/

            //构造要请求的参数数组，无需改动
            $parameter = array(
                    "service" => "refund_fastpay_by_platform_nopwd",
                    "partner" => trim($alipay_config['partner']),
                    "notify_url"    => $notify_url,
                    "batch_no"  => $batch_no,
                    "refund_date"   => $refund_date,
                    "batch_num" => $batch_num,
                    "detail_data"   => $detail_data,
                    "_input_charset"    => trim(strtolower($alipay_config['input_charset']))
            );

            //建立请求
            $alipaySubmit = new AlipaySubmit($alipay_config);
            $html_text = $alipaySubmit->buildRequestHttp($parameter);
            //解析XML
            //注意：该功能PHP5环境及以上支持，需开通curl、SSL等PHP配置环境。建议本地调试时使用PHP开发软件
            /*$doc = new DOMDocument();
            $doc->loadXML($html_text);*/
            $return_data = json_decode(json_encode(simplexml_load_string($html_text, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
            //请在这里加上商户的业务逻辑程序代码

            //——请根据您的业务逻辑来编写程序（以下代码仅作参考）——

            //获取支付宝的通知返回参数，可参考技术文档中页面跳转同步通知参数列表

            //解析XML
            /*if( ! empty($doc->getElementsByTagName( "alipay" )->item(0)->nodeValue) ) {
                $alipay = $doc->getElementsByTagName( "alipay" )->item(0)->nodeValue;
                echo $alipay;
            }*/
            CakeLog::write('alipay', '对订单'.$order_id.'的退款操作结果为:'.json_encode($return_data));
            //——请根据您的业务逻辑来编写程序（以上代码仅作参考）——
            return $return_data['is_success'];
        }
    }
    
    //一元夺宝,生成抽奖号
    function get_lot_code($no , $gid, $pay_tool, $pay_no){
        
        //查询已有code
        $snif = $this->SnatchInfo->find('all', array('conditions' => array('gid' => $gid, 'status' => 1),'fields' => array('code')));
        
        
        //查询需要修改的订单
        $sn_no = $this->SnatchInfo->find('all', array('conditions' => array('no' => $no, 'status' => 0),'fields' => array('id')));      
        //参与人数
        $headcount = $this->GrabTreasure->field('headcount', ['id' => $gid]);
        
        $codes = array();
        foreach($snif as $sn){
            $codes[] = $sn['SnatchInfo']['code'];
        }
       
        for ($i = 0;$i < count($sn_no);$i++) {
            //生成兑奖号码（10000000 + （1-商品需参与人数之间的随机数，不能重复））
            $code = 0;
            
            //过滤已有号码
            while(1){
                $j = 0;
                $number = rand(1,$headcount);               
                $code = 10000000 + $number;
                foreach($codes as $c){
                     if($code == $c){
                        $j++;
                     }
                }
                if($j == 0){
                    break;
                }
            }
            
            $codes[]=$code;
            $data = array(
                'code' => $code, 
                'status' => 1,
                'pay_tool' => $pay_tool,
                'pay_no' => $pay_no,
            );      
            
            $insert_code = $this->SnatchInfo->updateAll($data,array('id' => $sn_no[$i]['SnatchInfo']['id'], 'gid' => $gid));
            if(!$insert_code){
                $i--;
            }
        }
        
    }

    /*
     * 判断商城订单是否为排他性首单
     * @stone
     */
    function is_first_order($params){
        return 0; # 方法暂停使用，解密方法删除
        // 是否已有付过款的商城订单
        $qbs_count = $this->Orders->find('count', ['conditions' => ['created_by' => $params['user_id'], 'order_from !=' => 1, 'status >' => 1, 'status !=' => 5, 'id !=' => $params['order_id']]]);
        if ($qbs_count) {
            return 1;
        }

        // 在线支付建材馆订单
        $online_admin_count = $this->Orders->find('count', ['conditions' => ['created_by' => $params['user_id'], 'order_from' => 1, 'type' => [12], 'status >' => 1, 'status !=' => 5, 'id !=' => $params['order_id']]]);
        if ($online_admin_count) {
            return 1;
        }

        $user_info = $this->Users->findById($params['user_id']);
        
        // 是否已有建材馆订单
        $admin_count = $this->Orders->find('count', ['conditions' => ['phone' => $user_info['Users']['encryption_mobile'], 'order_from' => 1, 'NOT' => ['type' => [12]]]]);
        
        if ($admin_count) {
            return 1;
        }

        // 是否已有付过款的触摸屏订单 9-商家触摸屏，10-自营触摸屏
        $cmp_count = $this->Orders->find('count', ['conditions' => ['created_by' => $params['user_id'], 'order_from' => 1, 'type' => [9,10], 'status >' => 1, 'status !=' => 5, 'id !=' => $params['order_id']]]);
        if ($cmp_count) {
            return 1;
        }

        // 是否已有一元换购
        if(!$params['snatch']){
            $params['no'] = 0;
        }

        $snatch_count = $this->SnatchInfo->find('count', ['conditions' => ['userid' => $params['user_id'],'no !=' => $params['no'], 'status >' => 0]]);

        if ($snatch_count) {
            return 1;
        }
        return 0;
    }

    //获取app端传递过来的参数
    protected function _get_app_param(){

        if($_GET['platform']){

            if(empty($this->Session->read('platform'))){

                $this->Session->write('platform', $_GET['platform']);
            }

        }
        
    }


    /**
     * @即将替换 _set_laiyuan 方法
     * @获取链接来源（方便在具体的页面链接仍然能保留之前的来源）
     * @param  $requirement Array 请求参数
     * @param  $return      bool  是否返回参数数据
     * @author 120291704@qq.com 
     * @date   2018-07-09
     * @update 2018-10-17
     */
    protected function _load_semPromotion($requirement = [], $return = false){

        if( isset($requirement['uri_flag']) && in_array($requirement['uri_flag'], ['?', '&'])){

            $uri_flag = $requirement['uri_flag'];
        }else{

            if ($requirement['hide_flag']) {
            
                $uri_flag    = strpos($_SERVER['REQUEST_URI'], '?') == false ? '?' : '&';
            }else{

                $uri_flag    = '';
            }
        }

        $parse_url = parse_url($_SERVER['REQUEST_URI']);

        if( !isset($parse_url['query']) ){//url网址上无渠道信息

            //是否加载COOKIE中的渠道数据
            if( isset($requirement['load_cookie']) && $requirement['load_cookie'] ){

                $unionKeyArr = explode('_', $this->Cookie->read('unionKey'));
                $parse_url['query'] = 'putin='.$unionKeyArr[0].'&child='.$unionKeyArr[1];

            }else{

                return ;
            }
            
        }

        parse_str($parse_url['query'], $promotion_arr);
        
        if( isset($requirement['show_param']) && $requirement['show_param'] ){

            if($return){

                return $promotion_arr;
            }else{

                $putin = $promotion_arr['putin'];
                $child = $promotion_arr['child'];
                $this->set(compact('putin', 'child'));
            }
        }else{

            $laiyuan_url = $uri_flag.$parse_url['query'];
            if($return){

                return ['laiyuan_url' => $laiyuan_url];
            }else{

                $this->set(compact('laiyuan_url'));
            }
        }

        unset($uri_flag, $parse_url, $promotion_arr);

    }

    /*
     *@判断ip短信发送次数
     *@author dtz 2017-04-12
     *同一个IP一天只能请求发送10次短信
     *@更新于 2018-10-17
     */
    protected function _checkSmsSendNum(){

        $ip    = $this->cake24_getRealIp();
        $ignore = $this->cake24_ignore_ip($ip);

        $ip_long = sprintf("%u\n", ip2long($ip));//ip转化为整形，并解决负数问题

        if( !$ignore ){//不需要屏蔽的ip,但不能重复发送超过10次
            
            $client_ip_count = (int)$this->Redis->get('sms_count_'.$ip_long);
            
            //同一个ip下报名次数不能超过10次
            if( $client_ip_count >= 10 ){
                
                return false;
            }else{

                $this->Redis->setex('sms_count_'.$ip, 86400, $client_ip_count+1 );
                return true;
            }
        }else{

            return true;
        }
    }

    /*
     *@微信回复文章分享逻辑（仅供手机端调用）
     *@author dtz 2017-04-20
     *系统判断在微信浏览器下获取微信用户分享基本信息
     *@更新于 2017-04-20
     */
    protected function _load_wechatReplayShare(){

        $this->_load_wechatShare();//微信分享逻辑
        $this->_load_wechatReplay();//微信文章回复逻辑
    }

    /**
     *@判断当前业主是否是微信浏览器访问
     *@param  $return 是否返回数据
     *@return $browser_type 浏览器类型（1-为微信浏览器访问 2-非微信浏览器访问）
     *@author 120291704@qq.com 
     *@date 2017-07-06
     */
    protected function _check_browserType($return = false){

        //判断是否为微信访问 1-为微信浏览器访问 2-非微信浏览器访问
        $browser_type = strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false ? 1 : 2;

        if($return){

            return $browser_type;
        }else{

            $this->set(compact('browser_type'));
        }
    }


    /*
     *@微信分享逻辑（仅供手机端调用）
     *@author dtz 2017-04-19
     *系统判断在微信浏览器下获取微信用户分享基本信息
     *@更新于 2017-04-19
     */
    protected function _load_wechatShare(){

        //微信访问
        $wx_share = 1;
        $browser_type = strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false ? 1 : 2;//判断是否为微信访问
        if( $browser_type == 1 ){
            
            $wx_share = 2;
            $this->_mGetWxShareInfo();
        }

        $this->set(compact('wx_share', 'browser_type'));
    }


    /*
     *@微信文章回复逻辑
     *@author dtz 2017-04-20
     *@更新于 2017-04-20
     */
    private function _load_wechatReplay(){

        $request = $this->request->query;
        $share = explode('_', $request['share']);
        $type = strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false ? 1 : 2;//判断是否为微信访问
        if( $type == 1 && (($request['putin'] == 'weixin' && $request['child'] == 'gg') || (strpos('yjk,share_', $share[0]))) ){

            $con_url = $request['share'] ? $request['share'] : 'gg';
            $child = array('gg');
            $conditions = $request['share'] || in_array($request['child'], $child) ? array('url like'=>"%".$con_url."%") : array('key_words'=>1);
            $WechatReplay = $this->WechatReplay->find('first',array('conditions'=>$conditions));
            $this->set('WechatReplay',$WechatReplay['WechatReplay']);
            $this->set('bj_request',$this->request->query());

            /**用户订阅号**/
            $openid = 'fwh';
            if( strpos('yjk,'.$request['child'], 'dyh') ){

                $openid = 'dyh';
            }
            $this->set('openid',$openid);
        }
    }

    /*
     *@获取微信用户分享基本信息（仅供手机端调用）
     *@author dtz 2017-04-19
     *@更新于 2017-04-19
     */
    private function _mGetWxShareInfo(){

        //获取微信接口基本CLASS类
        $jssdk       = $this->_wx_jssdkClass();
        $signPackage = $jssdk->GetSignPackage();

        $this->set('appId', $signPackage['appId']);
        $this->set('timestamp', $signPackage['timestamp']);
        $this->set('nonceStr', $signPackage['nonceStr']);
        $this->set('signature', $signPackage['signature']);

        unset($jssdk);

    }


    /**
     *@微信接口基本CLASS类
     *@author 120291704@qq.com 
     *@return class Model
     *@date   2018-06-26
     **/
    protected function _wx_jssdkClass(){

        App::import('Vendor', 'jssdk');
        //获取微信基本信息
        return new JSSDK($this->AppID, $this->AppSecret);

    }

    /**
     *@获取微信客户端用户的基本信息
     *@param  $open_id String 微信用户的唯一识别ID
     *@author 120291704@qq.com 
     *@date   2018-06-26
     **/
    protected function _get_wxuserInfo($open_id = ''){

        //测试用
        // return [

        //     'openid'     => 'obXNEwhyTmLqe22BheetpSo54WT8',
        //     'nickname'   => '昵称',
        //     'headimgurl' => 'http://wx.qlogo.cn/mmopen/xn81cgWHAyLQsmAHfpafp6MBPx5Llr9nbhPGvI2GCdQWmbIUnzCh0YTFZnDBqnK08GdLCwBqEiabeehoUUKQ1vJMf8fR2ktX2/0'
        // ];

        unset($jssdk);

        //获取微信接口基本CLASS类
        $jssdk = $this->_wx_jssdkClass();
        if(empty($open_id)){

            $open_id = $jssdk->getOpenid();//采取静默方式获取open_id
        }

        return $jssdk->getUserInfo($open_id);

    }

    /**
     * 将用户的账号和微信信息实行绑定，以此实现无登录化
     * @author 120291704@qq.com @date 2018-06-25
     * ---------------------------------------------------------
     * @param $user_id     Int   当前登录的用户user_id
     * @param $wxuser_info Array 当微信的基本信息，例如open_id,微信昵称和微信头像
     * ---------------------------------------------------------
     */
    protected function _async_wxUserInfo($user_id, $wxuser_info){

        if( empty($user_id) || !is_numeric($user_id)){ return ; }
        if( empty($wxuser_info['openid']) ){ return ; }

        //判断当前Model是否存在
        if(!$this->cake24_check_modelExist($this->MallUsers)){ return ; }

        $conditions  = [ 'MallUsers.id' => $user_id ];
        $update_data = [

            'MallUsers.wx_open_id' => "'".$wxuser_info['openid']."'",
            'MallUsers.nickname'   => "'".$wxuser_info['nickname']."'",
            'MallUsers.avatar'     => "'".$wxuser_info['headimgurl']."'"
        ];

        try {
            $this->MallUsers->updateAll($update_data, $conditions);

            unset($conditions, $update_data);
        } catch (Exception $error) {
            
            return false;
        }
    }


    protected function object2array(&$object) {
        $object =  json_decode( json_encode( $object), true);
        return  $object;
    }

    /*
     * 营销短信通知
     * date 2017-05-25
     * author dtz
     * 20170614提出废止
     * 20170622提出开启
     * 20180105提出废止
     */
    protected function _sms_marketing($mobile){

        return ;
        if(empty($mobile)){ return ; }
        /****20170614 的短信内容 start*************/
        //$content = '【优居客客服中心】1元抢好货！防晒伞、卡通抱枕、品质拖鞋、清风抽纸…上百种好货，全部1元，包邮到家，仅此1天，马上抢：http://t.cn/Rasys9V 回TD退订';
        /****20170614 的短信内容 end*************/

        /****20170622 的短信内容 start*************/
        $content = '【优居客客服中心】1元抢好货！防晒伞、卡通抱枕、品质拖鞋…上百种好货，全部1元，包邮到家，仅此1天，马上抢：http://t.cn/RofISF2 回TD退订';
        /****20170622 的短信内容 end*************/
        $this->cake24_send_message(array('mobile'=>$mobile,'content'=>$content), true);

    }


    /*
    * 发短信
    */
    protected function send_message($mobile, $content, $ext = 0, $times = null) {
        if (empty($mobile) || empty($content)) {
            return false;
        }

        if (empty($times)) {
            $times = 1;
        }

        $post['mobile'] = is_array($mobile) ? join(',', $mobile) : $mobile;
        $post['content'] = $content;

//        $sms_api = 'http://sms.youjuke.com/sms/send_sms_api';
        $sms_api = 'http://sms.youjuke.com/sms/mandao';

        if ($ext == 1) {
            $sms_api = 'http://sms.youjuke.com/sms/send_sms_mkt_api';
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $sms_api);
        curl_setopt ( $ch, CURLOPT_POST, 1 );
        curl_setopt ( $ch, CURLOPT_HEADER, 0 );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ( $ch, CURLOPT_POSTFIELDS, $post );

        $output = curl_exec($ch);
        curl_close($ch);

        if (trim($output) == "失败" && $times > 0) {
            $this->send_message($mobile, $content, $ext, $times - 1);
        } else {
            return true;
        }

        return false;
    }


    /*
    * 发短信
    */
    protected function send_message_ex($mobile, $content, $ext = 0, $times = null) {
        if (empty($mobile) || empty($content)) {
            return false;
        }

        if (empty($times)) {
            $times = 1;
        }

        $post['mobile'] = is_array($mobile) ? join(',', $mobile) : $mobile;
        $post['content'] = $content;

        $sms_api = 'http://sms.youjuke.com/sms/send_sms_api_ex';
//        $sms_api = 'http://sms.youjuke.com/sms/mandao';

        if ($ext == 1) {
            $sms_api = 'http://sms.youjuke.com/sms/send_sms_mkt_api';
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $sms_api);
        curl_setopt ( $ch, CURLOPT_POST, 1 );
        curl_setopt ( $ch, CURLOPT_HEADER, 0 );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ( $ch, CURLOPT_POSTFIELDS, $post );

        $output = curl_exec($ch);
        curl_close($ch);

        if (trim($output) == "失败" && $times > 0) {
            $this->send_message($mobile, $content, $ext, $times - 1);
        } else {
            return true;
        }

        return false;
    }

    /*
     * 使用百度短连接
     * @stone
     */
    protected function baiduShortenUrl($long_url) {

        $ch = curl_init();
        $url = 'http://apis.baidu.com/3023/shorturl/shorten?url_long='.$long_url;
        $header = [
            'apikey: c3aadd615ae4b6bfcbf8e4afc503fab7',
        ];
        // 添加apikey到header
        curl_setopt($ch, CURLOPT_HTTPHEADER  , $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // 执行HTTP请求
        curl_setopt($ch , CURLOPT_URL , $url);
        $result = curl_exec($ch);

        //解析json
        $short_arr = json_decode($result, true);

        $url_short = $short_arr['urls'][0]['url_short'];
        //异常情况返回false
        if (isset($short_arr['error']) || $url_short == '') {
            return false;
        } else {
            return $url_short;
        }

    }


    protected function lottery_prob($prize){
        $x = $prize;
        $a = 1;
        $b = 1000000;
        $c = $a;
        $prize = 0;

        $rand = rand($a,$b);

        foreach($x as $k => $val) {
            $v = $val['odds'];
            $c += $x[$k-1]['odds']*$b;
            if ($c <= $rand && $rand < ($c+$v*$b)) {
                $prize = $k;
                break;
            }
        }

        return $prize;
    }


    /*
     *@根据数组中设置的几率计算出符合条件的id
     *@author dtz 2017-07-26
     */
    protected function lottery_get_rand($proArr) { 
        $result = ''; 
     
        //概率数组的总概率精度 
        $proSum = array_sum($proArr);
     
        //概率数组循环 
        foreach ($proArr as $key => $proCur) { 
            $randNum = mt_rand(1, $proSum);
            if ($randNum <= $proCur) { 
                $result = $key; 
                break; 
            } else { 
                $proSum -= $proCur;
            } 
        } 
        unset ($proArr); 
     
        return $result; 
    }


    /**
    * 求两个日期之间相差的天数
    * (针对1970年1月1日之后，求之前可以采用泰勒公式)
    * @param string $date_start
    * @param string $date_end
    * @return number
    * @suthor dtz 2017-09-08
    */
    protected function _get_date_diff($date_start, $date_end){
    
        $time_start = strtotime($date_start);
        $time_end   = strtotime($date_end);

        if ($time_start > $time_end) {
            return 0;
        }
        return ($time_end - $time_start) / 86400;
    }


    /**
     * @通过面积获取报价详情页
     * @param $area Int 房屋装修面积
     * @param $href Url 页面链接网址url
     * @date 2017-09-29
     * @author 120291704@qq.com
     **/
    protected function _get_jump_url($area, $href = ''){

        if(empty($href)){

            if(!is_numeric($area)){ return ; }
            $HxArr = $this->_get_house_apartment( $area );
            $href='/index/xbaojia_detail?total_area='.$area.'&ws='.$HxArr['shi'].'&kt='.$HxArr['ting'].'&cf='.$HxArr['chu'].'&wsj='.$HxArr['wei'].'&yt='.$HxArr['yangtai'].'&type=zx&qa_source=duanxin';
            unset($HxArr, $area);
        }
        
        $rtime_url = rtrim($_SERVER['SERVER_NAME'].$href);
        $f_urls    = $this->cake24_filterUrl($rtime_url);
        $bj_url    = $this->cake24_sinaShortenUrl($f_urls);
        $bj_url    = $bj_url ? $bj_url : $rtime_url;

        unset($rtime_url, $f_urls);

        return $bj_url;
    }


    /*
     * 短信通知在线报价的详情
     * date 2017-09-19
     * author dtz
     */
    protected function _push_text_message($data, $href = ''){

        if(empty($data['mobile'])){ return ; }

        if(empty($data['content'])){

            $bj_url = $this->_get_jump_url($data['area'], $href);

            if(empty($data['total_price'])){ return ; }
            if(empty($data['hot_line']))   { return ; }

            if($data['zx_type'] == 1){//全包
                $data['content'] = '【优居客客服中心】您家的装修半包+主材总价约为'.$data['total_price'].'元。报价明细见（'.$bj_url.'）。对该报价有疑问可直接拨打：'.$data['hot_line'].'，还为您免费审核报价。如非本人操作，请忽略。';

            }else{//半包
                if($data['area_type'] == 'largehouse'){
                    //$data['content'] = '【优居客客服中心】您家的装修半包总价约为'.$data['total_price'].'元。如想知晓报价明细、全包价格或对报价有疑问可咨询专家（'.$bj_url.'），也可直接拨打：'.$data['hot_line'].'，还为您免费审核报价。如非本人操作，请忽略。';
                    $data['content'] = '【优居客客服中心】您家的装修半包总价约为'.$data['total_price'].'元。恭喜您成为VIP业主，已为您开通VIP专家咨询通道，可咨询任何装修及报价问题（http://t.cn/RuI18NF）。正在为您安排专属客服，稍后请注意接听电话，咨询报价'.$data['hot_line'].'转1再转1。';
                }else{
                    if(empty($data['sms_type'])){ $data['sms_type'] = '装修'; }
                    $data['content'] = '【优居客客服中心】您家的'.$data['sms_type'].'半包总价约为'.$data['total_price'].'元。报价明细见（'.$bj_url.'）。完善装修信息可领1000元装修基金（装修时当现金使用），对该报价有疑问可拨打：'.$data['hot_line'].'，还为您免费审核报价。如非本人操作，请忽略';
                }
            }
            
        }

        $this->cake24_send_message($data);

    }


    /**
     * 发送假期短信通知
     * @param string $mobile
     * @return bool
     */
    protected function sendHolidayNotice($mobile){
        if (empty($mobile)) {
            return false;
        }

        #节假日期间
        $holliday_start = '2018-09-30 18:00:00';
        $holliday_end = '2018-10-07 18:00:00';
        if (time() > strtotime($holliday_start) && time() < strtotime($holliday_end)) {
            $data['content']="【优居客客服中心】您好！十一期间优居客<专家问答>频道暂停回复，10月8日恢复正常， 9月30日18点后您提交的问题专家将于10月8日逐一回复，感谢您的支持与谅解。祝您节日愉快。点击http://t.cn/Rr0safG  可查看专家回复，或再次提问！";
            $data['mobile']=$mobile;
            $this->cake24_send_message($data,true);#营销类短信通道
            return $data;
        }

        #正常周六周日
        $Friday = date('Ymd 18:00:00', (time() + (5 - (date('w') == 0 ? 7 : date('w'))) * 24 * 3600));;#本周五
        $Sunday = date('Ymd 18:00:00', (time() + (7 - (date('w') == 0 ? 7 : date('w'))) * 24 * 3600));;#本周日
        if (date('Ymd H:i:s') > $Friday && date('Ymd H:i:s') < $Sunday) {
            $data['content']="【优居客客服中心】您好！优居客<专家问答>频道回复时间为工作日10:00-18:00，周五18点后您提交的问题专家将于下周一逐一回复，感谢您的支持与谅解。点击 http://t.cn/Rr0safG  可查看专家回复，或再次提问！";
            $data['mobile']=$mobile;
            $this->cake24_send_message($data,true);#营销类短信通道
            return $data;
        }

        return false;
    }

    /*
     * 根据面积获取户型
     * date 2017-09-19
     * author dtz
     */
    private function _get_house_apartment($area){
        
        if( $area < 60 )
        {
            $arr = array('shi'=>1,'ting'=>1,'chu'=>1,'wei'=>1,'yangtai'=>1);
        }
        else if( $area > 60 && $area <=90 )
        {
            $arr = array('shi'=>2,'ting'=>1,'chu'=>1,'wei'=>1,'yangtai'=>1);
        }
        else if( $area > 90 && $area <=110 )
        {
            $arr = array('shi'=>3,'ting'=>1,'chu'=>1,'wei'=>1,'yangtai'=>1);
        }
        else if( $area > 110 && $area <=130 )
        {
            $arr = array('shi'=>3,'ting'=>2,'chu'=>1,'wei'=>1,'yangtai'=>1);
        }
        else if( $area > 130 && $area <=150 )
        {
            $arr = array('shi'=>3,'ting'=>2,'chu'=>1,'wei'=>2,'yangtai'=>1);
        }
        else if( $area > 180 )
        {
            $arr = array('shi'=>4,'ting'=>2,'chu'=>1,'wei'=>2,'yangtai'=>1);
        }
        return $arr;
    }


    /**
    * 二维数组根据字段进行排序
    * @params array $array 需要排序的数组
    * @params string $field 排序的字段
    * @params string $sort 排序顺序标志 SORT_DESC 降序；SORT_ASC 升序
    * date 2017-10-18
    * author dtz
    */
    protected function array_sequence($array, $field, $sort = 'SORT_DESC'){
        $arrSort = array();
        foreach ($array as $uniqid => $row) {
            foreach ($row as $key => $value) {
                $arrSort[$key][$uniqid] = $value;
            }
        }
        array_multisort($arrSort[$field], constant($sort), $array);
        return $array;
    }

    /**
    * 加载报名表单是否需要验证
    * 总开关设置为常量，不设置默认为不开启验证
    * date 2017-11-06
    * author 120291704@qq.com
    */
    protected function _load_verify_captcha(){

        if(constant('VERIFY_CAPTCHA') != NULL){

            $this->set('verify_captcha', constant('VERIFY_CAPTCHA'));
            
        }else{

            $this->set('verify_captcha', false);//默认验证码不开启
        }

        $this->Session->delete('captcha_code');
        
    }

    /** 
      * @获取具体城市的地区
      * @param $city_id int 72-上海 ， 74-无锡
      * @param $remove_district_id_arr array 排除的地区的id
      * @author dtz 2017-11-28
      * @更新于 2017-11-28
      **/
    protected function getDistrictsData($city_id = 72, $remove_district_id_arr = []){

        
        if(empty($city_id) || $city_id == 0){ $city_id = 72; }

        $conditions = ['citys_id'=> $city_id];

        if(!empty($remove_district_id_arr) && is_array($remove_district_id_arr)){

            $conditions = array_merge($conditions, ['id not' => $remove_district_id_arr ]);
        }

        $districts_data = $this->Districts->find('list', [
            'conditions' => $conditions,
            'fields' => ['id', 'name']
        ]);

        return $districts_data;

    }

    /** 
      * @获取装修公司基本数据信息
      * @param $periods 推广、专题页或者指定页面
      * @author 120291704@qq.com 2018-03-21
      * @更新于 2018-03-28
      **/
    protected function _get_decorateCompanyData($periods = ''){

        $limit = 0;
        if(empty($periods)){ return ; }
        if(in_array($periods, ['youhuihd', 'kbzxgs', 'zxdk'])){

            $limit = 12;
        }elseif(in_array($periods, ['activitys_gongsi', 'dsjzx'])){

            $limit = 3;
        }elseif(in_array($periods, ['zxfw', 'xhxzx'])){

            $limit = 24;
        }

        $params['limit'] = $limit;
        return $this->cake24_getDecorateCompanyData($params);

    }


    /**
      * @加载页面头部样式（只对调取公共头尾部才起效）
      * @param $periods 推广、专题页或者指定页面
      * @author 120291704@qq.com 2018-04-02
      * @更新于 2018-04-02
      **/
    protected function _load_page_header($periods){

        if(empty($periods)){ return ; }

        $head = '';
        if(in_array($periods, ['hfbj'])){

            $head = 'member';//会员中心板式的头部
        }elseif(in_array($periods, ['lffx'])){

            $head       = 'customize_title';//自定义title （左边是LOGO）
            $head_data  = $this->_set_header_title($periods);
            $this->set(compact('head_data'));
        }elseif(in_array($periods, ['xhxzx'])){

            $head       = 'customize_jump_title';//自定义title （左边是LOGO 和显示首页字样）
            $head_data = $this->_set_header_title($periods);
            $this->set(compact('head_data'));
        }elseif(in_array($periods, ['baojia', 'zxbj', 'xbaojia'])){

            $head       = 'one_stop';//一站式
        }elseif(in_array($periods, ['sem_zxgs', 'sem_qtxgt', 'sem_lysj', 'sem_zjwd', 'sem_zjwdql', 'xbbaojia'])){

            $head       = $periods;
        }elseif(in_array($periods, ['detail', 'ask', 'myqa'])){

            $head       = 'qa_header';
        }

        $this->set(compact('head', 'periods'));
    }


    /** 
      * @获取指定页面的头部标题
      * @param $periods 推广、专题页或者指定页面
      * @author 120291704@qq.com 2018-04-03
      * @更新于 2018-04-03
      **/
    public function _set_header_title($periods){

        if(empty($periods)){ return []; }
        
        $return = [];
        if(in_array($periods, ['lffx'])){

            $return['title'] = '老房翻新就找优居客';
        }elseif(in_array($periods, ['xhxzx'])){

            $return['title']    = '查询我家装修价格>>';
            $return['jump_url'] = '/tg/baojia.html';
        }

        return $return;
    }

    /**
      * @数据记录到鲍菊后台
      * @ 2018-04-04 经商讨并确认当业主点击了预约审核报价重复报名，baoming_service中如果有业主的记录，则新增一条数据记录（让数据进入鲍菊后台）
      * @param $baoming_id Int 报名ID
      * @author 120291704@qq.com 2018-04-04
      * @更新于 2018-04-04
      **/
    protected function _add_baoming_service_yusuan($baoming_id){

        if(!is_numeric($baoming_id)){ return ; }

        //判断服务咨询报名是否存在
        $serviceisExist = $this->cake24_check_modelExist($this->BaomingService);
        if(!$serviceisExist){ return ; }

        $service_exist = $this->BaomingService->find('first', [

            'conditions' => ['baoming_id' => $baoming_id]
        ]);

        if(!empty($service_exist)){ return ; }

        $service_data = ['baoming_id' => $baoming_id, 'last_plate' => 0, 'create_time' => time(), 'old_yusuan' => 1 ];

        //判断当前报名是否分配过客服（如果分配过需要baoming_service.is_fp_genzong 为1）
        $isExist = $this->cake24_check_modelExist($this->GenzongCsfp);
        if(!$isExist){ return ; }

        $genzong_con = ['baoming_id' => $baoming_id, 'type' => 1, 'f_cs !=' => NULL];
        $genzong_exist = $this->GenzongCsfp->find('count', [ 'conditions' => $genzong_con ]);
        if($genzong_exist){

            $service_data['is_fp_genzong'] = 1;
        }

        try {
            $this->BaomingService->save($service_data);
            $this->cake24_refresh_baomingLog($baoming_id);
        } catch (Exception $error) {
            return ;
        }
    }

    /**
     * 判断字符中是否有文字重复
     * @param $str        Str 需要判断的文字（包含中文、英文、数字和标点符号等）
     * @param $repeat_num Int 限制文字中任何一个文字允许出现的次数，如果超过指定次数则认为重复的文字过多
     * @return false / true  false（不存在文字重复），true （存在文字重复）
     * 注：ord函数在gbk下单个中文长度为2，utf-8下长度为3
     * 注：ASCII码中英文字符的范围为：65-122
     * 注：ASCII码数字的范围为：48-57 
     * 注：ASCII码运算符号的范围为：33-64
     * 注：ASCII码中大于127则表示中文字符的三分之一，再获取后面两个获得一个完整字符
     * @date 2018-04-10
     * @author 120291704@qq.com
     **/
    protected function _check_contentRepeat($str, $repeat_num = 3){

        if(empty($str)){ return ; }
        if(empty($repeat_num) || !is_numeric($repeat_num)){ $repeat_num = 3; }

        $tmp = [];
        for($i = 0; $i < strlen($str); $i++){

            $ascii_code = ord($str[$i]); //通过ord()函数获取字符的ASCII码值
            if ( $ascii_code < 127 ) {//英文特殊字符、数字、标点符号等
                $key = $str[$i];
            } else if ( $ascii_code < 224) {//扩展字符 例如 α 等
                $key = $str[$i] . $str[$i+1];
                $i ++;
            } else {//汉字
                //通过ord()函数获取字符的ASCII码值，如果返回值大于 127则表示为中文字符的三分之一，再获取后面两个获得一个完整字符
                $key = $str[$i] . $str[$i+1] . $str[$i+2];
                $i += 2;
            }

            if (! isset($tmp[$key])) {
                $tmp[$key] = 0;
            }
            $tmp[$key]++;
        }

        if( max(array_values($tmp)) >= $repeat_num ){

            return true;
        }else{

            return false;
        }
    }


    /** 
     * @仿微信红包随机生成算法
     * @param $total Int   需要分配红包的总金额
     * @param $count Int   需要分配红包的总个数
     * @param $max   Int   每个小红包的最大金额
     * @param $min   Int   每个小红包的最小金额
     * @return 存放生成的每个小红包的值的一维数组
     * @author 120291704@qq.com
     * @date 2018-06-27
     */    
    protected function wechat_redPacketRandomLogic($total, $count, $max = 100, $min = 1 ){

        unset($result, $average);

        $result = [];

        $average = $total / $count;

        //这样的随机数的概率实际改变了，产生大数的可能性要比产生小数的概率要小。
        //这样就实现了大部分红包的值在平均数附近。大红包和小红包比较少。
        $range1 = $this->sqr($average - $min);
        $range2 = $this->sqr($max - $average);

        unset($i, $temp, $result);

        for ($i = 0; $i < $count; $i++) {
            //因为小红包的数量通常是要比大红包的数量要多的，因为这里的概率要调换过来。
            //当随机数>平均值，则产生小红包
            //当随机数<平均值，则产生大红包
            if (rand($min, $max) > $average) {
                // 在平均线上减钱
                $temp         = $min + $this->xRandom($min, $average);
                $result[$i]   = $temp;
                $total -= $temp;
            } else {
                // 在平均线上加钱
                $temp         = $max - $this->xRandom($average, $max);
                $result[$i]   = $temp;
                $total       -= $temp;
            }    
        }

        // 如果还有余钱，则尝试加到小红包里，如果加不进去，则尝试下一个。    
        while ($total > 0) {
            for ($i = 0; $i < $count; $i++) {
                if ($total > 0 && $result[$i] < $max) {
                    $result[$i]++; 
                    $total--;
                }
            }
        }

        // 如果钱是负数了，还得从已生成的小红包中抽取回来 
        while ($total < 0) {
            for ($i = 0; $i < $count; $i++) {
                if ($total < 0 && $result[$i] > $min) {  
                    $result[$i]--;
                    $total++;
                }
            }  
        }

        return $result;
    }

    /** 
     * @求一个数的平方
     * @param $n Int
     * @return $n*$n
     * @author 120291704@qq.com
     * @date 2018-06-27
     */    
    private function sqr($n){  
        return $n*$n;
    }  
      
    /** 
     * @生产min和max之间的随机数，但是概率不是平均的，从min到max方向概率逐渐加大。 
     * @先平方，然后产生一个平方值范围内的随机数，再开方，这样就产生了一种“膨胀”再“收缩”的效果。 
     * @param $min Int 最小数
     * @param $max Int 最大数
     * @return $min*$max
     * @author 120291704@qq.com
     * @date 2018-06-27
     */   
    private function xRandom($min, $max){
        $sqr      = intval($this->sqr($max-$min));
        $rand_num = rand(0, ($sqr-1));
        return intval(sqrt($rand_num));
    }


    /** 
     * @获取该区域下的所有的服务的装修公司
     * @param $district_id 上海市的区域id
     * @return $firms_data
     * @author 120291704@qq.com
     * @date 2018-07-11
     */
    protected function _get_firmsDataFromDistinct($district_id){

        if(!is_numeric($district_id)){ return []; }

        $firm_idArr = $this->FirmAddress->find('list', [

            'conditions' => ['`FirmAddress`.`district`' => $district_id ],
            'fields'     => ['id', 'firm_id'],
        ]);

        if(empty($firm_idArr)){ return []; }

        $params               = [];
        $params['limit']      = 3;
        $params['firm_idArr'] = $firm_idArr;
        $params['fields']     = ['id', 'firm_logo', 'jc_title', 'lf_nums', 'v_cases', 'v_designers', 'v_reservats'];
        $firms_data = $this->cake24_getDecorateCompanyData($params);

        foreach ($firms_data as $key => $value) {

            //设计师数量
            $firms_data[$key]['Firms']['designer_count'] = $this->FirmDesigners->find('all', [

                'conditions' => ['firm_id' => $value['Firms']['id']],
                'group'      => 'firm_id',
                'fields'     => ['count(*) as count']
            ]);

            //案例数
            $firms_data[$key]['Firms']['case_count'] = $this->FirmCases->find('all', [

                'conditions' => ['firm_id' => $value['Firms']['id']],
                'group'      => 'firm_id',
                'fields'     => ['count(*) as count']
            ]);


        }

        unset($params, $firm_idArr);

        return $firms_data;

    }


    /** 
     * @将日期格式根据以下规律修改为不同显示样式
     * -----------------------------------------------------
     * @小于1分钟 则显示多少秒前 
     * @小于1小时，显示多少分钟前  
     * @一天内，显示多少小时前  
     * @3天内，显示前天或昨天。
     * @超过3天，则显示完整日期
     * -----------------------------------------------------
     * @param  $sorce_date 数据源日期 unix时间戳 
     * @return $text Str 格式化后的时间戳
     * @author 120291704@qq.com
     * @date 2018-07-31
     */
    protected function _get_dateStyle($sorce_date){

        // $sorce_date = strtotime('2018-07-30 11:39:00');  //测试用
        // $nowTime    = strtotime('2018-07-31 11:38:00');  //测试用

        $temp_time = 0;
        $timeHtml  = ''; //返回文字格式
        $nowTime   = time();  //获取当前时间戳

        $sorce_datetime = strtotime($sorce_date);
        if( (($nowTime - $sorce_datetime) <= 10) && ($nowTime >= $sorce_datetime) ){

            //显示 刚刚（十秒内）
            $timeHtml = '刚刚';
        }elseif((($nowTime - $sorce_datetime) < 60) && (($nowTime - $sorce_datetime) > 10) ){

            //显示 **秒前（一分钟内）
            $temp_time = $nowTime - $sorce_datetime;
            $timeHtml  = $temp_time .'秒前';

        }else if( (($nowTime - $sorce_datetime) < 3600) && (($nowTime - $sorce_datetime) >= 60) ){

            //显示**分钟前 （一小时之内）
            $temp_time = date('i',$nowTime - $sorce_datetime);
            $timeHtml  = (int)$temp_time ."分钟前";

        }else if( (($nowTime - $sorce_datetime) < 3600*24) && (($nowTime - $sorce_datetime) >= 3600) ){

            //显示**小时前 （一天之内）
            if( date('d', $nowTime) > date('d', $sorce_datetime) ){

                $timeHtml = floor(($nowTime - $sorce_datetime)/3600) .'小时前';

            }else{

                $temp_time = date('H',$nowTime) - date('H',$sorce_datetime);
                $timeHtml = $temp_time .'小时前';
            }

        }elseif( (($nowTime - $sorce_datetime) < 3600*24*2) && (($nowTime - $sorce_datetime) >= 3600*24) ){

            $timeHtml = '昨天';

        }elseif( (($nowTime - $sorce_datetime) < 3600*24*3) && (($nowTime - $sorce_datetime) >= 3600*24*2) ){

            $timeHtml = '前天';

        }elseif( (($nowTime - $sorce_datetime) < 3600*24*4) && (($nowTime - $sorce_datetime) >= 3600*24*3) ){

            $timeHtml = '3天前';
        }else{

            $timeHtml = date('Y-m-d', $sorce_datetime);
        }

        unset($nowTime, $temp_time);
        return $timeHtml;
    }


    /** 
     * @格式化mall_users表中用户的用户名
     * -----------------------------------------------------
     * @目前mall_users表中username大部分存储的是明文的手机号
     * @为了防止手机号泄露，则如果用户名是手机号的话需要隐藏手机号中间四位数
     * -----------------------------------------------------
     * @param  $username String 用户名
     * @author 120291704@qq.com
     * @date 2018-08-21
     */
    protected function _format_username($username){

        if(empty($username)){ return ; }
        
        //如果用户的用户名是以明文手机号形式显示，则需要隐藏中间四位数
        if($this->cake24_check_mobile($username)){

            return substr_replace($username, '****', 3, 4);
        }else{

            //非手机号形式则直接返回
            return $username;
        }

    }


    /** 
     * @mall_users表用户注册统一入口
     * -----------------------------------------------------
     * @目前mall_users表中username大部分存储的是明文的手机号，现需要格外注意
     * @为了防止手机号泄露，则如果用户名是手机号的话需要隐藏手机号中间四位数
     * -----------------------------------------------------
     * @param  $params Array 用户基本信息
     * @return Array
     * @author 120291704@qq.com
     * @date 2018-08-23
     */
    protected function _userRegister($params){

        if(empty($params) || !is_array($params)){

            return [ 'status' => false, 'errorMsg' => '非法请求！' ];
        }
        
        if(!$this->cake24_check_mobile($params['mobile'])){

            return [ 'status' => false, 'errorMsg' => '手机号码格式不对！' ];
        }

        if(!empty($params['username'])){

            $username = $this->_cake24_formatUsername($params['username']);
            
        }else{

            $username = $this->_cake24_formatUsername($params['mobile']);
        }

        $encryption_mobile = $this->U_ENCRYPT($params['mobile']);
        if(empty($encryption_mobile)){

            return [ 'status' => false, 'errorMsg' => '手机号码加密错误！' ];
        }

        $user_data = $this->MallUsers->find('first', [

            'conditions' => ['encryption_mobile' => $encryption_mobile, 'is_xunbao' => 0],
            'fields'     => ['id', 'username', 'realname']
        ]);

        if(!empty($user_data['MallUsers']['id'])){

            return [ 'status' => true, 'data' => $user_data['MallUsers'] ];
        }

        $save_data = [

            'username'          => $username,
            'avatar'            => isset($params['avatar']) ? $params['avatar'] : '',
            'wx_open_id'        => isset($params['openid']) ? $params['openid'] : '',
            'unionid'           => isset($params['unionid']) ? $params['unionid'] : '',
            'mobile'            => '',
            'encryption_mobile' => $encryption_mobile,
            'password'          => isset($params['password']) ? MD5($params['password']) : MD5('88888888'),
            'pwd_change'        => 0,
            'is_wechat_bind'    => 0,
            'is_jckf'           => 0,
            'user_from'         => isset($params['user_from']) ? $params['user_from'] : 'mobile',
            'reg_laiyuan'       => isset($params['reg_laiyuan']) ? $params['reg_laiyuan'] : 'mobile',
            'page_laiyuan'      => isset($params['page_laiyuan']) ? $params['page_laiyuan'] : 'mobile',
            'from_child'        => isset($params['from_child']) ? $params['from_child'] : '',
            'is_new'            => isset($params['is_new']) ? $params['is_new'] : 0,
            'decorate_funds'    => 1000,//新注册用户直接送1000元装修基金
            'city_id'           => 72,
            'code'              => isset($params['code']) ? $params['code'] : 0,
            'lastip'            => $this->cake24_getRealIp()
        ];

        unset($params, $username, $user_data, $encryption_mobile);

        try {
            
            $result = $this->MallUsers->save($save_data);
            if($result){

                unset($result);
                $save_data['id'] = $this->MallUsers->id;
                return [ 'status' => true, 'data' => $save_data ];
            }else{

                unset($save_data);
                return [ 'status' => false, 'errorMsg' => '注册错误，请重试！' ];
            }

        } catch (Exception $error) {
            
            unset($save_data);
            return [ 'status' => false, 'errorMsg' => '注册异常，请重试！' ];
        }

    }

    /** 
      * @判断当前报名业主是否注册过
      * @param  $baoming_id Int 报名ID
      * @param  $send_message Bool 
      * @author 120291704@qq.com 2018-08-29
      * @更新于 2018-08-29
      **/
    protected function _check_bmMobile_in_mallUser($baoming_id, $send_message = false){

        if( empty($baoming_id) || !is_numeric($baoming_id) ){ return 0; }

        $bm_data  = $this->Baoming->read('mobile', $baoming_id);

        if(empty($bm_data['Baoming']['mobile'])){ return 0; }

        $mobile   = $this->U_DECRYPT($bm_data['Baoming']['mobile']);
        $userData = $this->_userRegister(['mobile' => $mobile]);//用户注册统一入口

        if($userData['status']){

            if($send_message){

                $this->_push_text_message([

                    'mobile'  => $mobile,
                    'content' => '您好，感谢您向优居客专家提问，我们已帮您注册好专家问答账号（账号即为您手机号），您随时可以登陆( http://t.cn/RBGRIw4 ）优居客专家问答—我的问答查看问题的答案或再次向专家提问。网址登陆有链接可点击。'
                ]);
            }
            
            unset($baoming_id, $bm_data, $mobile);

            return $userData['data']['id'];
        }else{

            return 0;
        }

    }

    /** 
     * @从showbm表中获取指定数量的手机号，且该手机号是隐藏了中间四位数的
     * @param  $limit  Int  限制获取的数量
     * @param  $return Bool 是否返回数据
     * @param  $overTime redis信息更新时间 默认为75 S
     * @author 120291704@qq.com 2018-10-16
     * @更新于  2018-10-16
    **/
    protected function _load_showbmData($limit = 20, $return = true, $overTime = 60){

        //判断当前Model是否存在
        $configExist = $this->cake24_check_modelExist($this->Showbm);
        if(!$configExist){ return ; }

        if( empty($limit) || !is_numeric($limit) ){ $limit = 20; }

        $key = 'GET_SHOWBM_'.$limit;
        if($this->Redis->get($key)){

            $bm_data = json_decode($this->Redis->get($key), true);

        }else{

            //获取已经获奖的前9条数据
            $conditions    = [];
            $fields        = ['*'];
            $order         = ['Showbm.addtime DESC'];
            $bm_data  = $this->Showbm->get_all('all', $conditions, $limit, $fields, $order)->reset_data()->get_reset_data();
            $this->Redis->setex($key, $overTime, json_encode($bm_data));
        }

        if($return){

            return $bm_data;
        }else{

            $this->set(compact('bm_data'));
        }
    }


    ############################ U_DECRYPT ############################

    # 加密
    public function U_ENCRYPT($mobile) {
        return U_ENCRYPT($mobile);
        
        // $wMobile = array(
        //     '18601693057', # l
        //     '13918817036', # m
        //     '15001793098', # z
        //     '13611849697', # h
        //     '18605623290', # c
        //     '17051210248'  # f
        // );
        // if (in_array($mobile, $wMobile)) {
        //     return U_ENCRYPT($mobile);
        // }
        // return $mobile;
    }

    # 解密
    // public function U_DECRYPT($mobile) {
    //     if (strlen($mobile) == 16)
    //         return U_DECRYPT($mobile); # 新存入字段 解密后返回
    //     else
    //         return $mobile; # 旧存入字段 直接返回
    // }



    # 解密
    public function U_DECRYPT($mobile) {
        if (strlen($mobile) == 16){
            # 定义手机号数组
            # 加载MODEL
            $this->loadModel('MobileDecryptLists');
            $mobile_array = $this->MobileDecryptLists->find('list',array('conditions' => array('disable' => 1),'fields' => array('id','mobile')));
            #判断当前解密的手机号是否存在数组中，如果存在数组中则记录日志           
            if(in_array($mobile, $mobile_array)) {
                $this->get_decrypt_logs($_SESSION['u_id'],$mobile); #传当前登录的用户ID
            }
            return U_DECRYPT($mobile); # 新存入字段 解密后返回
        }else{
            return $mobile; # 旧存入字段 直接返回
        }
    }


    # 调用解密方法记录日志
    private function get_decrypt_logs($uid = null,$mobile = null) {
        $project = $_SERVER['SERVER_NAME'] ? $_SERVER['SERVER_NAME'] : '';
        $u_controller = $this->params['controller'];
        $u_action = $this->params['action'];
        $machine_id = $_SESSION['machine_id']; #设备号
        $u_ip = $_SERVER["REMOTE_ADDR"];
        $posttime = date("Y-m-d H:i:s");                
       
        # 加载MODEL
        $this->loadModel('MobileDecryptLogs');
        $this->loadModel('Baoming');
        if(!empty($mobile)) {
            $baoming_id = $this->Baoming->field('id',array('mobile' => $mobile));
        }

        $data_array = array(
            'project' => $project ? $project : '',
            'controller' => $u_controller ? $u_controller : '',
            'action' => $u_action ? $u_action : '',
            'uid' => $uid ? $uid : '',
            'baoming_id' => $baoming_id ? $baoming_id : '',
            'mobile' => $mobile ? $mobile : '',
            'ip' => $u_ip ? $u_ip : '',
            'machine_id' => $machine_id ? $machine_id : '',
            'posttime' => $posttime
        );
        $this->MobileDecryptLogs->save($data_array); # 保存日志
    }






}
