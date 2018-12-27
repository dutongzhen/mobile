<?php
/**
   * @微信授权接口交互分流跳转部署
   * --------------------------------------------------------------------
   * @notice 解决根据授权回调页面域名的原则，它只能用一个域名，并且只有回调地址的域名与该设置完全相同，才能成功发起微信授权，否则就会提示rediret_uri参数错误或者引发无法回调的问题
   * @notice 增加了一次重定向操作，不过由于这个授权请求并不是所有请求都需要，所以实际上也不会对用户体验产生多大的影响，但是从架构上来说，它的好处很明显，能够配合着应用的拆分逻辑，集成同一个公众号的登录及支付功能，不必为每个子应用都单独申请一个公众号来开发
   * --------------------------------------------------------------------
   * @author 120291704@qq.com
   * @date   2018-06-26
   */

class WechatRedirectController extends AppController {

	public $name = 'WechatRedirect';

	private $appId;
	private $jssdk;//微信接口基本CLASS类
	const FETCH_CODE_URL = 'https://open.weixin.qq.com/connect/oauth2/authorize';

	public function beforeFilter() {

		parent::beforeFilter();

		$this->appId = 'wx1ace2c8f40fd6605';
		//获取微信接口基本CLASS类
        $this->jssdk = $this->_wx_jssdkClass();
	}

	public function index(){

		$this->layout = false;

		$request = $this->_YJK->reqCheck($_REQUEST);

		if(empty($request['code'])){

			$authUrl = 'https://open.weixin.qq.com/connect/oauth2/authorize';
			//引导用户进入授权页面同意授权，获取code
			$redirectUrl = urlencode('https://m.youjuke.com/WechatRedirect/index');
		    //echo $this->__createOauthUrlForCode($redirectUrl);
		    $options = [
		        $authUrl,
		        '?appid=' . $this->appId,
		        '&redirect_uri=' . $redirectUrl,
		        '&response_type=code',
		        '&scope=snsapi_base',
		        '&state=STATE',
		        '#wechat_redirect'
		    ];

		    unset($authUrl, $redirectUrl);

		    //把redirect_uri先写到cookie
		    header(implode('', [
		        "Set-Cookie: redirect_uri=",
		        urlencode($request['redirect_uri']),
		        "; path=/; domain=",
		        $this->getDomain(),
		        "; expires=" . gmstrftime("%A, %d-%b-%Y %H:%M:%S GMT", time() + 60),
		        "; Max-Age=" + 60,
		        "; httponly"
		    ]));
		    header('Location: ' . implode('', $options));

		}else{

			if (isset($_COOKIE['redirect_uri'])) {
		        $back_url = urldecode($_COOKIE['redirect_uri']);
		        header('Location: ' . implode('', [
	                $back_url,
	                strpos($back_url, '?') ? '&' : '?',
	                'code=' . $request['code'],
	                '&state=' . $request['state']
	            ]));
		    }
		}
		
		exit;
		
	}


	//引导用户进入授权页面同意授权，获取code
	private function __createOauthUrlForCode($redirectUrl) {
		
		$urlObj = [];
		$urlObj['appid']         = $this->appId;
		$urlObj['redirect_uri']  = "$redirectUrl";
		$urlObj['response_type'] = 'code';
		$urlObj['scope']         = 'snsapi_base';// 这里采用静默授权，也可以显示弹出授权页面：snsapi_userinfo
		$urlObj['state']         = 'STATE' . '#wechat_redirect';
		$bizString = http_build_query($urlObj);

		unset($urlObj);
		
		return self::FETCH_CODE_URL . "?" . $bizString;
	}

	private function getDomain() {
	    $server_name = $_SERVER['SERVER_NAME'];
	    if (strpos($server_name, 'www.') !== false) {
	        return substr($server_name, 4);
	    }
	    return $server_name;
	}

	//获取微信用户的微信基本信息
	public function get_access_token(){

		echo $this->jssdk->getAccessToken();exit;
	}

	public function test(){

		dump($this->_get_wxuserInfo());exit;
	}
}