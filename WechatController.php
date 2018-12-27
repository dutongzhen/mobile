<?php 
class WechatController extends AppController {
    
    public $name = 'Wechat';
    public $uses = array('WechatReplay', 'WechatSceneSubscribe', 'WechatMenu','Firms','FirmIntegralLogs','WechatShareLog', 'Jiugg');
    public $components = array("Common");
    private $AppID = 'wx1ace2c8f40fd6605';
    private $AppSecret = '6ba999c53b578fb9aecf4b07c17a4f81';
    private $contentStrPhoto = '<a href="http://q.markjie.com/youjukemonthcoin.html">领取码客街照片打印次数</a>
优居客，互联网装修平台~既然来了就别错过！
<a href="https://m.youjuke.com/baojia.html?putin=weixin&child=fwhm">点我查看→报价明细</a>
<a href="https://m.youjuke.com/wylb.html?putin=weixin&child=fwhm">免费获取→100套案例</a>';
    private $contentStr = '优居客，互联网装修平台~既然来了就别错过！ 

<a href="https://m.youjuke.com/zxgw.html?putin=weixin&child=fwhzdhf">立即申请→VIP装修顾问</a>

<a href="https://m.youjuke.com/wylb.html?putin=weixin&child=fwhzdhf">免费领取→100套案例</a>

<a href="https://m.youjuke.com/baojia.html?putin=weixin&child=fwhzdhf">点我获取→装修报价明细</a>  

<a href="https://m.youjuke.com/sheji.html?putin=weixin&child=fwhzdhf">免费量房→3套设计方案</a>

<a href="https://m.youjuke.com/daikuan.html?putin=weixin&child=fwhzdhf">点我申请→装修免息贷款</a>

客服微信号：youjuke02';

    
    public function index(){//第三方微信
    
        $this->layout = false;
        $this->autoRender = false;
        
        // define("TOKEN", "youjuke");
        // $this->valid();
        
        $this->responseMsg();
    
    }
    
    public function valid()
    {
        $echoStr = $_GET["echostr"];

        //valid signature , option
        if($this->checkSignature()){
            echo $echoStr;
            exit;
        }
    }
    
    private function checkSignature()
    {
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];    

        $token = TOKEN;
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr );
        
        if( $tmpStr == $signature ){
            return true;
        }else{
            return false;
        }
    }
    
    public function responseMsg()
    {
        //get post data, May be due to the different environments
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];

        //extract post data
        if (!empty($postStr)){
                
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $fromUsername = $postObj->FromUserName;
            $toUsername = $postObj->ToUserName;//开发者信息
            $keyword = trim($postObj->Content);
            $RX_TYPE = trim($postObj->MsgType);
            // $this->EventKey = trim($postObj->EventKey);
            // $this->Ticket = trim($postObj->Ticket);
            // $this->Event = trim($postObj->Event);
            
            $time = time();

            switch ($RX_TYPE){
                case "text":
                    $resultStr = $this->receiveText($postObj);
                    break;
                case "event":
                    $resultStr = $this->receiveEvent($postObj);
                    break;
                default:
                    $resultStr = "";
                    break;
            }
            echo $resultStr;
                
        }else {
            echo "";
            exit;
        }
    }

    //多客服
    private function loadduokefu($keyword, $object){

        $keywordData = $this->Common->loadConfig(array('wx_keyword_arr'));
        $keywordArr = $keywordData['wx_keyword_arr'];
        
        foreach($keywordArr as $key=>$value){

            if(strstr($keyword, $value)){

                $resultStr = $this->transmitService($object);
                return $resultStr;
                break;
            }
        }
    }

    /**
    * 通过手机号查询当前手机号获得的奖品信息
    * @author dtz 20170717
    * @param  number $mobile
    * @return bool true / false
    */
    private function _load_lottery_info($mobile){

        if(!$mobile){ return ; }

        $prize_info_arr = $this->Common->loadConfig('wechat_lottory_prize');

        $prize_data = $this->Jiugg->find('first', [
            'conditions' => ['Jiugg.mobile' => $mobile, 'Jiugg.branch' => 3],
            'fields'     => ['Jiugg.prize_id'],
            'order'      => ['Jiugg.posttime DESC']
        ]);
        return $prize_info_arr[$prize_data['Jiugg']['prize_id']]['prize_info'];
    }


    private function receiveText($object){

        $keyword = trim($object->Content);
        
        $kefuresultStr = $this->loadduokefu($keyword, $object);//多客服功能
        if($kefuresultStr){return $kefuresultStr;}

        /***判断输入的内容是否是手机号,如果是手机号则从后台查询是否有当前手机号的获奖信息 dtz 20170717 start***/
        if($this->cake24_check_mobile($keyword)){

            $prize_info = $this->_load_lottery_info($keyword);
            if(!$prize_info){ return $this->transmitText($object, '亲爱的小主，未查到您的中奖信息，请核对您报名的手机号是否正确，也可直接在线咨询我，说明情况哦！'); }

            $resultStr = $this->transmitText($object, $prize_info);
            return $resultStr;
        }elseif ($keyword == '使用') {
            
            return $this->transmitText($object, '尊敬的业主，优居客客服已收到您的使用请求，将在24小时内联系您确认，请您注意接听！');
        }
        /***判断输入的内容是否是手机号,如果是手机号则从后台查询是否有当前手机号的获奖信息 dtz 20170717 end***/

        $contentStr = $this->_getNewsData($keyword, $object);
        if(!empty($contentStr)){

            $resultStr = $this->transmitNews($object, $contentStr);
            return $resultStr;
        }else{

            $data = array('replay_type'=> 2, 'limit' => 1); //数据类型为文本类型
            $replayData = $this->get_wechat_replay($keyword, $object , $data);
            $contentStr = $replayData[0]['replay_text'];

            if(!empty($contentStr)){

                $resultStr = $this->transmitText($object, $contentStr);
                return $resultStr;
            }
        }
    }

    //回复多客服消息
    private function transmitService($object){
        
        $xmlTpl = "<xml>
            <ToUserName><![CDATA[%s]]></ToUserName>
            <FromUserName><![CDATA[%s]]></FromUserName>
            <CreateTime>%s</CreateTime>
            <MsgType><![CDATA[transfer_customer_service]]></MsgType>
        </xml>";
        $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time());
        return $result;
    }

    //发起微信会话连接消息
    private function _sendMessage($object){

        $content = '欢迎来到优居客~
在线客服为您服务（9:30-18:30）：
想获得更多精彩内容点击栏目下方哦↓↓↓';
        App::import('Vendor', 'jssdk');
        //获取微信基本信息
        $jssdk = new JSSDK($this->AppID, $this->AppSecret);
        $jssdk->send_message($object->FromUserName, $content);
    }

    
    private function _getNewsData($keyword,$object, $limit=4){
        
        $historyData = $this->get_wechat_replay($keyword,$object);

        //$historyData = json_decode($res, true);
        
        unset($historyData['code']);
        unset($historyData['msg']);
        
        if(count($historyData) <= $limit){ return $historyData;}
        $keyArr = array_rand($historyData, $limit);
        $historyData2 = array();
        foreach($keyArr as $key){
            
            array_push($historyData2, $historyData[$key]);
        }
        return $historyData2;
        
    }
    
    private function transmitNews($object, $arr_item){
        
        $itemTpl = "<item>
            <Title><![CDATA[%s]]></Title>
            <Description><![CDATA[%s]]></Description>
            <PicUrl><![CDATA[%s]]></PicUrl>
            <Url><![CDATA[%s]]></Url>
        </item>
        ";

        $wxuserData = $this->_getWxuserInfo($object->FromUserName);
        $nick_name = $this->emoji_replace( $wxuserData['nickname'] );
        
        $item_str = "";
        foreach ($arr_item as $key => $item){
            if($key == 0){

                $item_str .= sprintf($itemTpl, $nick_name.'，'.$item['title'], $item['description'], $item['picUrl'], $item['url']);
            }else{

                $item_str .= sprintf($itemTpl, $item['title'], $item['description'], $item['picUrl'], $item['url']);
            }
            
        }
        
        $newsTpl = "<xml>
            <ToUserName><![CDATA[%s]]></ToUserName>
            <FromUserName><![CDATA[%s]]></FromUserName>
            <CreateTime>%s</CreateTime>
            <MsgType><![CDATA[news]]></MsgType>
            <ArticleCount>%s</ArticleCount>
            <Articles>
                $item_str
            </Articles>
        </xml>";

        $resultStr = sprintf($newsTpl, $object->FromUserName, $object->ToUserName, time(), count($arr_item));
        return $resultStr;
    }
    
    
    private function receiveEvent($object){
        
        $contentStr = "";

        switch ($object->Event)
        {
            case "subscribe":
                
                $sence_id = (int)substr($object->EventKey, 8);
                if($sence_id > 90000){

                    $contentStr = "您正在使用优居客微信登录，<a href='https://m.youjuke.com/wechat_login/".$object->FromUserName."/".$sence_id.".html' >请点击确认登录</a>";
                    $cj_status_data = 2;
                }elseif($sence_id < 90000 && $sence_id > 80000){

                    $outtime = time()+60;
                    $contentStr = "您正在使用优居客微信绑定，<a href='https://m.youjuke.com/wechat_bind/".$object->FromUserName."/".$sence_id."/".$outtime.".html' >请点击确认绑定</a>";
                    $cj_status_data = 1;

                }elseif($sence_id < 70000 && $sence_id > 60000){//添加图库案例渠道
                    
                    $id = (int) $sence_id-60000;
                    $contentStr = "恭喜您收藏成功，点击查看<a href='https://m.youjuke.com/anli_detail_".$id.".html' >效果图</a>";
                    $cj_status_data = 0;

                }else if( $sence_id == 79960 ){
                
                    $contentStr = $this->contentStrPhoto;

                }else if( $sence_id == 79944 ){
                
                    $contentStr = '亲爱的小主，恭喜您中奖了，请回复报名手机号验证中奖信息';

                }else{
                    if( !empty( substr($object->EventKey, 8) ) )
                    {
                        if( !preg_match('/^[0-9]+$/', substr($object->EventKey, 8) ) )
                        {
                            return false;
                        }
                    }

                    // $contentStr = $this->contentStr;
                    $contentNews = $this->_getNewsData('关注');//关注时获取图文消息
        
                    $cj_status_data = 0;
                    //商家关注微信增加积分
                    if($sence_id > 0 && $sence_id < 70000 && $sence_id != 337)
                    {
                        $this->Firms->unbindModel(array(
                            'hasMany' => array('FirmCases')
                        ));
                        $firm_openid = $object->FromUserName;
                        $is_count = $this->FirmIntegralLogs->find('count',array('conditions'=>array('status'=>7,'firm_id'=>$sence_id,'belongs_id'=>$firm_openid)));
                        if(!$is_count)
                        {
                            $firms_count = $this->Firms->find('count',array('conditions'=>array('Firms.id'=>$sence_id,'Firms.status'=>1)));
                            if($firms_count)
                            {
                                $Firms = $this->Firms->updateAll(
                                    array('integral'=>'`integral`+5'),
                                    array('Firms.id'=>$sence_id,'Firms.status'=>1)
                                );
                                $firms_data['firm_id'] = $sence_id;
                                $firms_data['add_time'] = date('Y-m-d H:i:s');
                                $firms_data['integral_class'] = 0;
                                $firms_data['integral_state'] = '+5';
                                $firms_data['explain'] = '微信关注';
                                $firms_data['belongs_id'] = $firm_openid;
                                $firms_data['status'] = 7;
                                $this->FirmIntegralLogs->save($firms_data);
                            }
                        }    
                    } 
                }

                $this->add_subscribe_log($object,$cj_status_data);
                break;
            case "unsubscribe":
                $this->unsubscribe_log($object);
                break;
            case "CLICK":
                switch ($object->EventKey){
                    
                    case "view_address":
                        $contentStr = '上海市普陀区曹杨路272号优居客大楼';
                        break;
                }
                
                break;
            case "SCAN":

                $EventKey = (int)($object->EventKey);
                if($EventKey > 90000){

                    $is_count = $this->WechatSceneSubscribe->find('count',array('conditions' => array(
                                'from_open_id' => $object->FromUserName,
                                'wx_sence' => 'qrscene_'.$EventKey
                            )
                        )
                    );
                    if($is_count)
                    {
                        $contentStr = "此二维码已失效，请刷新页面重新扫描";
                    }
                    else
                    {
                        $this->wx_is_event($object);
                        $contentStr = "您正在使用优居客微信登录，<a href='https://m.youjuke.com/wechat_login/".$object->FromUserName."/".$EventKey.".html' >请点击确认登录</a>";
                    }

                }elseif($EventKey < 90000 && $EventKey > 80000){

                    $outtime = time()+60;
                    $contentStr = "您正在使用优居客微信绑定，<a href='https://m.youjuke.com/wechat_bind/".$object->FromUserName."/".$EventKey."/".$outtime.".html' >请点击确认绑定</a>";
                
                }elseif($EventKey < 70000 && $EventKey > 60000){//添加图库案例渠道
                    
                    $id = (int) $EventKey-60000;
                    $contentStr = "恭喜您收藏成功，点击查看<a href='https://m.youjuke.com/anli_detail_".$id.".html' >效果图</a>";
                }
                break;
            case "LOCATION":
                $this->updateWxLocation($object);
                break;
                
            default:
                break;
        }

        if(!empty($contentStr)){

            $resultStr = $this->transmitText($object, $contentStr);
            return $resultStr;
        }elseif(!empty($contentNews)){

            $this->_sendMessage($object);
            $resultStr = $this->transmitNews($object, $contentNews);
            return $resultStr;
        }
        
    }

    //重新更新微信地理位置
    private function updateWxLocation($object){

        $latitude = $object->Latitude;
        $longitude = $object->Longitude;
        $from_open_id = $object->FromUserName;
        //$locationData = $this->cake24_getLocation($latitude, $longitude);
        $locationData = $this->cake24_getAddress($latitude, $longitude);

        $condition = array('from_open_id' => $object->FromUserName);
        $data = array('province' => "'".$locationData['province']."'", 'city' => "'".$locationData['city']."'", 'area' => "'".$locationData['dist']."'");

        $this->WechatSceneSubscribe->updateAll($data, $condition);
    }

    //微信登录场景判断是否扫描
    private function wx_is_event($object)
    {
        $EventKey = $object->EventKey;
        if($EventKey <= 90000){return ;}
        /*$is_data = $this->WechatSceneSubscribe->find('first', 
            array(
                'fields'=>array('WechatSceneSubscribe.id','WechatSceneSubscribe.from_open_id','WechatSceneSubscribe.wx_sence','WechatSceneSubscribe.create_time','WechatSceneSubscribe.cj_status'),
                'conditions' => array(
                    'from_open_id' => $object->FromUserName,
                    //'wx_sence like' => '%qrscene_9%'
                    'cj_status' =>2
                ),
                'order' => array('WechatSceneSubscribe.id desc')
            )
        );
        if($is_data)
        {
            $wx_sence = 'qrscene_'.$EventKey;
            $arrs = array('wx_sence'=>"'".$wx_sence."'",'login_time'=>time() + 35);
            $this->WechatSceneSubscribe->updateAll($arrs, array('WechatSceneSubscribe.id'=>$is_data['WechatSceneSubscribe']['id']));
        }*/
        $this->add_subscribe_log($object,2,false);

    }

    //$object  获取微信返回的对象  $is_sub  true 关注 false  $cj_status_data  场景来源 2 微信登录 1微信绑定 0其他
    private function add_subscribe_log($object,$cj_status_data=0,$is_sub = true)
    {

        $conditions['from_open_id'] = $object->FromUserName; 
        $wx_sence = !empty($object->EventKey) ? ($is_sub ? $object->EventKey : 'qrscene_'.$object->EventKey)  : 'qrscene_0';
        $explode = explode('_', $wx_sence);
        $wx_sence = $explode[0] == 'qrscene' ? $wx_sence : 'qrscene_0';
        $Ticket = !empty($object->Ticket) ? $object->Ticket : 'youjuke';

        //判断 是微信场景 
        if($cj_status_data  == 2)
        {
            $conditions['cj_status'] = 2;
        }
        else
        {
            //$conditions['wx_ticket'] = $Ticket;
            $conditions['wx_sence'] = $wx_sence;
        }

        $is_exist = $this->WechatSceneSubscribe->find('count', array('conditions' => $conditions) );

        $wxuserData = $this->_getWxuserInfo($object->FromUserName);
        $nick_name = $this->emoji_replace( $wxuserData['nickname'] );

        if($is_exist)
        {

            $FromUserName = $object->FromUserName;
            
            //$condition_update = array('from_open_id' => $object->FromUserName, 'wx_ticket' => $Ticket);
            $data = array(
                'wx_sence' => "'".$wx_sence."'",
                'login_time'  => time()+35,
            );
            //是关注才修改
            if($is_sub)
            {
                $data['wx_sub_time'] = $object->CreateTime;
                $data['sub_status'] = 1;
                $data['nick_name'] = "'".$nick_name."'";
                $data['province'] = "'".$wxuserData['province']."'";
                $data['city'] = "'".$wxuserData['city']."'";
            }
            $this->WechatSceneSubscribe->updateAll($data, $conditions);
        }
        else
        {

           //$wxuserData = $this->_getWxuserInfo($object->FromUserName);
            // $condition = array('from_open_id' => $object->FromUserName);
            // $data = array('sub_status' => 0);
            // $this->WechatSceneSubscribe->updateAll($data, $condition);
            $cj_status = (int)substr($wx_sence, 8);
            $cj_status = $cj_status > 90000 ? 2 : 0;

            $data = array(

                'to_wx_id' => $object->ToUserName,
                'from_open_id' => $object->FromUserName,
                'nick_name' => $nick_name,
                'wx_sub_time' => $object->CreateTime,
                'wx_event' => $object->Event,
                'wx_sence' => $wx_sence,
                'wx_ticket' => $Ticket,
                'province'  => $wxuserData['province'],
                'city'  => $wxuserData['city'],
                'create_time' => time(),
                'login_time'  => time()+35,
                'sub_status' => 1,
                'cj_status'  =>$cj_status
            );

            $this->WechatSceneSubscribe->save($data);
        }

        
    }

    private function unsubscribe_log($object){

        $FromUserName = $object->FromUserName;
        $Ticket = !empty($object->Ticket) ? $object->Ticket : 'youjuke';

        $condition = array('from_open_id' => $object->FromUserName);

        $data = array('sub_status' => 0);

        $this->WechatSceneSubscribe->updateAll($data, $condition);

    }
    
    private function transmitText($object, $content, $flag = 0){
        
        $textTpl = "<xml>
            <ToUserName><![CDATA[%s]]></ToUserName>
            <FromUserName><![CDATA[%s]]></FromUserName>
            <CreateTime>%s</CreateTime>
            <MsgType><![CDATA[text]]></MsgType>
            <Content><![CDATA[%s]]></Content>
            <FuncFlag>%d</FuncFlag>
        </xml>";
        
        $resultStr = sprintf($textTpl, $object->FromUserName, $object->ToUserName, time(), $content, $flag);
        return $resultStr;
    }


    /*
     *根据关键词查询微信文章触发回复信息 - curl提交
    */
    public function curl_get_wechat_replay()
    {
        $this->layout = false;
        $this->autoRender = false;
        $data = $this->request->data;
        //获取头部信息  
        $getallheaders = $this->Wechat_getallheaders();

        //判断提交过来的访问密码是否正确
        $apikey = trim($getallheaders['Apikey']);
        if($apikey == '10a46f447e6bedf8abc589ca8916aa3b')
        {
            $key_words = trim($this->request->query['key_words']);
            $array = $this->get_wechat_replay($key_words);
        }
        else
        {
            $array = array('code'=>10402,'msg'=>'没有访问权限');
        }
        
        echo json_encode($array);die();
    }

    /*
     *根据关键词查询微信文章触发回复信息 - 内部调用
    */
    private function get_wechat_replay($key_words = '新闻',$object='',$data = array('replay_type'=> 1))
    {
        //关键词
        $key_words = trim($key_words);
        //数据限制个数
        $limit = array_key_exists('limit', $data) ? $data['limit'] : 6;
        //数据排序设置
        $order = array_key_exists('order', $data) ? (is_array($data['order']) ? $data['order'] : array('WechatReplay.create_time '.$data['order'])) : array('WechatReplay.create_time desc');
        
        if($data['replay_type'] == 1){

            $conditions = array(
                'WechatReplay.key_words'=> $key_words,
                'WechatReplay.replay_type'=> 1,
                'WechatReplay.status'=>1
            );
            $fields = array('WechatReplay.title','WechatReplay.description','WechatReplay.picUrl','WechatReplay.key_words','WechatReplay.url','WechatReplay.replay_text');
        }else{

            $conditions = array(
                'WechatReplay.key_words LIKE'=> '%'.$key_words .'%',
                'WechatReplay.replay_type'=> 2,
                'WechatReplay.status'=>1
            );
            $fields = array('WechatReplay.replay_text');
        }
        $conditions['WechatReplay.wechat_type'] = 0;//服务号文章
        $WechatReplayData = $this->WechatReplay->find(
            'all',
            array(
                'fields'=>$fields,
                'conditions'=>$conditions,
                'order'=>$order,
                'limit'=>$limit
            )
        );

        /**记录关键词回复记录数**/
        if( $object )
        {
            $wxuserData = $this->_getWxuserInfo($object->FromUserName);
            foreach ($WechatReplayData as $key => $value) 
            {
                $this->WechatShareLog->clear();
                $is_log_data = $this->WechatShareLog->find('count',array(
                        'conditions' => array(
                            'WechatShareLog.openid'=>$wxuserData['openid'],
                            'WechatShareLog.share_url' => $value['WechatReplay']['url'],
                            'WechatShareLog.actions' => '微信文章回复'
                        ),
                        //'fields' => array('id','openid')
                    )
                );
                if(!$is_log_data)
                {
                    $nickname = !empty($wxuserData['nickname']) ? $this->emoji_replace( $wxuserData['nickname'] ) : '暂无昵称';
                    $wsl_data = array(
                        'openid' => $wxuserData['openid'],
                        'nickname' => $nickname,
                        'actions' => '微信文章回复',
                        'share_kwd' => $value['WechatReplay']['key_words'],
                        'share_url' => $value['WechatReplay']['url'],
                        'times' => time()
                    );
                    $this->WechatShareLog->save($wsl_data);
                }
                
            }
            
        }
        /**记录关键词回复记录数**/
        
        if($WechatReplayData)
        {
            foreach($WechatReplayData as $k => $v) 
            {
                $array[$k] = $v['WechatReplay'];
            }
            $array['code'] = 10200;
            $array['msg'] = 'ok';
        }
        else
        {
            $array = array('code'=>10401,'msg'=>'暂无数据');
        }
        return $array;
    }


    //获取header 信息
    private function Wechat_getallheaders()
    {
        if (!function_exists('getallheaders'))
        {
            foreach ($_SERVER as $name => $value)
            {
               if (substr($name, 0, 5) == 'HTTP_')
               {
                   $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
               }
            }
        }
        else
        {
            $headers = getallheaders();
        }
        
       return $headers;
    }

    //过滤emoji表情
    private function emoji_replace($str)
    {
        $tmpStr = json_encode($str);  
        $tmpStr = preg_replace("#(\\\ud[0-9a-f]{3})|(\\\ue[0-9a-f]{3})#ie","",$tmpStr); //将emoji的unicode置为空，其他不动  
        return json_decode($tmpStr, true);
    }

    private function _getWxuserInfo($openid){

        if(empty($openid)){return ;}

        App::import('Vendor', 'jssdk');
        //获取微信基本信息
        $jssdk = new JSSDK($this->AppID, $this->AppSecret);
        
        return $user_info = $jssdk->getUserInfo($openid);
    }

    public function create_wechat_qrcode($scene_id){

        $this->layout = false;
        if(empty($scene_id)){

            $scene_id = $this->request->query('scene_id');
        }
        App::import('Vendor', 'jssdk');
        $jssdk = new JSSDK($this->AppID, $this->AppSecret);
        $data = $jssdk->create_qrcode('QR_LIMIT_SCENE', $scene_id);
        $this->set('data', $data);

        $imageInfo = $this->downloadImageFromWeixin($data);
        $filename = 'wechat_'.$scene_id.'.jpg';
        $local_file = fopen($filename, 'w');
        if(false !== $local_file){

            if(false !== fwrite($local_file, $imageInfo['body'])){

                fclose($local_file);
            }
        }

        //从服务器端现在二维码图片到本地
        $fileinfo = pathinfo($filename);
        header('Content-type: application/x-'.$fileinfo['extension']);
        header('Content-Disposition: attachment; filename='.$fileinfo['basename']);
        header('Content-Length: '.filesize($filename));
        readfile($filename);
        unlink($filename);

    }

    //从微信端通过ticket获取到二维码图片，并且放到服务器
    public function downloadImageFromWeixin($url){

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_NOBODY, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $package = curl_exec($ch);
        $httpinfo = curl_getinfo($ch);
        curl_close($ch);
        return array_merge(array('body' => $package), array('header' => $httpinfo));

    }

    //微信扫描登录二维码
    public function create_wechat_login_qrcode(){

        $this->layout = false;
        $this->autoRender = false;
        $scene_id = $this->request->query('scene_id');
        $expire_seconds = 60;

        App::import('Vendor', 'jssdk');
        $jssdk = new JSSDK($this->AppID, $this->AppSecret);
        $data = $jssdk->create_qrcode('QR_SCENE', $scene_id, $expire_seconds);
        return $data;exit;
    }

    //短期二维码
    public function create_wechat_qrcode_forweb($scene_id, $expire_days){

        $this->layout = false;
        if(empty($scene_id)){

            $scene_id = $this->request->query('scene_id');
        }
        if(empty($expire_days)){

            $expire_days = (int)$this->request->query('expire_days');
        }


        if(is_numeric($expire_days)){

            if($expire_days > 30){
                echo '临时二维码最长可以设置为30天!';exit;
            }
            $expire_seconds = $expire_days*86400;
        }else{

            $expire_seconds = 86400;
        }
        
        App::import('Vendor', 'jssdk');
        $jssdk = new JSSDK($this->AppID, $this->AppSecret);
        $data = $jssdk->create_qrcode('QR_SCENE', $scene_id, $expire_seconds);
        
        $this->set('data', $data);
        $this->render('create_wechat_qrcode');

    }

    //长期二维码
    public function create_wechat_qrcode_forweb2($scene_id){

        $this->layout = false;
        $this->autoRender = false;
        if(empty($scene_id)){

            $scene_id = $this->request->query('scene_id');
        }
        App::import('Vendor', 'jssdk');
        $jssdk = new JSSDK($this->AppID, $this->AppSecret);
        $data = $jssdk->create_qrcode('QR_LIMIT_SCENE', $scene_id);
        return $data;exit;

    }

    //生成微信自定义菜单（服务号）
    public function create_wechat_menu(){

        $this->layout = false;
        $this->autoRender = false;

        App::import('Vendor', 'jssdk');

        $mp_type = 1;//服务号
        $jssdk = new JSSDK($this->AppID, $this->AppSecret);

        $condition = array('WechatMenu.mp_type' => $mp_type);

        $menuData = $this->WechatMenu->find('first', array('conditions' => $condition));
        if($mp_type != $menuData['WechatMenu']['mp_type']){echo json_encode(array('errmsg' => '公众号类型错误！'));exit;}

        $wechatMenu = $menuData['WechatMenu']['menu_char'];
        echo (json_encode($jssdk->create_menu($wechatMenu)));exit;
    }

    /*
    * 发送消息模板（任务处理通知）
    */
    public function set_message_template(){
        $this->layout = false;
        $this->autoRender = false;
        $data = $this->request->data;
        if( $data['password'] != '51youjuke' ){
            die( 'Error' );
        }
        unset($data['password']);
        // $data = array(
        //     'openid' => 'oYQ0AuJItPrC51jIqniS75s2jUcc',
        //     'url' => 'http://www.baidu.com',
        //     'title' => '测试杜',
        //     'rw_name' => '测试name',
        //     'tz_type' => '测试通知类型',
        //     'remark' => '测试内容'
        // );
        // $data = array(
        //     'template_id'=> 'FsZCU2kikOKAU_IcinKjQE3kmGRQ8YKI6ArjIz4q9Co',
        //     'openid' => 'oYQ0AuJItPrC51jIqniS75s2jUcc',
        //     'url' => 'http://www.baidu.com',
        //     'service_info' => '测试杜',
        //     'service_type' => '问题咨询',
        //     'service_status' => '已解决',
        //     'time' => date('Y-m-d H:i:s'),
        //     'remark' => '测试内容'
        // );

        App::import('Vendor', 'jssdk');
        $jssdk = new JSSDK($this->AppID, $this->AppSecret);
        $res = $jssdk->set_message_template($data);
        return $res;
    }

}