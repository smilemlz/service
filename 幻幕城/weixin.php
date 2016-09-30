<?php

/**
 * ECTouch Open Source Project
 * ============================================================================
 * Copyright (c) 2012-2014 http://ectouch.cn All rights reserved.
 * ----------------------------------------------------------------------------
 * 文件名称：wechat.php
 * ----------------------------------------------------------------------------
 * 功能描述：微信登录插件
 * ----------------------------------------------------------------------------
 * Licensed ( http://www.ectouch.cn/docs/license.txt )
 * ----------------------------------------------------------------------------
 */

/* 访问控制 */
defined('IN_ECTOUCH') or die('Deny Access');

$payment_lang = ROOT_PATH . 'plugins/connect/languages/' . C('lang') . '/' . basename(__FILE__);

if (file_exists($payment_lang)) {
    include_once ($payment_lang);
    L($_LANG);
}
/* 模块的基本信息 */
if (isset($set_modules) && $set_modules == TRUE) {
    $i = isset($modules) ? count($modules) : 0;
    /* 类名 */
    $modules[$i]['name'] = '微信登录插件';
    // 文件名，不包含后缀
    $modules[$i]['type'] = 'weixin';

    $modules[$i]['className'] = 'weixin';
    // 作者信息
    $modules[$i]['author'] = 'ECTouch Team';

    // 作者QQ
    $modules[$i]['qq'] = '10000';

    // 作者邮箱
    $modules[$i]['email'] = 'support@ectouch.cn';

    // 申请网址
    $modules[$i]['website'] = 'http://mp.wexin.qq.com';

    // 版本号
    $modules[$i]['version'] = '1.0';

    // 更新日期
    $modules[$i]['date'] = '2014-8-19';
    /* 配置信息 */
    $modules[$i]['config'] = array(
        array('type' => 'text', 'name' => 'app_id', 'value' => ''),
        array('type' => 'text', 'name' => 'app_secret', 'value' => ''),
        array('type' => 'text', 'name' => 'token', 'value' => ''),
    );
    return;
}

/**
 * WECHAT API client
 */
class weixin {

    private $token = '';
    private $appid = '';
    private $appkey = '';
    private $weObj = '';

    /**
     * 构造函数
     *
     * @param unknown $app            
     * @param string $access_token            
     */
    public function __construct($conf) {
        $this->token = $conf['token'];
        $this->appid = $conf['app_id'];
        $this->appsecret = $conf['app_secret'];

        $config['token'] = $this->token;
        $config['appid'] = $this->appid;
        $config['appsecret'] = $this->appsecret;

        $this->weObj = new Wechat($config);
    }

    /**
     * 授权登录地址
     */
    public function act_login($info, $url){
        // 微信浏览器浏览
        if (is_wechat_browser() && ($_SESSION['user_id'] === 0 || empty($_SESSION['openid']))) {
            return $this->weObj->getOauthRedirect($url, 1);
        }
        else{
            show_message("请在微信内访问或者已经登录。", L('relogin_lnk'), url('login', array(
                'referer' => urlencode($this->back_act)
                    )), 'error');
        }
    }

    /**
     * 登录处理
     */
    public function call_back($info, $url, $code, $type)
    {
        if (!empty($code)) {
            $token = $this->weObj->getOauthAccessToken();
            $userinfo = $this->weObj->getOauthUserinfo($token['access_token'], $token['openid']);
            $_SESSION['wechat_user'] = empty($userinfo) ? array() : $userinfo;
            if (!empty($userinfo)) {					//用户信息不为空
                //公众号信息
                $wechat = model('Base')->model->table('wechat')->field('id, oauth_status')->where(array('type' => 2, 'status' => 1, 'default_wx' => 1))->find();
                $this->update_weixin_user($userinfo, $wechat['id'], $this->weObj);
                return $url;
            } else {
                return false;
            }

        } else {
            return false;
        }
    }

    /**
     * 更新微信用户信息
     *
     * @param unknown $userinfo          
     * @param unknown $weObj            
     */
    public function update_weixin_user($userinfo, $wechat_id = 0, $weObj)
    {
        $time = time();
        $ret = model('Base')->model->table('wechat_user')->field('openid, ect_uid')->where('openid = "' . $userinfo['openid'] . '"')->find();
        if(isset($ret['ect_uid']) && $ret['ect_uid'] == 0){
            model('Base')->model->table('wechat_user')->where('openid = "' . $userinfo['openid'] . '"')->delete();
        }
        if (empty($ret) || empty($ret['ect_uid'])) {
            //微信用户绑定会员id
            $ect_uid = 0;
            //查看公众号是否绑定
            if($userinfo['unionid']){
                $ect_uid = model('Base')->model->table('wechat_user')->field('ect_uid')->where(array('unionid'=>$userinfo['unionid']))->getOne();
            }

            //未绑定
            if(empty($ect_uid)){
                // 设置的用户注册信息
                $register = model('Base')->model->table('wechat_extend')
                    ->field('config')
                    ->where('enable = 1 and command = "register_remind" and wechat_id = '.$wechat_id)
                    ->find();
                if (! empty($register)) {
                    $reg_config = unserialize($register['config']);
                    $username = msubstr($reg_config['user_pre'], 3, 0, 'utf-8', false) . time().mt_rand(1, 99);
                    // 密码随机数
                    $rs = array();
                    $arr = range(0, 9);
                    $reg_config['pwd_rand'] = $reg_config['pwd_rand'] ? $reg_config['pwd_rand'] : 3;
                    for ($i = 0; $i < $reg_config['pwd_rand']; $i ++) {
                        $rs[] = array_rand($arr);
                    }
                    $pwd_rand = implode('', $rs);
                    // 密码
                    $password = $reg_config['pwd_pre'] . $pwd_rand;
                    // 通知模版
                    $template = str_replace(array(
                        '[$username]',
                        '[$password]'
                    ), array(
                        $username,
                        $password
                    ), $reg_config['template']);
                } else {
                    $username = 'wx_' . time().mt_rand(1, 99);
                    $password = 'ecmoban';
                    // 通知模版
                    $template = '默认用户名：' . $username . "\r\n" . '默认密码：' . $password;
                }
                // 会员注册
                $domain = get_top_domain();
                if (model('Users')->register($username, $password, $username . '@' . $domain, array('parent_id'=>intval($_GET['u']))) !== false) {
                    model('Users')->update_user_info();
                } else {
                    die('授权失败，可能需要联系管理员开放会员注册。');
                }
                $data1['ect_uid'] = $_SESSION['user_id'];
            }
            else{
                //已绑定
                $username = model('Base')->model->table('users')->field('user_name')->where(array('user_id'=>$ect_uid))->getOne();
                $template = '您已拥有帐号，用户名为'.$username;
                $data1['ect_uid'] = $ect_uid;
            }
            
            // 获取用户所在分组ID
            $group_id = $weObj->getUserGroup($userinfo['openid']);
            $group_id = $group_id ? $group_id : 0;

            $data1['wechat_id'] = $wechat_id;
            $data1['subscribe'] = 0;
            $data1['openid'] = $userinfo['openid'];
            $data1['nickname'] = $userinfo['nickname'];
            $data1['sex'] = $userinfo['sex'];
            $data1['city'] = $userinfo['city'];
            $data1['country'] = $userinfo['country'];
            $data1['province'] = $userinfo['province'];
            $data1['language'] = $userinfo['country'];
            $data1['headimgurl'] = $userinfo['headimgurl'];
            $data1['subscribe_time'] = $time;
            $data1['group_id'] = $group_id;
            $data1['unionid'] = $userinfo['unionid'];
            
            model('Base')->model->table('wechat_user')->data($data1)->insert();
        } else {
            //开放平台有privilege字段,公众平台没有
			
            unset($userinfo['privilege']);
            model('Base')->model->table('wechat_user')->data($userinfo)->where(array('openid'=> $userinfo['openid']))->update();
            $new_user_name = model('Base')->model->table('users')->field('user_name')->where(array('user_id'=>$ret['ect_uid']))->getOne();
            ECTouch::user()->set_session($new_user_name);
            ECTouch::user()->set_cookie($new_user_name);
            model('Users')->update_user_info();
        }
        $_SESSION['openid'] = $userinfo['openid'];
        setcookie('openid', $userinfo['openid'], gmtime() + 86400 * 7);
    }

}
