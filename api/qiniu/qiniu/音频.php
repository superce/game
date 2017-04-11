<?php
/**
 * 女神来了模块定义
 *
 * @author 霸途科技
 * @url http://bbs.012wz.com/
 */
defined('IN_IA') or exit('Access Denied');

class fm_photosvoteModuleSite extends WeModuleSite {	
	public $title 			 = '女神来了！';
	public $table_reply  	 = 'fm_photosvote_reply';//规则 相关设置
	public $table_users  	 = 'fm_photosvote_provevote';	//报名参加活动的人
	public $table_users_voice  	 = 'fm_photosvote_provevotevoice';	//
	public $table_log        = 'fm_photosvote_votelog';//投票记录
	public $table_bbsreply   = 'fm_photosvote_bbsreply';//投票记录
	public $table_banners    = 'fm_photosvote_banners';//幻灯片
	public $table_advs  	 = 'fm_photosvote_advs';//广告
	public $table_gift  	 = 'fm_photosvote_gift';
	public $table_data  	 = 'fm_photosvote_data';
	public $table_iplist 	 = 'fm_photosvote_iplist';//禁止ip段
	public $table_iplistlog  = 'fm_photosvote_iplistlog';//禁止ip段
	public $table_announce   = 'fm_photosvote_announce';//公告

	public function doMobilelisthome() {
		//这个操作被定义用来呈现 微站首页导航图标
		$this->doMobilelistentry();	
	}
	
	public function getTiles($keyword = '') {
		global $_GPC,$_W;
		$uniacid = $_W['uniacid'];
		$urls = array();
		$list = pdo_fetchall("SELECT id FROM ".tablename('rule')." WHERE uniacid = ".$uniacid." and module = 'fm_photosvote'".(!empty($keyword) ? " AND name LIKE '%{$keyword}%'" : ''));
		if (!empty($list)) {
			foreach ($list as $row) {
			    $reply = pdo_fetch("SELECT title FROM ".tablename($this->table_reply)." WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $row['id']));
				$urls[] = array('title'=>$reply['title'], 'url'=> $_W['siteroot'] .'app/'.$this->createMobileUrl('photosvoteview', array('rid' => $row['id'])));
			}
		}
		return $urls;
	}
    //入口列表
	public function doMobilelistentry() {
		global $_GPC,$_W;
		$uniacid = $_W['uniacid'];
		$time = time();
		$from_user = $_W['openid'];
		$from_user = base64_encode(authcode($from_user, 'ENCODE'));

		$cover_reply = pdo_fetch("SELECT * FROM ".tablename("cover_reply")." WHERE uniacid = :uniacid and module = 'fm_photosvote'", array(':uniacid' => $uniacid));
		$reply = pdo_fetchall("SELECT * FROM ".tablename($this->table_reply)." WHERE uniacid = :uniacid and status = 1 and start_time<".$time."  and end_time>".$time." ORDER BY `end_time` DESC", array(':uniacid' => $uniacid));

		foreach ($reply as $mid => $replys) {
			$reply[$mid]['num'] = pdo_fetchcolumn("SELECT COUNT(*) FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and rid = :rid", array(':uniacid' => $_W['uniacid'], ':rid' => $replys['rid']));
			$reply[$mid]['is'] = pdo_fetchcolumn("SELECT COUNT(*) FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and rid = :rid and from_user = :from_user", array(':uniacid' => $uniacid, ':rid' => $replys['rid'], ':from_user' => $from_user));
			$picture = $replys['picture'];
			if (substr($picture,0,6)=='images'){
			    $reply[$mid]['picture'] = $_W['attachurl'] . $picture;
			}else{
			    $reply[$mid]['picture'] = $_W['siteroot'] . $picture;
			}
		}

		//查询参与情况
		$usernum = pdo_fetchcolumn("SELECT COUNT(*) FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and from_user = :from_user", array(':uniacid' => $uniacid, ':from_user' => $from_user));

	    $user_agent = $_SERVER['HTTP_USER_AGENT'];
		if (strpos($user_agent, 'MicroMessenger') === false) {
			echo "本页面仅支持微信访问!非微信浏览器禁止浏览!";
			//include $this->template('listentry');
		} else { 
			include $this->template('listentry');
		}		
	}
	function get_share($uniacid,$rid,$from_user,$title) {
		if (!empty($rid)) {
			$reply = pdo_fetch("SELECT xuninum FROM ".tablename($this->table_reply)." WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $rid));
		    $listtotal = pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_users).' WHERE uniacid= :uniacid AND rid= :rid', array(':uniacid' => $uniacid,':rid' => $rid));//参与人数
			$listtotal = $listtotal+$reply['xuninum'];//总参与人数
        }		
		if (!empty($from_user)) {
		    $userinfo = pdo_fetch("SELECT nickname,realname FROM ".tablename($this->table_users)." WHERE uniacid= :uniacid AND rid= :rid AND from_user= :from_user", array(':uniacid' => $uniacid,':rid' => $rid,':from_user' => $from_user));
			$nickname = empty($userinfo['realname']) ? $userinfo['nickname'] : $userinfo['realname'];
		}
		$str = array('#参与人数#'=>$listtotal,'#参与人名#'=>$nickname);
		$result = strtr($title,$str);
        return $result;
    }
	public function doMobilePhotosvote() {
		//分享页面显示。
		global $_GPC,$_W;
		$uniacid = $_W['uniacid'];//当前公众号ID
		$rid = $_GPC['rid'];
		$from_user = $_W['openid'];		
		load()->func('communication');
		load()->model('account');
		if (empty($from_user)) {
			$from_user = authcode(base64_decode($_GPC['from_user']), 'DECODE');
		}
		$from_user = base64_encode(authcode($from_user, 'ENCODE'));
		$serverapp = $_W['account']['level'];	//是否为高级号
		$cfg = $this->module['config'];
	    $appid = $cfg['appid'];
		$secret = $cfg['secret'];
		//echo $appid;
		//exit;
		//活动规则
      	if (!empty($rid)) {
			$reply = pdo_fetch("SELECT subscribe FROM ".tablename($this->table_reply)." WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $rid));
		}
		
		
		//参与方式  任意 与 关注 参与 
		if($serverapp!=4 && !empty($appid) && $reply['subscribe']==1){
		    //重新授权
			//setcookie("user_oauth2_openid", -10000);
			
		    if(isset($avatar)&&isset($nickname)&&isset($_COOKIE["user_oauth2_openid"])&&isset($_COOKIE["user_putonghao_openid"])){
		        $photosvoteviewurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('photosvoteview', array('rid' => $rid));
			    header("location:$photosvoteviewurl");
			    exit;
		    }else{
				
				
			    $url = $_W['siteroot'] .'app/'.$this->createMobileUrl('oauth2', array('rid' => $rid,'putonghao' => $from_user));
				$oauth2_code = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$appid."&redirect_uri=".urlencode($url)."&response_type=code&scope=snsapi_userinfo&state=0#wechat_redirect";				
				header("location:$oauth2_code");
				exit;
		    }
		}
		
		
	
		//服务号直接判读是否可以直接显示活动页
		if(isset($avatar) && isset($nickname) && isset($_COOKIE["user_oauth2_openid"])){
		    
			
			$photosvoteviewurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('photosvoteview', array('rid' => $rid));
			
			header("location:$photosvoteviewurl");
			exit;
			
			
		}else{
			//echo $from_user;
			//exit;
			
			if(!empty($from_user)) {
				//取得openid后查询是否为高级号
				
				if ($serverapp==4) {//高级号查询是否关注
			   	 $profile = pdo_fetch("SELECT follow FROM ".tablename('mc_mapping_fans')." WHERE uniacid = :uniacid and openid = :from_user", array(':uniacid' => $uniacid,':from_user' => $from_user));
				
					if($profile['follow']==1){//已关注直接获取信息
				   		//$access_token = account_weixin_token($_W['account']);
						load()->classs('weixin.account');
						
						//$accObj= WeixinAccount::create($uniacid);						
						//$access_token = $accObj->fetch_token();
						
						$access_token = WeAccount::token();
						
				    	$oauth2_url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=".$access_token."&openid=".$from_user."&lang=zh_CN";				
				  	    $content = ihttp_get($oauth2_url);
				   	    $info = @json_decode($content['content'], true);
				    	
						
						if(empty($info) || !is_array($info) || empty($info['openid'])  || empty($info['nickname']) ) {
				    		echo '<h1>获取微信公众号授权失败[无法取得info], 请稍后重试！ 公众平台返回原始数据为: <br />' . $content['meta'].'<h1>';
				    		exit;
				    	}else{
							
					  		$avatar = $info['headimgurl'];
			           		$nickname = $info['nickname'];
							//设置cookie信息
							setcookie("user_oauth2_avatar", $avatar, time()+3600*24*7);
							setcookie("user_oauth2_nickname", $nickname, time()+3600*24*7);
							setcookie("user_oauth2_openid", $from_user, time()+3600*24*7);
							
							
							$photosvoteviewurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('photosvoteview', array('rid' => $rid));
							header("location:$photosvoteviewurl");
							exit;
						}		            
					}else{//非关注直接跳转授权页
				  		$appid = $_W['account']['key'];
						$url = $_W['siteroot'] .'app/'.$this->createMobileUrl('oauth2', array('rid' => $rid));
				    	$oauth2_code = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$appid."&redirect_uri=".urlencode($url)."&response_type=code&scope=snsapi_userinfo&state=0#wechat_redirect";				
				    	header("location:$oauth2_code");
						exit;
					}	
				}else{//普通号直接跳转授权页
				
			    	if(!empty($appid)){//有借用跳转授权页没有则跳转普通注册页
				    	$url = $_W['siteroot'] .'app/'.$this->createMobileUrl('oauth2', array('rid' => $rid,'putonghao' => $from_user));
				    	$oauth2_code = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$appid."&redirect_uri=".urlencode($url)."&response_type=code&scope=snsapi_userinfo&state=0#wechat_redirect";				
				    	header("location:$oauth2_code");
						exit;
					}else{
				    	$reguser = $_W['siteroot'] .'app/'.$this->createMobileUrl('reguser', array('rid' => $rid));
				    	header("location:$reguser");
						exit;
					}
				}			
			}else{
		    	//取不到openid 直接跳转授权页
				if(!empty($appid)){//有借用跳转授权页没有则跳转普通
					$url = $_W['siteroot'] .'app/'.$this->createMobileUrl('oauth2', array('rid' => $rid));
					$oauth2_code = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$appid."&redirect_uri=".urlencode($url)."&response_type=code&scope=snsapi_userinfo&state=0#wechat_redirect";				
					header("location:$oauth2_code");
					exit;
				}else{
					$reguser = $_W['siteroot'] .'app/'.$this->createMobileUrl('reguser', array('rid' => $rid));
					header("location:$reguser");
					exit;
				}
			}
        }		
	}
	public function doMobileStop() {
		global $_GPC,$_W;
		$uniacid = $_W['uniacid'];//当前公众号ID		
		$rid = $_GPC['rid'];
		$oauthuser = $this->FM_checkoauth();
			$from_user = $oauthuser['from_user'];
			$avatar = $oauthuser['avatar'];
			$nickname = $oauthuser['nickname'];
			$follow = $oauthuser['follow'];
		
		$status = $_GPC['status'];
	
		$from_user = !empty($_GPC['from_user']) ? $_GPC['from_user'] : $from_user ;
		
        //活动规则
      	if (!empty($rid)) {
			$reply = pdo_fetch("SELECT * FROM ".tablename($this->table_reply)." WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $rid));$bgarr = iunserializer($reply['bgarr']);			
			 if (!isset($_COOKIE["user_yuedu"])) {
				 //pdo_update($this->table_users, array('hits' => $user['hits']+1,), array('rid' => $rid, 'from_user' => $tfrom_user));
				 pdo_update($this->table_reply, array('hits' => $reply['hits'] + 1), array('rid' => $rid));
				 setcookie("user_yuedu", $from_user, time()+3600*24);
				 
			}
			
			
			$number_num_day = $reply['number_num_day'];
			$picture = $reply['picture'];			
			$sharephoto = toimage($reply['sharephoto']);			
			$bgcolor = $reply['bgcolor'];
			$share_shownum = $reply['share_shownum'];	
			
			if (substr($picture,0,6)=='images'){
			   $picture = $_W['attachurl'] . $picture;
			}else{
			   $picture = $_W['siteroot'] . $picture;
			}			
			
			
 		}
		if ($status == '-1') {
			$title = $reply['title'] . ' 即将开始哦 - ';
			$stopbg = toimage($reply['nostart']);
		}elseif ($status == '0') {
			$title = $reply['title'] . ' 暂停中哦 - ';
			$stopbg = toimage($reply['stopping']);
		}elseif ($status == '1') {
			$title = $reply['title'] . ' 已经停止了，期待下一次吧！';
			$stopbg = toimage($reply['end']);
		}
		
		$user_agent = $_SERVER['HTTP_USER_AGENT'];
		if (strpos($user_agent, 'MicroMessenger') === false) {
			echo "本页面仅支持微信访问!非微信浏览器禁止浏览!";
			//include $this->template('stop');
		} else { 
			include $this->template('stop');
		}
	}
	
	public function doMobileStopip() {
		global $_GPC,$_W;
		$uniacid = $_W['uniacid'];//当前公众号ID		
		$rid = $_GPC['rid'];
		$oauthuser = $this->FM_checkoauth();
			$from_user = $oauthuser['from_user'];
			$avatar = $oauthuser['avatar'];
			$nickname = $oauthuser['nickname'];
			$follow = $oauthuser['follow'];
		
		$status = $_GPC['status'];
		
		//$this->checkoauth2($rid,$from_user);//查询是否有cookie信息
        //活动规则
      	if (!empty($rid)) {
			$reply = pdo_fetch("SELECT * FROM ".tablename($this->table_reply)." WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $rid));$bgarr = iunserializer($reply['bgarr']);			
			 if (!isset($_COOKIE["user_yuedu"])) {
				 //pdo_update($this->table_users, array('hits' => $user['hits']+1,), array('rid' => $rid, 'from_user' => $tfrom_user));
				 pdo_update($this->table_reply, array('hits' => $reply['hits'] + 1), array('rid' => $rid));
				 setcookie("user_yuedu", $from_user, time()+3600*24);
				 
			}
			$number_num_day = $reply['number_num_day'];
			$picture = $reply['picture'];			
			$sharephoto = toimage($reply['sharephoto']);			
			$bgcolor = $reply['bgcolor'];
			$share_shownum = $reply['share_shownum'];	
			
			if (substr($picture,0,6)=='images'){
			   $picture = $_W['attachurl'] . $picture;
			}else{
			   $picture = $_W['siteroot'] . $picture;
			}			
			
			
 		}
		
		$reply['sharetitle']= $this->get_share($uniacid,$rid,$from_user,$reply['sharetitle']);
		$reply['sharecontent']= $this->get_share($uniacid,$rid,$from_user,$reply['sharecontent']);
		$myavatar = $avatar;
		$mynickname = $nickname;
		$title = $reply['title'];
		//$_share['link'] = $_W['siteroot'] .'app/'.$this->createMobileUrl('shareuserview', array('rid' => $rid,'fromuser' => $from_user));//分享URL
		 $_share['title'] = $reply['sharetitle'];
		$_share['content'] =  $reply['sharecontent'];
		$_share['imgUrl'] = toimage($reply['sharephoto']);
		
		
		
		include $this->template('stopip');
		
	}
	public function stopip($rid, $uniacid, $from_user,$mineip, $do, $ipturl = '0') {					
		$iplist = pdo_fetchall('SELECT * FROM '.tablename($this->table_iplist).' WHERE uniacid= :uniacid  AND  rid= :rid order by `createtime` desc ', array(':uniacid' => $uniacid, ':rid' => $rid));
		$mineipz = sprintf("%u",ip2long($mineip));
		foreach ($iplist as $i) {
			$iparrs = iunserializer($i['iparr']);
			$ipstart = sprintf("%u",ip2long($iparrs['ipstart']));
			$ipend = sprintf("%u",ip2long($iparrs['ipend']));					
			if ($mineipz >= $ipstart && $mineipz <= $ipend) {						
				$ipdate = array(
					'rid' => $rid,
					'uniacid' => $uniacid,
					'avatar' => $avatar,
					'nickname' => $nickname,
					'from_user' => $from_user,
					'ip' => $mineip,
					'hitym' => $do,
					'createtime' => time(),
				);
				pdo_insert($this->table_iplistlog, $ipdate);
				if ($ipturl == 1) {
					$ipurl = $_W['siteroot'] . $this->createMobileUrl('stopip', array('from_user' => $from_user, 'rid' => $rid));
					header("location:$ipurl");
					exit();
				}
				break;
			}
		}
	}
	public function doMobileStopllq() {
		global $_GPC,$_W;
		$uniacid = $_W['uniacid'];//当前公众号ID		
		$rid = $_GPC['rid'];
		$oauthuser = $this->FM_checkoauth();
			$from_user = $oauthuser['from_user'];
			$avatar = $oauthuser['avatar'];
			$nickname = $oauthuser['nickname'];
			$follow = $oauthuser['follow'];
		
		$status = $_GPC['status'];
		
		//$this->checkoauth2($rid,$from_user);//查询是否有cookie信息
        //活动规则
      	if (!empty($rid)) {
			$reply = pdo_fetch("SELECT * FROM ".tablename($this->table_reply)." WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $rid));$bgarr = iunserializer($reply['bgarr']);			
			 if (!isset($_COOKIE["user_yuedu"])) {
				 //pdo_update($this->table_users, array('hits' => $user['hits']+1,), array('rid' => $rid, 'from_user' => $tfrom_user));
				 pdo_update($this->table_reply, array('hits' => $reply['hits'] + 1), array('rid' => $rid));
				 setcookie("user_yuedu", $from_user, time()+3600*24);
				 
			}
			$number_num_day = $reply['number_num_day'];
			$picture = $reply['picture'];			
			$sharephoto = toimage($reply['sharephoto']);			
			$bgcolor = $reply['bgcolor'];
			$share_shownum = $reply['share_shownum'];	
			
			if (substr($picture,0,6)=='images'){
			   $picture = $_W['attachurl'] . $picture;
			}else{
			   $picture = $_W['siteroot'] . $picture;
			}			
			
			
 		}
		
		$reply['sharetitle']= $this->get_share($uniacid,$rid,$from_user,$reply['sharetitle']);
		$reply['sharecontent']= $this->get_share($uniacid,$rid,$from_user,$reply['sharecontent']);
		$myavatar = $avatar;
		$mynickname = $nickname;
		$title = $reply['title'];
		//$_share['link'] = $_W['siteroot'] .'app/'.$this->createMobileUrl('shareuserview', array('rid' => $rid,'fromuser' => $from_user));//分享URL
		 $_share['title'] = $reply['sharetitle'];
		$_share['content'] =  $reply['sharecontent'];
		$_share['imgUrl'] = toimage($reply['sharephoto']);		
		include $this->template('stopllq');
		
	}
	private function _stopllq($turl) {
		global $_GPC,$_W;
		$user_agent = $_SERVER['HTTP_USER_AGENT'];
		if (strpos($user_agent, 'MicroMessenger') === false) {
			//$turl = 'stopllq';
			return $turl;			
		} else { 
			return $turl;;
		}
		
	}
	
	private function FM_checkoauth() {
		global $_GPC,$_W;
		$uniacid = $_W['uniacid'];//当前公众号ID
		load()->model('mc');		
		$openid = '';
		$nickname = '';
		$avatar = '';
		$follow = '';
		if (!empty($_W['member']['uid'])) {
			$member = mc_fetch(intval($_W['member']['uid']), array('avatar','nickname'));//无openid 无follow 有avatar 有nickname
			if (!empty($member)) {
				$avatar = $member['avatar'];
				$nickname = $member['nickname'];
			}
		}
		
		if (empty($avatar) || empty($nickname)) {
			$fan = mc_fansinfo($_W['openid']);//有openid 有follow 有avatar 有nickname
			if (!empty($fan)) {
				$avatar = $fan['avatar'];
				$nickname = $fan['nickname'];
				$openid = $fan['openid'];
				$follow = $fan['follow'];
			}
		}
		
		if (empty($avatar) || empty($nickname) || empty($openid) || empty($follow)) {
			$userinfo = mc_oauth_userinfo();//有openid 有follow 有avatar 有nickname
			if (!is_error($userinfo) && !empty($userinfo) && is_array($userinfo) && !empty($userinfo['avatar'])) {
				$avatar = $userinfo['avatar'];
			}
			if (!is_error($userinfo) && !empty($userinfo) && is_array($userinfo) && !empty($userinfo['nickname'])) {
				$nickname = $userinfo['nickname'];
			}
			if (!is_error($userinfo) && !empty($userinfo) && is_array($userinfo) && !empty($userinfo['openid'])) {
				$openid = $userinfo['openid'];
			}
			if (!is_error($userinfo) && !empty($userinfo) && is_array($userinfo) && !empty($userinfo['follow'])) {
				$follow = $userinfo['follow'];
			}
		}
		
		if ((empty($avatar) || empty($nickname)) && !empty($_W['member']['uid'])) {
			//$avatar = mc_require($_W['member']['uid'], array('avatar'));//无openid 无follow 有avatar 有nickname
			//$nickname = mc_require($_W['member']['uid'], array('nickname'));
		}
		
		$oauthuser = array();
		$oauthuser['avatar'] = $avatar; 
		$oauthuser['nickname'] = $nickname; 
		$oauthuser['from_user'] = $openid; 
		$oauthuser['follow'] = !empty($follow) ? $follow : $_W['fans']['follow']; 	
		
		return $oauthuser;		
	}
	
	public function doMobilePhotosvoteview() {
		//关健词触发页面显示。
		global $_GPC,$_W;
		$uniacid = $_W['uniacid'];//当前公众号ID		
		$rid = $_GPC['rid'];
		//load()->model('mc');
		//setcookie("user_oauth2_openid", -10000);
		$oauthuser = $this->FM_checkoauth();
		//print_r($oauthuser);
			$from_user = $oauthuser['from_user'];
			$avatar = $oauthuser['avatar'];
			$nickname = $oauthuser['nickname'];
			$follow = $oauthuser['follow'];
		//$from_user = $_GPC['from_user'];
		//$from_user = $_COOKIE["user_oauth2_openid"];		
		////$this->checkoauth2($rid,$from_user);//查询是否有cookie信息
		//if (empty($from_user)){
		//    $from_user = !empty($_W['fans']['from_user']) ? $_W['fans']['from_user'] : $from_user;			
		//    $from_user = $from_user;	
		//}
		//	$from_user = base64_encode(authcode($from_user, 'ENCODE'));
		
		
		
        //活动规则
      	if (!empty($rid)) {
			$reply = pdo_fetch("SELECT * FROM ".tablename($this->table_reply)." WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $rid));
			$bgarr = iunserializer($reply['bgarr']);			
			 if (!isset($_COOKIE["user_yuedu"])) {
				 //pdo_update($this->table_users, array('hits' => $user['hits']+1,), array('rid' => $rid, 'from_user' => $tfrom_user));
				 pdo_update($this->table_reply, array('hits' => $reply['hits'] + 1), array('rid' => $rid));
				 setcookie("user_yuedu", $from_user, time()+3600*24);
				 
			}
			$number_num_day = $reply['number_num_day'];
			$picture = $reply['picture'];			
			$sharephoto = toimage($reply['sharephoto']);			
			$bgcolor = $reply['bgcolor'];
			$share_shownum = $reply['share_shownum'];	
			
			if (substr($picture,0,6)=='images'){
			   $picture = $_W['attachurl'] . $picture;
			}else{
			   $picture = $_W['siteroot'] . $picture;
			}			
			
			if ($reply['status']==0) {
				$statpraisetitle = '<h1>活动暂停！请稍候再试！</h1>';
				$stopurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('stop', array('status' => '0', 'rid' => $rid));
				header("location:$stopurl");
				exit;
			}
			if (time()<$reply['start_time']) {//判断活动是否已经开始
				$statpraisetitle = '<h1>活动未开始！</h1>';
				
				$stopurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('stop', array('status' => '-1', 'rid' => $rid));
				header("location:$stopurl");
				exit;
			}elseif (time()>$reply['end_time']) {//判断活动是否已经结束
				$statpraisetitle = '<h1>活动已结束！</h1>';
				
				$stopurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('stop', array('status' => '1', 'rid' => $rid));
				header("location:$stopurl");
				exit;
			}
			if ($reply['isipv']==1) {
				$this->stopip($rid, $uniacid, $from_user,getip(), $_GPC['do'], $reply['ipturl']);				
			}
			
			
			
 		}
		if ($reply['ipannounce'] == 1) {
			$announce = pdo_fetchall("SELECT * FROM " . tablename($this->table_announce) . " WHERE uniacid= '{$_W['uniacid']}' AND rid= '{$rid}' ORDER BY id DESC");
			
		}
		//幻灯片
        $banners = pdo_fetchall("SELECT * FROM " . tablename($this->table_banners) . " WHERE enabled=1 AND uniacid= '{$_W['uniacid']}' AND rid= '{$rid}' ORDER BY displayorder ASC");
        foreach ($banners as &$banner) {
            if (substr($banner['link'], 0, 5) != 'http:') {
                $banner['link'] = "http://" . $banner['link'];
            }
        }
        unset($banner);
		//赞助商
		if ($reply['isindex'] == 1) {
			$advs = pdo_fetchall("SELECT * FROM " . tablename($this->table_advs) . " WHERE enabled=1 AND uniacid= '{$_W['uniacid']}'  AND rid= '{$rid}' ORDER BY displayorder ASC");
			foreach ($advs as &$adv) {
				if (substr($adv['link'], 0, 5) != 'http:') {
					$adv['link'] = "http://" . $adv['link'];
				}
			}
			unset($adv);
		}
		
		$pindex = max(1, intval($_GPC['page']));
		$psize = empty($reply['indextpxz']) ? 10 : $reply['indextpxz'];
		//取得用户列表
		$where = '';
		if (!empty($_GPC['keyword'])) {
				$keyword = $_GPC['keyword'];
				if (is_numeric($keyword)) 
					$where .= " AND id = '".$keyword."'";
				else 				
					$where .= " AND nickname LIKE '%{$keyword}%'";
			
		}
		$where .= " AND status = '1'";
		$userlist = pdo_fetchall('SELECT * FROM '.tablename($this->table_users).' WHERE uniacid= :uniacid and rid = :rid '.$where.' order by `id` desc LIMIT ' . ($pindex - 1) * $psize . ',' . $psize, array(':uniacid' => $uniacid,':rid' => $rid) );
		
		$total = pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_users).' WHERE uniacid= :uniacid and rid = :rid '.$where.'', array(':uniacid' => $uniacid,':rid' => $rid));
		$pager = paginationm($total, $pindex, $psize, '', array('before' => 0, 'after' => 0, 'ajaxcallback' => ''));
		 
		// $userlist = pdo_fetchall("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid ", array(':uniacid' => $uniacid));
		
		
		//查询自己是否参与活动
		if(!empty($from_user)) {
		    $mygift = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and from_user = :from_user and rid = :rid", array(':uniacid' => $uniacid,':from_user' => $from_user,':rid' => $rid));
			//此处更新一下分享量和邀请量
			if(!empty($mygift)){
			    $yql = pdo_fetchcolumn("SELECT COUNT(*) FROM ".tablename($this->table_data)." WHERE uniacid = :uniacid and fromuser = :fromuser and rid = :rid and isin >= ".$reply['opensubscribe']."", array(':uniacid' => $uniacid,':fromuser' => $from_user,':rid' => $rid));
			    $fxl = pdo_fetchcolumn("SELECT COUNT(*) FROM ".tablename($this->table_data)." WHERE uniacid = :uniacid and fromuser = :fromuser and rid = :rid", array(':uniacid' => $uniacid,':fromuser' => $from_user,':rid' => $rid));
				//$hits = $mygift['hits'] + 1;
				pdo_update($this->table_users,array('sharenum' => $fxl,'yaoqingnum' => $yql),array('id' => $mygift['id']));
			}	
		}
		//查询是否参与活动
		//if(!empty($from_user)) {
		//    $usergift = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and from_user = :from_user and rid = :rid", array(':uniacid' => $uniacid,':from_user' => $from_user,':rid' => $rid));
		   
			//$follow = pdo_fetch("SELECT follow FROM ".tablename('mc_mapping_fans')." WHERE uniacid = :uniacid and openid = :from_user", array(':uniacid' => $uniacid,':from_user' => $from_user));
			
		//}
			
		//统计
		$csrs = pdo_fetchcolumn("SELECT COUNT(*) FROM ".tablename($this->table_users)." WHERE rid= ".$rid." AND uniacid= ".$uniacid."") + $reply['xuninum'];//参赛人数
		$ljtp = pdo_fetchcolumn("SELECT COUNT(*) FROM ".tablename($this->table_log)." WHERE rid= ".$rid."") + pdo_fetchcolumn("SELECT sum(xnphotosnum) FROM ".tablename($this->table_users)." WHERE rid= ".$rid."");//累计投票
		$cyrs = $csrs + $reply['hits'] + pdo_fetchcolumn("SELECT sum(hits) FROM ".tablename($this->table_users)." WHERE rid= ".$rid."") + pdo_fetchcolumn("SELECT sum(xnhits) FROM ".tablename($this->table_users)." WHERE rid= ".$rid."");//参与人数
		
		
		//每个奖品的位置
		//虚拟人数据配置
		$now = time();
		if($now-$reply['xuninum_time']>$reply['xuninumtime']){
		    pdo_update($this->table_reply, array('xuninum_time' => $now,'xuninum' => $reply['xuninum']+mt_rand($reply['xuninuminitial'],$reply['xuninumending'])), array('rid' => $rid));
		}
		//虚拟人数据配置
		//参与活动人数
		$total = $reply['xuninum'] + pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_users).' WHERE uniacid=:uniacid and rid=:rid', array(':uniacid' => $uniacid,':rid' => $rid));
		//参与活动人数
		//查询分享标题以及内容变量
		$reply['sharetitle']= $this->get_share($uniacid,$rid,$from_user,$reply['sharetitle']);
		$reply['sharecontent']= $this->get_share($uniacid,$rid,$from_user,$reply['sharecontent']);
		//整理数据进行页面显示
		$myavatar = $avatar;
		$mynickname = $nickname;
		$shareurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('shareuserview', array('rid' => $rid,'fromuser' => $from_user));//分享URL
		$regurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('reg', array('rid' => $rid));//关注或借用直接注册页
		$lingjiangurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('lingjiang', array('rid' => $rid));//领奖URL
		$mygifturl = $_W['siteroot'] .'app/'.$this->createMobileUrl('photosvoteview', array('rid' => $rid));//我的页面
		$shouquan = base64_encode($_SERVER ['HTTP_HOST'].'anquan_ma_photosvote');
		$title = $reply['title'] . ' ';
		
		
		$_share['link'] = $_W['siteroot'] .'app/'.$this->createMobileUrl('shareuserview', array('rid' => $rid,'fromuser' => $from_user));//分享URL
		 $_share['title'] = $reply['sharetitle'];
		$_share['content'] =  $reply['sharecontent'];
		$_share['imgUrl'] = toimage($reply['sharephoto']);
		
		$toye = $this->_stopllq('photosvote');
		include $this->template($toye);
		

	}
		
	public function doMobileTuser() {
		//关健词触发页面显示。
		global $_GPC,$_W;
		$uniacid = $_W['uniacid'];//当前公众号ID		
		$rid = $_GPC['rid'];
		load()->func('tpl');		
		$oauthuser = $this->FM_checkoauth();
			$from_user = $oauthuser['from_user'];
			$avatar = $oauthuser['avatar'];
			$nickname = $oauthuser['nickname'];
			$follow = $oauthuser['follow'];
		$tfrom_user = $_GPC['tfrom_user'];
		
		//$from_user = $_GPC['from_user'];
		//$from_user = $_COOKIE["user_oauth2_openid"];
		
		//$from_user_putonghao = $_COOKIE["user_putonghao_openid"];
		
		$fromuser = $_GPC["fromuser"];//分享人
		
		//$from_user = base64_encode(authcode($_COOKIE["user_oauth2_openid"], 'ENCODE'));
		////$this->checkoauth2($rid,$from_user);//查询是否有cookie信息
		//if (empty($from_user)){
		//    $from_user = !empty($_W['fans']['from_user']) ? $_W['fans']['from_user'] : $from_user;			
		//    $from_user = $from_user;	
		//}
		//$from_user = base64_encode(authcode($from_user, 'ENCODE'));
		if (empty($fromuser)){
			$fromuser = $_COOKIE["user_fromuser_openid"];
		}		
		//echo $fromuser;
		//exit;
		
        //活动规则
      	if (!empty($rid)) {
			$reply = pdo_fetch("SELECT * FROM ".tablename($this->table_reply)." WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $rid));
			$bgarr = iunserializer($reply['bgarr']);			
			$number_num_day = $reply['number_num_day'];
			$picture = $reply['picture'];			
			$bgcolor = $reply['bgcolor'];
			$sharephoto = toimage($reply['sharephoto']);
			$share_shownum = $reply['share_shownum'];	
			
			if (substr($picture,0,6)=='images'){
			   $picture = $_W['attachurl'] . $picture;
			}else{
			   $picture = $_W['siteroot'] . $picture;
			}			
			
			if ($reply['status']==0) {
				$statpraisetitle = '<h1>活动暂停！请稍候再试！</h1>';
				$stopurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('stop', array('status' => '0', 'rid' => $rid));
				header("location:$stopurl");
				exit;
			}
			if (time()<$reply['start_time']) {//判断活动是否已经开始
				$statpraisetitle = '<h1>活动未开始！</h1>';
				
				$stopurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('stop', array('status' => '-1', 'rid' => $rid));
				header("location:$stopurl");
				exit;
			}elseif (time()>$reply['end_time']) {//判断活动是否已经结束
				$statpraisetitle = '<h1>活动已结束！</h1>';
				
				$stopurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('stop', array('status' => '1', 'rid' => $rid));
				header("location:$stopurl");
				exit;
			}
			
			
			if ($reply['isipv']==1) {
				$this->stopip($rid, $uniacid, $from_user,getip(), $_GPC['do'], $reply['ipturl']);				
			}
 		}
 		
		
		if ($reply['ipannounce'] == 1) {
			$announce = pdo_fetchall("SELECT * FROM " . tablename($this->table_announce) . " WHERE uniacid= '{$_W['uniacid']}' AND rid= '{$rid}' ORDER BY id DESC");
			
		}
		//赞助商
		if ($reply['isvotexq'] == 1) {
			$advs = pdo_fetchall("SELECT * FROM " . tablename($this->table_advs) . " WHERE enabled=1 AND uniacid= '{$_W['uniacid']}'  AND rid= '{$rid}' ORDER BY displayorder ASC");
			foreach ($advs as &$adv) {
				if (substr($adv['link'], 0, 5) != 'http:') {
					$adv['link'] = "http://" . $adv['link'];
				}
			}
			unset($adv);
		}
		
		//查询自己是否参与活动
		if(!empty($from_user)) {
		    $mygift = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and from_user = :from_user and rid = :rid", array(':uniacid' => $uniacid,':from_user' => $from_user,':rid' => $rid));
		   
			
		}
		
		
		//查询是否参与活动
		if(!empty($tfrom_user)) {
		    $user = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and from_user = :from_user and rid = :rid", array(':uniacid' => $uniacid,':from_user' => $tfrom_user,':rid' => $rid));
		    if (!empty($user)) {	
//setcookie("user_yuedu", -10000);			
				if (!isset($_COOKIE["user_yuedu"])) {
					 pdo_update($this->table_users, array('hits' => $user['hits']+1,), array('rid' => $rid, 'from_user' => $tfrom_user));
					 setcookie("user_yuedu", $from_user, time()+3600*24);
				}
		    }else{
				$url = $_W['siteroot'] .'app/'.$this->createMobileUrl('photosvoteview', array('rid' => $rid));
				header("location:$url");
				exit;
			}
			
		}
		$sharenum = pdo_fetchcolumn("SELECT COUNT(*) FROM ".tablename($this->table_data)." WHERE uniacid = :uniacid and tfrom_user = :tfrom_user and rid = :rid", array(':uniacid' => $uniacid,':tfrom_user' => $tfrom_user,':rid' => $rid)) + pdo_fetchcolumn("SELECT COUNT(*) FROM ".tablename($this->table_data)." WHERE uniacid = :uniacid and fromuser = :fromuser and rid = :rid", array(':uniacid' => $uniacid,':fromuser' => $tfrom_user,':rid' => $rid));
		if ($user['picarr']) {
			$picarr = iunserializer($user['picarr']);
		}else {
			$pacarr = array();
			for ($i = 1; $i <= $reply['tpxz']; $i++) {
				$n = $i - 1;
				$picarr[$n] .= $user['picarr_'.$i];				
			}
		}
		
		
		$follow = $oauthuser['follow'];
		
		
		$starttime=mktime(0,0,0);//当天：00：00：00
		$endtime = mktime(23,59,59);//当天：23：59：59
		$times = '';
		$times .= ' AND createtime >=' .$starttime;
		$times .= ' AND createtime <=' .$endtime;
		
		
		
		$uservote = pdo_fetch("SELECT * FROM ".tablename($this->table_log)." WHERE uniacid = :uniacid AND from_user = :from_user  AND tfrom_user = :tfrom_user AND rid = :rid", array(':uniacid' => $uniacid,':from_user' => $from_user,':tfrom_user' => $tfrom_user,':rid' => $rid));
		$uallonetp = pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_log).' WHERE uniacid= :uniacid AND from_user = :from_user AND tfrom_user = :tfrom_user AND rid = :rid  ORDER BY createtime DESC', array(':uniacid' => $uniacid, ':from_user' => $from_user, ':tfrom_user' => $tfrom_user,':rid' => $rid));
		
		$udayonetp = pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_log).' WHERE uniacid= :uniacid AND from_user = :from_user AND tfrom_user = :tfrom_user AND rid = :rid '.$times.' ORDER BY createtime DESC', array(':uniacid' => $uniacid, ':from_user' => $from_user, ':tfrom_user' => $tfrom_user,':rid' => $rid));
		if ($reply['isbbsreply'] == 1) {//开启评论
		
			//评论
			$pindex = max(1, intval($_GPC['page']));
			$psize = 10;
			//取得用户列表
			$where = '';
			if (!empty($_GPC['keyword'])) {
					$keyword = $_GPC['keyword'];
					if (is_numeric($keyword)) 
						$where .= " AND id = '".$keyword."'";
					else 				
						$where .= " AND nickname LIKE '%{$keyword}%'";
				
			}
			$bbsreply = pdo_fetchall("SELECT * FROM ".tablename($this->table_bbsreply)." WHERE uniacid = :uniacid AND tfrom_user = :tfrom_user AND rid = :rid ".$where." order by `id` desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize,  array(':uniacid' => $uniacid,':tfrom_user' => $tfrom_user,':rid' => $rid));
			
			$total = pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_bbsreply).' WHERE uniacid= :uniacid AND tfrom_user = :tfrom_user AND rid = :rid '.$where.'', array(':uniacid' => $uniacid, ':tfrom_user' => $tfrom_user,':rid' => $rid));
			$pager = paginationm($total, $pindex, $psize, '', array('before' => 0, 'after' => 0, 'ajaxcallback' => ''));
			$btotal = pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_bbsreply).' WHERE uniacid= :uniacid AND tfrom_user = :tfrom_user AND rid = :rid ', array(':uniacid' => $uniacid, ':tfrom_user' => $tfrom_user,':rid' => $rid));
						
			
		}		
		
		$votetime = $reply['votetime']*3600*24;
		$isvtime = TIMESTAMP - $user['createtime'];
		$ttime = $votetime - $isvtime;
		
		if ($ttime > 0) {
			$totaltime = $ttime;
		} else {
			$totaltime = 0;
		}
		
		//每个奖品的位置
		//虚拟人数据配置
		$now = time();
		if($now-$reply['xuninum_time']>$reply['xuninumtime']){
		    pdo_update($this->table_reply, array('xuninum_time' => $now,'xuninum' => $reply['xuninum']+mt_rand($reply['xuninuminitial'],$reply['xuninumending'])), array('rid' => $rid));
		}
		//虚拟人数据配置
		//参与活动人数
		$totals = $reply['xuninum'] + pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_users).' WHERE uniacid=:uniacid and rid=:rid', array(':uniacid' => $uniacid,':rid' => $rid));
		//参与活动人数
		//查询分享标题以及内容变量
		$reply['sharetitle']= $this->get_share($uniacid,$rid,$from_user,$reply['sharetitle']);
		$reply['sharecontent']= $this->get_share($uniacid,$rid,$from_user,$reply['sharecontent']);
		//整理数据进行页面显示
		$myavatar = $avatar;
		$mynickname = $nickname;
		$shareurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('shareuserview', array('rid' => $rid,'duli'=> '1', 'fromuser' => $from_user, 'tfrom_user' => $tfrom_user));//分享URL
		$regurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('reg', array('rid' => $rid));//关注或借用直接注册页
		$lingjiangurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('lingjiang', array('rid' => $rid));//领奖URL
		$mygifturl = $_W['siteroot'] .'app/'.$this->createMobileUrl('photosvoteview', array('rid' => $rid));//我的页面
		$shouquan = base64_encode($_SERVER ['HTTP_HOST'].'anquan_ma_photosvote');
		//$title = $user['nickname'] . ' 的投票详情！';
		$title = $user['nickname'] . '正在参加'. $reply['title'] .'，快来为'.$user['nickname'].'投票及拉票吧！';
		
		$sharetitle = $user['nickname'] . '正在参加'. $reply['title'] .'，快来为'.$user['nickname'].'投票及拉票吧！';
		$sharecontent = $user['nickname'] . '正在参加'. $reply['title'] .'，快来为'.$user['nickname'].'投票及拉票吧！';
		$picture =  toimage($reply['sharephoto']);
		
		
		
		$_share['link'] =$_W['siteroot'] .'app/'.$this->createMobileUrl('shareuserview', array('rid' => $rid,'duli'=> '1', 'fromuser' => $from_user, 'tfrom_user' => $tfrom_user));//分享URL
		 $_share['title'] = $user['nickname'] . '正在参加'. $reply['title'] .'，快来为'.$user['nickname'].'投票及拉票吧！';
		$_share['content'] = $user['nickname'] . '正在参加'. $reply['title'] .'，快来为'.$user['nickname'].'投票及拉票吧！';
		//$_share['imgUrl'] = !empty($user['photo']) ? toimage($user['photo']) : toimage($user['avatar']);
		$_share['imgUrl'] = toimage($reply['sharephoto']);
		
		
		
		$toye = $this->_stopllq('tuser');
		include $this->template('tuser');

	}
	
	public function doMobileSubscribe() {
		//关健词触发页面显示。
		global $_GPC,$_W;
		if($_W['isajax']) {
			$uniacid = $_W['uniacid'];//当前公众号ID	
			
			$rid = $_GPC['rid'];
			$tfrom = $_GPC['tfrom'];
			$vote = $_GPC['vote'];			
			$tid = $_GPC['tid'];
			
			if (!empty($tid)) {
				$tuser = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and id = :id and rid = :rid", array(':uniacid' => $uniacid,':id' => $tid,':rid' => $rid));
				$tfrom_user = $tuser['from_user'];
			}else {
				$tfrom_user = $_GPC['tfrom_user'];
			}
			
						
			//$from_user = $_GPC['from_user'];
			//$from_user = $_COOKIE["user_oauth2_openid"];
			//$from_user_putonghao = $_COOKIE["user_putonghao_openid"];
			$oauthuser = $this->FM_checkoauth();
			$from_user = $oauthuser['from_user'];
			$avatar = $oauthuser['avatar'];
			$nickname = $oauthuser['nickname'];
			$follow = $oauthuser['follow'];
			$tfrom_user = $_GPC['tfrom_user'];
			
			$fromuser = $_GPC["fromuser"];//分享人
			
			//$from_user = base64_encode(authcode($_COOKIE["user_oauth2_openid"], 'ENCODE'));
			//if (empty($from_user)){
			//	$from_user = $from_user;
			//	$from_user = $from_user;	
			//}
			if (empty($fromuser)){
				$fromuser = $_COOKIE["user_fromuser_openid"];
			}		
			//echo $fromuser;
			//exit;
			$this->checkoauth2($rid,$from_user);//查询是否有cookie信息
			//活动规则
			if (!empty($rid)) {
				$reply = pdo_fetch("SELECT * FROM ".tablename($this->table_reply)." WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $rid));$bgarr = iunserializer($reply['bgarr']);
			}
			
			
	
			include $this->template('subscribe');
			exit();
		}
	}
	
	public function doMobileSubscribeshare() {
		//关健词触发页面显示。
		global $_GPC,$_W;
		
			$uniacid = $_W['uniacid'];//当前公众号ID	
			$oauthuser = $this->FM_checkoauth();
			$from_user = $oauthuser['from_user'];
			$avatar = $oauthuser['avatar'];
			$nickname = $oauthuser['nickname'];
			$follow = $oauthuser['follow'];
			$tfrom_user = $_GPC['tfrom_user'];
			$rid = $_GPC['rid'];
			$tfrom = $_GPC['tfrom'];
			$vote = $_GPC['vote'];			
			$tid = $_GPC['tid'];
			
			if (!empty($tid)) {
				$tuser = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and id = :id and rid = :rid", array(':uniacid' => $uniacid,':id' => $tid,':rid' => $rid));
				$tfrom_user = $tuser['from_user'];
			}else {
				$tfrom_user = $_GPC['tfrom_user'];
			}
			
						
			//$from_user = $_GPC['from_user'];
			////$from_user = $_COOKIE["user_oauth2_openid"];
			//$from_user_putonghao = $_COOKIE["user_putonghao_openid"];
			
			$fromuser = $_GPC["fromuser"];//分享人
			
			//$from_user = base64_encode(authcode($_COOKIE["user_oauth2_openid"], 'ENCODE'));
			//if (empty($from_user)){
			//	$from_user = $from_user;
			//	$from_user = $from_user;	
			//}
			if (empty($fromuser)){
				$fromuser = $_COOKIE["user_fromuser_openid"];
			}		
			//echo $fromuser;
			//exit;
			//$this->checkoauth2($rid,$from_user);//查询是否有cookie信息
			//活动规则
			if (!empty($rid)) {
				$reply = pdo_fetch("SELECT * FROM ".tablename($this->table_reply)." WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $rid));
			}
			
			
	
			include $this->template('subscribeshare');
			
	}
	
	public function doMobileTvote() {
		//关健词触发页面显示。
		global $_GPC,$_W;
		if($_W['isajax']) {
			$uniacid = $_W['uniacid'];//当前公众号ID	
			$oauthuser = $this->FM_checkoauth();
			$from_user = $oauthuser['from_user'];
			$avatar = $oauthuser['avatar'];
			$nickname = $oauthuser['nickname'];
			$follow = $oauthuser['follow'];
			//$tfrom_user = $_GPC['tfrom_user'];
			//$from_user = $oauthuser['from_user'];	
			
			$rid = $_GPC['rid'];
			$tfrom = $_GPC['tfrom'];
			$vote = $_GPC['vote'];			
			$tid = $_GPC['tid'];
			
			if (!empty($tid)) {
				$tuser = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and id = :id and rid = :rid", array(':uniacid' => $uniacid,':id' => $tid,':rid' => $rid));
				$tfrom_user = $tuser['from_user'];
			}else {
				$tfrom_user = $_GPC['tfrom_user'];
			}
			
			// $user = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and id = :id and rid = :rid", array(':uniacid' => $uniacid,':id' => $tid,':rid' => $rid));
			//$tfrom_user = $user['from_user'];
			
			//$from_user = $_GPC['from_user'];
			//$from_user = $_COOKIE["user_oauth2_openid"];
			//$from_user_putonghao = $_COOKIE["user_putonghao_openid"];
			
			$fromuser = $_GPC["fromuser"];//分享人
			
			//$from_user = base64_encode(authcode($_COOKIE["user_oauth2_openid"], 'ENCODE'));
			//if (empty($from_user)){
			//	$from_user = $from_user;
			//	$from_user = $from_user;	
			//}
			if (empty($fromuser)){
				$fromuser = $_COOKIE["user_fromuser_openid"];
			}		
			//echo $fromuser;
			//exit;
			////$this->checkoauth2($rid,$from_user);//查询是否有cookie信息
			//活动规则
			if (!empty($rid)) {
				$reply = pdo_fetch("SELECT * FROM ".tablename($this->table_reply)." WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $rid));$bgarr = iunserializer($reply['bgarr']);
			}
			
			
			include $this->template('tvote');
			exit();
		}
	}
	
	public function doMobileTvotestart() {
		//关健词触发页面显示。
		global $_GPC,$_W;
		
		
		
			$uniacid = $_W['uniacid'];//当前公众号ID		
			$rid = $_GPC['rid'];
			$tid = $_GPC['tid'];
			$oauthuser = $this->FM_checkoauth();
			$from_user = $oauthuser['from_user'];
			$avatar = $oauthuser['avatar'];
			$nickname = $oauthuser['nickname'];
			$follow = $oauthuser['follow'];
			
			load()->func('tpl');
			
			if (!empty($tid)) {
				$tuser = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and id = :id and rid = :rid", array(':uniacid' => $uniacid,':id' => $tid,':rid' => $rid));
				$tfrom_user = $tuser['from_user'];
			}else {
				$tfrom_user = $_GPC['tfrom_user'];
			}
			
			$oauth_tfrom_user = $_COOKIE["user_tfrom_user_openid"];
			
			//$from_user = $_GPC['from_user'];
			//$from_user = $_GPC['from_user'];
			//$from_user = $_COOKIE["user_oauth2_openid"];
			
			
			//$from_user_putonghao = $_COOKIE["user_putonghao_openid"];
			
			$fromuser = $_GPC["fromuser"];//分享人
			
			//$from_user = base64_encode(authcode($_COOKIE["user_oauth2_openid"], 'ENCODE'));
			//if (empty($from_user)){
			//	$from_user = !empty($_W['fans']['from_user']) ? $_W['fans']['from_user'] : $from_user;				
			//}
		//	if (empty($from_user)){				
		//		$from_user = $from_user;	
		//	}
			if (empty($fromuser)){
				$fromuser = $_COOKIE["user_fromuser_openid"];
			}		
			//echo $fromuser;
			//exit;
			//$this->checkoauth2($rid,$from_user);//查询是否有cookie信息
			//活动规则
			if (!empty($rid)) {
				$reply = pdo_fetch("SELECT * FROM ".tablename($this->table_reply)." WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $rid));$bgarr = iunserializer($reply['bgarr']);
			}
			
						
			//查询自己是否参与活动
			if(!empty($from_user)) {
				$mygift = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and from_user = :from_user and rid = :rid", array(':uniacid' => $uniacid,':from_user' => $from_user,':rid' => $rid));
			}
			
			
			//查询是否参与活动
			if(!empty($tfrom_user)) {
				$user = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and from_user = :from_user and rid = :rid", array(':uniacid' => $uniacid,':from_user' => $tfrom_user,':rid' => $rid));
				if (!empty($user)) {
					//pdo_update($this->table_users, array('hits' => $user['hits']+1), array('rid' => $rid, 'from_user' => $tfrom_user));
				}else{
					$url = $_W['siteroot'] .'app/'.$this->createMobileUrl('photosvoteview', array('rid' => $rid));
					//header("location:$url");
					$fmdata = array(
							"success" => 3,
							"linkurl" => $url,
							"msg" => '！',
						);
						echo json_encode($fmdata);
						exit();	
					
					
				}
				
			}
			
			//$follow = pdo_fetch("SELECT follow FROM ".tablename('mc_mapping_fans')." WHERE uniacid = :uniacid and openid = :from_user", array(':uniacid' => $uniacid,':from_user' => $from_user));
			
			$uservote = pdo_fetch("SELECT * FROM ".tablename($this->table_log)." WHERE uniacid = :uniacid AND from_user = :from_user  AND tfrom_user = :tfrom_user AND rid = :rid", array(':uniacid' => $uniacid,':from_user' => $from_user,':tfrom_user' => $tfrom_user,':rid' => $rid));
			$uallonetp = pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_log).' WHERE uniacid= :uniacid AND from_user = :from_user AND tfrom_user = :tfrom_user AND rid = :rid ORDER BY createtime DESC', array(':uniacid' => $uniacid, ':from_user' => $from_user, ':tfrom_user' => $tfrom_user,':rid' => $rid));
			$udayonetp = pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_log).' WHERE uniacid= :uniacid AND from_user = :from_user AND tfrom_user = :tfrom_user AND rid = :rid '.$times.' ORDER BY createtime DESC', array(':uniacid' => $uniacid, ':from_user' => $from_user, ':tfrom_user' => $tfrom_user,':rid' => $rid));
			
			$mineip = getip();			
			$iplist = pdo_fetchall('SELECT * FROM '.tablename($this->table_iplist).' WHERE uniacid= :uniacid  AND  rid= :rid order by `createtime` desc ', array(':uniacid' => $uniacid, ':rid' => $rid));
			$mineipz = sprintf("%u",ip2long($mineip));
			foreach ($iplist as $i) {
				$iparrs = iunserializer($i['iparr']);
				$ipstart = sprintf("%u",ip2long($iparrs['ipstart']));
				$ipend = sprintf("%u",ip2long($iparrs['ipend']));					
				if ($mineipz >= $ipstart && $mineipz <= $ipend) {						
					$ipdate = array(
						'rid' => $rid,
						'uniacid' => $uniacid,
						'avatar' => $avatar,
						'nickname' => $nickname,
						'from_user' => $from_user,
						'ip' => $mineip,
						'hitym' => 'tvote',
						'createtime' => time(),
					);
					pdo_insert($this->table_iplistlog, $ipdate);
					if ($reply['ipstopvote'] == 1) {
						$ipurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('stopip', array('from_user' => $from_user, 'rid' => $rid));
						
						$fmdata = array(
							"success" => 3,
							"linkurl" => $ipurl,
							"msg" => '你存在刷票的嫌疑或者您的网络不稳定，请重新进入！',
						);
						echo json_encode($fmdata);
						exit();	
					}
					break;
				}
			}
			
			
			
			if($_GPC['vote'] == '1') {
				$starttime=mktime(0,0,0);//当天：00：00：00
				$endtime = mktime(23,59,59);//当天：23：59：59
				$times = '';
				$times .= ' AND createtime >=' .$starttime;
				$times .= ' AND createtime <=' .$endtime;
				$now = time();
				$daytpxz = pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_log).' WHERE uniacid= :uniacid AND from_user = :from_user AND rid = :rid '.$times.' ORDER BY createtime DESC', array(':uniacid' => $uniacid, ':from_user' => $from_user,':rid' => $rid));
				
				$votetime = $reply['votetime']*3600*24;
				$isvtime = $now - $user['createtime'];
				if($isvtime >= $votetime) {
					$fmdata = array(
						"success" => -1,
						"msg" => empty($reply['ttipvote']) ? '你的投票时间已经结束' : $reply['ttipvote'],
					);
					echo json_encode($fmdata);
					exit();	
				}
				if($now <= $reply['tstart_time'] || $now >= $reply['tend_time']) {
					
					if ($now <= $reply['tstart_time']) {
						$fmdata = array(
							"success" => -1,
							"msg" => $reply['ttipstart'],
						);
						echo json_encode($fmdata);
						exit();	
					}
					if ($now >= $reply['tend_time']) {
						$fmdata = array(
							"success" => -1,
							"msg" => $reply['ttipend'],
						);
						echo json_encode($fmdata);
						exit();	
					}
				}
				
				
				if ($_GPC['vfrom'] == 'photosvoteview') {
					$turl = $this->createMobileUrl('photosvoteview', array('rid' => $rid));
				} elseif ($_GPC['vfrom'] == 'tuserphotos') {
					$turl = $this->createMobileUrl('tuserphotos', array('rid' => $rid));
				} else {
					$turl = referer();
				}
					
				if ($reply['subscribe'] == 1) {
					
					if ($follow == 1) {
						
						if ($daytpxz >= $reply['daytpxz']) {
							$msg = '您当前最多可以投票'.$reply['daytpxz'].'个参赛选手，您当天的次数已经投完，请明天再来';
							//message($msg,$turl,'error');
							$fmdata = array(
								"success" => -1,
								"msg" => $msg,
							);
							echo json_encode($fmdata);
							exit();	
							
							
						}	else {
							if ($tfrom_user == $from_user) {
								//message(,$turl,'error');
								$msg = '您不能为自己投票';
								$fmdata = array(
									"success" => -1,
									"msg" => $msg,
								);
								echo json_encode($fmdata);
								exit();	
							}else {
								$dayonetp = pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_log).' WHERE uniacid= :uniacid AND from_user = :from_user AND tfrom_user = :tfrom_user AND rid = :rid '.$times.' ORDER BY createtime DESC', array(':uniacid' => $uniacid, ':from_user' => $from_user, ':tfrom_user' => $tfrom_user,':rid' => $rid));
								
								$allonetp = pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_log).' WHERE uniacid= :uniacid AND from_user = :from_user AND tfrom_user = :tfrom_user AND rid = :rid ORDER BY createtime DESC', array(':uniacid' => $uniacid, ':from_user' => $from_user, ':tfrom_user' => $tfrom_user,':rid' => $rid));
								if ($allonetp >= $reply['allonetp']) {
									$msg = '您总共可以给他投票'.$reply['allonetp'].'次，您已经投完！';
									//message($msg,$turl,'error');
									$fmdata = array(
										"success" => -1,
										"msg" => $msg,
									);
									echo json_encode($fmdata);
									exit();	
									
								} else {							
									if ($dayonetp >= $reply['dayonetp']) {
										$msg = '您当天最多可以给他投票'.$reply['dayonetp'].'次，您已经投完，请明天再来';
										//message($msg,$turl,'error');
										$fmdata = array(
											"success" => -1,
											"msg" => $msg,
										);
										echo json_encode($fmdata);
										exit();	
										
										
										//exit;
									}else {
										
										if($reply['iscode'] == 1) {
											$code = $_GPC['code'];
											if (empty($code)) {
												$fmdata = array(
													"success" => -1,
													"msg" => '请输入验证码！',
												);
												echo json_encode($fmdata);
												exit();	
											}
											$hash = md5($code . $_W['config']['setting']['authkey']);
											if($_GPC['__code'] != $hash) {					
												$fmdata = array(
													"success" => -1,
													"msg" => '你输入的验证码不正确, 请重新输入.',
												);
												echo json_encode($fmdata);
												exit();	
												//message('你输入的验证码不正确, 请重新输入.');
											}
										}
										
										$votedate = array(
											'uniacid' => $uniacid,
											'rid' => $rid,
											'tptype' => '1',
											'avatar' => $avatar,
											'nickname' => $nickname,
											'from_user' => $from_user,
											'afrom_user' => $fromuser,
											'tfrom_user' => $tfrom_user,
											'ip' => getip(),
											'createtime' => time(),
											
										);				
										pdo_insert($this->table_log, $votedate);
										pdo_update($this->table_users, array('photosnum'=> $user['photosnum']+1), array('rid' => $rid, 'from_user' => $tfrom_user,'uniacid' => $uniacid));
										
										
										$tuservote = pdo_fetch("SELECT * FROM ".tablename($this->table_log)." WHERE uniacid = :uniacid AND from_user = :from_user  AND tfrom_user = :tfrom_user AND rid = :rid", array(':uniacid' => $uniacid,':from_user' => $from_user,':tfrom_user' => $tfrom_user,':rid' => $rid));
										
										$this->sendMobileVoteMsg($tuservote,$from_user, $rid, $uniacid);
										
										if (!empty($user['realname'])) {
											$user['realname'] = $user['realname'];
										} else {
											$user['realname'] = $user['nickname'];
										}
										
										
										$str = array('#编号#'=>$user['id'],'#参赛人名#'=>$user['realname']);
										$res = strtr($reply['votesuccess'],$str);										
										$msg = '恭喜您成功的为编号为： '.$user['id'].' ,姓名为： '.$user['realname'].' 的参赛者投了一票！';
										$msg = empty($res) ? $msg : $res ;
										
										$fmdata = array(
											"success" => 1,
											"msg" => $msg,
										);
										echo json_encode($fmdata);
										exit();	
										//message('恭喜您成功的为编号为： '.$user['id'].' ,姓名为： '.$user['realname'].' 的参赛者投了一票！',$turl,'success');
										
										
									
									}
								}
							}
						}
					} else {
						
						$fmdata = array(
							"success" => 10,
							"msg" => $reply['shareurl'],
						);
						echo json_encode($fmdata);
						exit();	
						//$surl = $reply['shareurl'];
						//header("location:$surl");
						//exit;
					}
				} else {
					
					if ($daytpxz >= $reply['daytpxz']) {
						$msg = '您当前最多可以投票'.$reply['daytpxz'].'个参赛选手，您当天的次数已经投完，请明天再来';
						//message($msg,$turl,'error');
						$fmdata = array(
							"success" => -1,
							"msg" => $msg,
						);
						echo json_encode($fmdata);
						exit();	
					}	else {
						
						if ($tfrom_user == $from_user) {
							$msg = '您不能为自己投票';
							$fmdata = array(
								"success" => -1,
								"msg" => $msg,
							);
							echo json_encode($fmdata);
							exit();	
						}else {
							
							
							$dayonetp = pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_log).' WHERE uniacid= :uniacid AND from_user = :from_user AND tfrom_user = :tfrom_user AND rid = :rid '.$times.' ORDER BY createtime DESC', array(':uniacid' => $uniacid, ':from_user' => $from_user, ':tfrom_user' => $tfrom_user,':rid' => $rid));
							
							$allonetp = pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_log).' WHERE uniacid= :uniacid AND from_user = :from_user AND tfrom_user = :tfrom_user AND rid = :rid ORDER BY createtime DESC', array(':uniacid' => $uniacid, ':from_user' => $from_user, ':tfrom_user' => $tfrom_user,':rid' => $rid));
							if ($allonetp >= $reply['allonetp']) {	
								$msg = '您总共可以给他投票'.$reply['allonetp'].'次，您已经投完！';
								//message($msg,$turl,'error');
								$fmdata = array(
									"success" => -1,
									"msg" => $msg,
								);
								echo json_encode($fmdata);
								exit();	
							} else {
								if ($dayonetp >= $reply['dayonetp']) {
										$msg = '您当前最多可以给他投票'.$reply['dayonetp'].'次，您已经投完，请明天再来';
										//message($msg,$turl,'error');
										$fmdata = array(
											"success" => -1,
											"msg" => $msg,
										);
										echo json_encode($fmdata);
										exit();	
									//exit;
								}else {
									
									if($reply['iscode'] == 1) {
										$code = $_GPC['code'];
										if (empty($code)) {
											$fmdata = array(
												"success" => -1,
												"msg" => '请输入验证码！',
											);
											echo json_encode($fmdata);
											exit();	
										}
										$hash = md5($code . $_W['config']['setting']['authkey']);
										if($_GPC['__code'] != $hash) {					
											$fmdata = array(
												"success" => -1,
												"msg" => '你输入的验证码不正确, 请重新输入.',
											);
											echo json_encode($fmdata);
											exit();	
											//message('你输入的验证码不正确, 请重新输入.');
										}
									}
									$votedate = array(
										'uniacid' => $uniacid,
										'rid' => $rid,
										'avatar' => $avatar,
										'nickname' => $nickname,
										'from_user' => $from_user,
										'afrom_user' => $fromuser,
										'tfrom_user' => $tfrom_user,
										'ip' => getip(),
										'createtime' => time(),
										
									);				
									pdo_insert($this->table_log, $votedate);
									pdo_update($this->table_users, array('photosnum'=> $user['photosnum']+1), array('rid' => $rid, 'from_user' => $tfrom_user,'uniacid' => $uniacid));
									
									$tuservote = pdo_fetch("SELECT * FROM ".tablename($this->table_log)." WHERE uniacid = :uniacid AND from_user = :from_user  AND tfrom_user = :tfrom_user AND rid = :rid", array(':uniacid' => $uniacid,':from_user' => $from_user,':tfrom_user' => $tfrom_user,':rid' => $rid));
									
									$this->sendMobileVoteMsg($tuservote,$from_user, $rid, $uniacid);
									if (!empty($user['realname'])) {
										$user['realname'] = $user['realname'];
									} else {
										$user['realname'] = $user['nickname'];
									}
									$str = array('#编号#'=>$user['id'],'#参赛人名#'=>$user['realname']);
										$res = strtr($reply['votesuccess'],$str);										
										$msg = '恭喜您成功的为编号为： '.$user['id'].' ,姓名为： '.$user['realname'].' 的参赛者投了一票！';
										$msg = empty($res) ? $msg : $res ;
									$fmdata = array(
										"success" => 1,
										"msg" => $msg,
									);
									echo json_encode($fmdata);
									exit();	
									//message('您成功的为Ta投了一票！',$turl,'success');
								}
							}
						}
					}
				}
			
			}
		
	
		
		echo json_encode($fmdata);
		exit();	
	
	}
	
	public function doMobileTbbs() {
		//关健词触发页面显示。
		global $_GPC,$_W;
		$uniacid = $_W['uniacid'];//当前公众号ID		
		$rid = $_GPC['rid'];
		$tfrom_user = $_GPC['tfrom_user'];		
		$oauthuser = $this->FM_checkoauth();
		$from_user = $oauthuser['from_user'];
		$avatar = $oauthuser['avatar'];
		$nickname = $oauthuser['nickname'];
		$follow = $oauthuser['follow'];	
		
		//$from_user = $_GPC['from_user'];
		//$from_user = $_COOKIE["user_oauth2_openid"];
		
		//$from_user_putonghao = $_COOKIE["user_putonghao_openid"];
		
		$fromuser = $_GPC["fromuser"];//分享人
		
		//$from_user = base64_encode(authcode($_COOKIE["user_oauth2_openid"], 'ENCODE'));
		//if (empty($from_user)){
		//	$from_user = $from_user;
		//	$from_user = $from_user;	
		//}
		if (empty($fromuser)){
			$fromuser = $_COOKIE["user_fromuser_openid"];
		}		
		$rb = array();
	
		
		
		if ($reply['tmyushe'] == 1) {
			//预设
			$ybbsreply = pdo_fetchall("SELECT * FROM ".tablename($this->table_bbsreply)." WHERE uniacid = :uniacid AND rid = :rid AND status = '9' order by `id` desc ",  array(':uniacid' => $uniacid,':rid' => $rid));		
			foreach ($ybbsreply as $r) {
				$rb[] .= $r['nickname'] . ' : ' . cutstr($r['content'], '15');
			}
		}			
		
		//评论
		$bbsreply = pdo_fetchall("SELECT * FROM ".tablename($this->table_bbsreply)." WHERE uniacid = :uniacid AND tfrom_user = :tfrom_user AND rid = :rid order by `id` desc ",  array(':uniacid' => $uniacid,':tfrom_user' => $tfrom_user,':rid' => $rid));
		if (empty($bbsreply)) {
			//预设
			$ybbsreply = pdo_fetchall("SELECT * FROM ".tablename($this->table_bbsreply)." WHERE uniacid = :uniacid AND rid = :rid AND status = '9' order by `id` desc ",  array(':uniacid' => $uniacid,':rid' => $rid));		
			foreach ($ybbsreply as $r) {
				$rb[] .= $r['nickname'] . ' : ' . cutstr($r['content'], '15');
			}
		} else {
			foreach ($bbsreply as $r) {
				$rb[] .= $r['nickname'] . ' : ' . cutstr($r['content'], '15');
			}
		}
		
		echo json_encode($rb);
		exit();	
	}
	
	public function doMobileTbbsreply() {
		//关健词触发页面显示。
		global $_GPC,$_W;
		$uniacid = $_W['uniacid'];//当前公众号ID		
		$rid = $_GPC['rid'];
		$oauthuser = $this->FM_checkoauth();
			$from_user = $oauthuser['from_user'];
			$avatar = $oauthuser['avatar'];
			$nickname = $oauthuser['nickname'];
			$follow = $oauthuser['follow'];
		$tfrom_user = $_GPC['tfrom_user'];			
		//$from_user = $_GPC['from_user'];
		//$from_user = $_COOKIE["user_oauth2_openid"];
		
		//$from_user_putonghao = $_COOKIE["user_putonghao_openid"];
		
		$fromuser = $_GPC["fromuser"];//分享人
		
		//$from_user = base64_encode(authcode($_COOKIE["user_oauth2_openid"], 'ENCODE'));
		//if (empty($from_user)){
		//	$from_user = !empty($_W['fans']['from_user']) ? $_W['fans']['from_user'] : $from_user;
		//	$from_user = $from_user;	
		//}
		if (empty($fromuser)){
			$fromuser = $_COOKIE["user_fromuser_openid"];
		}		
			//echo $fromuser;
			//exit;
			//$this->checkoauth2($rid,$from_user);//查询是否有cookie信息
			//活动规则
		if (!empty($rid)) {
			$reply = pdo_fetch("SELECT * FROM ".tablename($this->table_reply)." WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $rid));$bgarr = iunserializer($reply['bgarr']);
		}
		
			$mineip = getip();			
			$iplist = pdo_fetchall('SELECT * FROM '.tablename($this->table_iplist).' WHERE uniacid= :uniacid  AND  rid= :rid order by `createtime` desc ', array(':uniacid' => $uniacid, ':rid' => $rid));
			$mineipz = sprintf("%u",ip2long($mineip));
			foreach ($iplist as $i) {
				$iparrs = iunserializer($i['iparr']);
				$ipstart = sprintf("%u",ip2long($iparrs['ipstart']));
				$ipend = sprintf("%u",ip2long($iparrs['ipend']));					
				if ($mineipz >= $ipstart && $mineipz <= $ipend) {						
					$ipdate = array(
						'rid' => $rid,
						'uniacid' => $uniacid,
						'avatar' => $avatar,
						'nickname' => $nickname,
						'from_user' => $from_user,
						'ip' => $mineip,
						'hitym' => 'tvote',
						'createtime' => time(),
					);
					pdo_insert($this->table_iplistlog, $ipdate);
					if ($reply['ipstopvote'] == 1) {
						$ipurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('stopip', array('from_user' => $from_user, 'rid' => $rid));
						
						$fmdata = array(
							"success" => 3,
							"linkurl" => $ipurl,
							"msg" => '你存在刷票的嫌疑或者您的网络不稳定，请重新进入！',
						);
						echo json_encode($fmdata);
						exit();	
					}
					break;
				}
			}

		
						
			//查询自己是否参与活动
		if(!empty($from_user)) {
			$mygift = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and from_user = :from_user and rid = :rid", array(':uniacid' => $uniacid,':from_user' => $from_user,':rid' => $rid));
		}
			
		//查询是否参与活动
		if(!empty($tfrom_user)) {
		    $user = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and from_user = :from_user and rid = :rid", array(':uniacid' => $uniacid,':from_user' => $tfrom_user,':rid' => $rid));
		    if (!empty($user)) {
			   // pdo_update($this->table_users, array('hits' => $user['hits']+1), array('rid' => $rid, 'from_user' => $tfrom_user));
		    }else{
				$url = $_W['siteroot'] .'app/'.$this->createMobileUrl('photosvoteview', array('rid' => $rid));
				$fmdata = array(
							"success" => 3,
							"linkurl" => $url,
							"msg" => '！',
						);
						echo json_encode($fmdata);
						exit();	
				
				//header("location:$url");
				//exit;
			}
			
		}
		
		/**	
		$follow = pdo_fetch("SELECT follow FROM ".tablename('mc_mapping_fans')." WHERE uniacid = :uniacid and openid = :from_user", array(':uniacid' => $uniacid,':from_user' => $from_user));
		if (empty($follow)) {
			$follow = pdo_fetch("SELECT follow FROM ".tablename('mc_mapping_fans')." WHERE uniacid = :uniacid and openid = :from_user", array(':uniacid' => $uniacid,':from_user' => $from_user_putonghao));
			if (empty($follow)) {
				$follow = pdo_fetch("SELECT follow FROM ".tablename('mc_mapping_fans')." WHERE uniacid = :uniacid and openid = :from_user", array(':uniacid' => $uniacid,':from_user' => $_W['openid']));
			
			}
		}
		**/
		$bbsreply = pdo_fetchall("SELECT * FROM ".tablename($this->table_bbsreply)." WHERE uniacid = :uniacid AND tfrom_user = :tfrom_user AND rid = :rid order by `id` desc ",  array(':uniacid' => $uniacid,':tfrom_user' => $tfrom_user,':rid' => $rid));
		
		if ($reply['tmreply'] == 1) {//开启评论				
			if ($_GPC['tmreply'] == 1) {//开启评论	
				$tid = $user['id'];
				$content = $_GPC['msgstr'];
				//$reply_id = $user['id'];
				
				$rdata = array(
					'uniacid' => $uniacid,
					'rid' => $rid,
					'avatar' => $avatar,
					'nickname' => $nickname,
					'tfrom_user' => $tfrom_user,//帖子作者的openid
					'tid' => $tid,//帖子的ID
					'from_user' => $from_user,//回复评论帖子的openid
					//'reply_id' => $reply_id,//回复评论帖子的ID
					//'rfrom_user' => $rfrom_user,//被回复的评论的作者的openid
					//'to_reply_id' => $to_reply_id,//回复评论的id
					'content' => $content,//评论回复内容
					//'storey' => $storey,//绝对楼层
					'ip' => getip(),
					'createtime' => time(),
					
				);
				pdo_insert($this->table_bbsreply, $rdata);
				$reply_id = pdo_insertid();
				pdo_update($this->table_bbsreply, array('storey' => $reply_id), array('uniacid' => $uniacid, 'rid' => $rid, 'id' => $reply_id ));
			}
		}
	
	
	
		if ($reply['isbbsreply'] == 1) {//开启评论	
			if ($_GPC['isbbsreply'] == 1) {
				if (empty($tfrom_user)) {
					$msg = '被投票人不存在！';
					//message($msg,$turl,'error');
					$fmdata = array(
						"success" => -1,
						"msg" => $msg,
					);
					echo json_encode($fmdata);
					exit();	
				}
				if (empty($_GPC['content'])) {
					$msg = '你还没有评论哦';
					//message($msg,$turl,'error');
					$fmdata = array(
						"success" => -1,
						"msg" => $msg,
					);
					echo json_encode($fmdata);
					exit();	
				}
				
				if ($reply['iscode'] == 1) {					
					$code = $_GPC['code'];
					if (empty($code)) {
						$fmdata = array(
							"success" => -1,
							"msg" => '请输入验证码！',
						);
						echo json_encode($fmdata);
						exit();	
					}
					$hash = md5($code . $_W['config']['setting']['authkey']);
					if($_GPC['__code'] != $hash) {					
						$fmdata = array(
							"success" => -1,
							"msg" => '你输入的验证码不正确, 请重新输入.',
						);
						echo json_encode($fmdata);
						exit();	
						//message('你输入的验证码不正确, 请重新输入.');
					}
				}
				$tid = $user['id'];
				$content = $_GPC['content'];
				//$reply_id = $user['id'];
				
				$rdata = array(
					'uniacid' => $uniacid,
					'rid' => $rid,
					'avatar' => $avatar,
					'nickname' => $nickname,
					'tfrom_user' => $tfrom_user,//帖子作者的openid
					'tid' => $tid,//帖子的ID
					'from_user' => $from_user,//回复评论帖子的openid
					//'reply_id' => $reply_id,//回复评论帖子的ID
					//'rfrom_user' => $rfrom_user,//被回复的评论的作者的openid
					//'to_reply_id' => $to_reply_id,//回复评论的id
					'content' => $content,//评论回复内容
					//'storey' => $storey,//绝对楼层
					'ip' => getip(),
					'createtime' => time(),
					
				);
				pdo_insert($this->table_bbsreply, $rdata);
				$reply_id = pdo_insertid();
				pdo_update($this->table_bbsreply, array('storey' => $reply_id), array('uniacid' => $uniacid, 'rid' => $rid, 'id' => $reply_id ));
				
				$msg = '评论成功！';
				//message($msg,$turl,'error');
				$fmdata = array(
					"success" => 1,
					"msg" => $msg,
				);
				echo json_encode($fmdata);
				exit();	
				//message('评论成功！', referer(), 'success');
			
			}
		}
	}
	
	public function doMobileTuserphotos() {
		//关健词触发页面显示。
		global $_GPC,$_W;
		$uniacid = $_W['uniacid'];//当前公众号ID		
		$rid = $_GPC['rid'];
		$oauthuser = $this->FM_checkoauth();
			$from_user = $oauthuser['from_user'];
			$avatar = $oauthuser['avatar'];
			$nickname = $oauthuser['nickname'];
			$follow = $oauthuser['follow'];
		$tfrom_user = $_GPC['tfrom_user'];
		//$from_user = $_GPC['from_user'];
		//$from_user = $_COOKIE["user_oauth2_openid"];
		
		$fromuser = $_GPC["fromuser"];//分享人
		
		//$from_user = base64_encode(authcode($_COOKIE["user_oauth2_openid"], 'ENCODE'));
		//$this->checkoauth2($rid,$from_user);//查询是否有cookie信息
		//if (empty($from_user)){
		//    $from_user = !empty($_W['fans']['from_user']) ? $_W['fans']['from_user'] : $from_user;			
		//    $from_user = $from_user;	
		//}
		//$from_user = base64_encode(authcode($from_user, 'ENCODE'));
		if (empty($fromuser)){
			$fromuser = $_COOKIE["user_fromuser_openid"];
		}
        //活动规则
      	if (!empty($rid)) {
			$reply = pdo_fetch("SELECT * FROM ".tablename($this->table_reply)." WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $rid));$bgarr = iunserializer($reply['bgarr']);
			$number_num_day = $reply['number_num_day'];
			$picture = $reply['picture'];			
			$bgcolor = $reply['bgcolor'];	
			$sharephoto = toimage($reply['sharephoto']);			
			$share_shownum = $reply['share_shownum'];	
			
			if (substr($picture,0,6)=='images'){
			   $picture = $_W['attachurl'] . $picture;
			}else{
			   $picture = $_W['siteroot'] . $picture;
			}			
			
			if ($reply['status']==0) {
				$statpraisetitle = '<h1>活动暂停！请稍候再试！</h1>';
			}
			if (time()<$reply['start_time']) {//判断活动是否已经开始
				$statpraisetitle = '<h1>活动未开始！</h1>';
			}elseif (time()>$reply['end_time']) {//判断活动是否已经结束
				$statpraisetitle = '<h1>活动已结束！</h1>';
			}
			
			if ($reply['isipv']==1) {
				$this->stopip($rid, $uniacid, $from_user,getip(), $_GPC['do'], $reply['ipturl']);				
			}
 		}
 		
		
		if ($reply['ipannounce'] == 1) {
			$announce = pdo_fetchall("SELECT * FROM " . tablename($this->table_announce) . " WHERE uniacid= '{$_W['uniacid']}' AND rid= '{$rid}' ORDER BY id DESC");
			
		}
		//查询自己是否参与活动
		if(!empty($from_user)) {
		    $mygift = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and from_user = :from_user and rid = :rid", array(':uniacid' => $uniacid,':from_user' => $from_user,':rid' => $rid));
			
		}
		//查询是否参与活动
		if(!empty($tfrom_user)) {
		    $user = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and from_user = :from_user and rid = :rid", array(':uniacid' => $uniacid,':from_user' => $tfrom_user,':rid' => $rid));
		    if (!empty($user)) {
				//setcookie("user_yuedu", -10000);	
			    if (!isset($_COOKIE["user_yuedu"])) {
					 pdo_update($this->table_users, array('hits' => $user['hits']+1,), array('rid' => $rid, 'from_user' => $tfrom_user));
					 setcookie("user_yuedu", $from_user, time()+3600*24);
				}
		    }
			//$follow = pdo_fetch("SELECT follow FROM ".tablename('mc_mapping_fans')." WHERE uniacid = :uniacid and openid = :from_user", array(':uniacid' => $uniacid,':from_user' => $from_user));
		}
		
		if ($user['picarr']) {
			$picarr = iunserializer($user['picarr']);
		}else {
			$pacarr = array();
			for ($i = 1; $i <= $reply['tpxz']; $i++) {
				$n = $i - 1;
				$picarr[$n] .= $user['picarr_'.$i];				
			}
		}
		
		
		
		$starttime=mktime(0,0,0);//当天：00：00：00
		$endtime = mktime(23,59,59);//当天：23：59：59
		$times = '';
		$times .= ' AND createtime >=' .$starttime;
		$times .= ' AND createtime <=' .$endtime;
		$uservote = pdo_fetch("SELECT * FROM ".tablename($this->table_log)." WHERE uniacid = :uniacid AND from_user = :from_user  AND tfrom_user = :tfrom_user AND rid = :rid", array(':uniacid' => $uniacid,':from_user' => $from_user,':tfrom_user' => $tfrom_user,':rid' => $rid));
		$uallonetp = pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_log).' WHERE uniacid= :uniacid AND from_user = :from_user AND tfrom_user = :tfrom_user AND rid = :rid ORDER BY createtime DESC', array(':uniacid' => $uniacid, ':from_user' => $from_user, ':tfrom_user' => $tfrom_user,':rid' => $rid));
		$udayonetp = pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_log).' WHERE uniacid= :uniacid AND from_user = :from_user AND tfrom_user = :tfrom_user AND rid = :rid '.$times.' ORDER BY createtime DESC', array(':uniacid' => $uniacid, ':from_user' => $from_user, ':tfrom_user' => $tfrom_user,':rid' => $rid));		
		if ($reply['isbbsreply'] == 1) {//开启评论
		
			//评论
			$pindex = max(1, intval($_GPC['page']));
			$psize = 10;
			//取得用户列表
			$where = '';
			if (!empty($_GPC['keyword'])) {
					$keyword = $_GPC['keyword'];
					if (is_numeric($keyword)) 
						$where .= " AND id = '".$keyword."'";
					else 				
						$where .= " AND nickname LIKE '%{$keyword}%'";
				
			}
			$bbsreply = pdo_fetchall("SELECT * FROM ".tablename($this->table_bbsreply)." WHERE uniacid = :uniacid AND tfrom_user = :tfrom_user AND rid = :rid ".$where." order by `id` desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize,  array(':uniacid' => $uniacid,':tfrom_user' => $tfrom_user,':rid' => $rid));
			
			$total = pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_bbsreply).' WHERE uniacid= :uniacid AND tfrom_user = :tfrom_user AND rid = :rid '.$where.'', array(':uniacid' => $uniacid, ':tfrom_user' => $tfrom_user,':rid' => $rid));
			$pager = paginationm($total, $pindex, $psize, '', array('before' => 0, 'after' => 0, 'ajaxcallback' => ''));
			$btotal = pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_bbsreply).' WHERE uniacid= :uniacid AND tfrom_user = :tfrom_user AND rid = :rid ', array(':uniacid' => $uniacid, ':tfrom_user' => $tfrom_user,':rid' => $rid));
						
			
		}
		//每个奖品的位置
		//虚拟人数据配置
		$now = time();
		if($now-$reply['xuninum_time']>$reply['xuninumtime']){
		    pdo_update($this->table_reply, array('xuninum_time' => $now,'xuninum' => $reply['xuninum']+mt_rand($reply['xuninuminitial'],$reply['xuninumending'])), array('rid' => $rid));
		}
		//虚拟人数据配置
		//参与活动人数
		$totals = $reply['xuninum'] + pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_users).' WHERE uniacid=:uniacid and rid=:rid', array(':uniacid' => $uniacid,':rid' => $rid));
		//参与活动人数
		//查询分享标题以及内容变量
		$reply['sharetitle']= $this->get_share($uniacid,$rid,$from_user,$reply['sharetitle']);
		$reply['sharecontent']= $this->get_share($uniacid,$rid,$from_user,$reply['sharecontent']);
		//整理数据进行页面显示
		$myavatar = $avatar;
		$mynickname = $nickname;
		$shareurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('shareuserview', array('rid' => $rid,'duli'=> '2', 'fromuser' => $from_user, 'tfrom_user' => $tfrom_user));//分享URL
		$regurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('reg', array('rid' => $rid));//关注或借用直接注册页
		$lingjiangurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('lingjiang', array('rid' => $rid));//领奖URL
		$mygifturl = $_W['siteroot'] .'app/'.$this->createMobileUrl('photosvoteview', array('rid' => $rid));//我的页面
		$shouquan = base64_encode($_SERVER ['HTTP_HOST'].'anquan_ma_photosvote');
		$title = $user['nickname'] . ' 的投票详情！';
		
		$sharetitle = $user['nickname'] . '正在参加'. $reply['title'] .'，快来为'.$user['nickname'].'投一票吧！';
		$sharecontent = $user['nickname'] . '正在参加'. $reply['title'] .'，快来为'.$user['nickname'].'投一票吧！';
		$picture = !empty($user['photo']) ? toimage($user['photo']) : toimage($user['avatar']);
		
		
		$_share['link'] =$_W['siteroot'] .'app/'.$this->createMobileUrl('shareuserview', array('rid' => $rid,'duli'=> '2', 'fromuser' => $from_user, 'tfrom_user' => $tfrom_user));//分享URL
		 $_share['title'] = $user['nickname'] . '正在参加'. $reply['title'] .'，快来为'.$user['nickname'].'投一票吧！';
		$_share['content'] = $user['nickname'] . '正在参加'. $reply['title'] .'，快来为'.$user['nickname'].'投一票吧！';
		$_share['imgUrl'] = !empty($user['photo']) ? toimage($user['photo']) : toimage($user['avatar']);
		
		
		$toye = $this->_stopllq('tuserphotos');
		include $this->template($toye);
	}
	
	
	public function doMobilereg() {
		//关健词触发页面显示。
		global $_GPC,$_W;
		$uniacid = $_W['uniacid'];//当前公众号ID		
		$rid = $_GPC['rid'];
		
		$oauthuser = $this->FM_checkoauth();
			$from_user = $oauthuser['from_user'];
			$avatar = $oauthuser['avatar'];
			$nickname = $oauthuser['nickname'];
			$follow = $oauthuser['follow'];
		$from_user = !empty($_GPC["from_user"]) ? $_GPC["from_user"] : $from_user;	
		
		//
		//$from_user = $_COOKIE["user_oauth2_openid"];
		//$from_user = base64_encode(authcode($_COOKIE["user_oauth2_openid"], 'ENCODE'));
		//$from_user_putonghao = $_COOKIE["user_putonghao_openid"];
		
		
		//$this->checkoauth2($rid,$from_user);//查询是否有cookie信息
		//if (empty($from_user)){
		//    $from_user = !empty($_W['fans']['from_user']) ? $_W['fans']['from_user'] : $from_user;			
		//    $from_user = $from_user;	
		//}
		//$from_user = base64_encode(authcode($from_user, 'ENCODE'));
	
		
		//if (!empty($from_user_putonghao)) {
		//	$from_user_putonghao = $from_user_putonghao;				
		//} else {
		//	$from_user_putonghao = $_W['openid'];
		//}
		$fromuser = $_GPC["fromuser"];//分享人
		
		
		if (empty($fromuser)){
			$fromuser = $_COOKIE["user_fromuser_openid"];//分享人
		}
		
		//活动规则
      	if (!empty($rid)) {
			$reply = pdo_fetch("SELECT * FROM ".tablename($this->table_reply)." WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $rid));
			$bgarr = iunserializer($reply['bgarr']);
			$qiniu = iunserializer($reply['qiniu']);
			$now= time();
			if ($reply['status']==0) {
				$statpraisetitle = '<h1>活动暂停！请稍候再试！</h1>';
				$stopurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('stop', array('status' => '0', 'rid' => $rid));
				header("location:$stopurl");
				exit;
			}
			if (time()<$reply['start_time']) {//判断活动是否已经开始
				$statpraisetitle = '<h1>活动未开始！</h1>';
				
				$stopurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('stop', array('status' => '-1', 'rid' => $rid));
				header("location:$stopurl");
				exit;
			}elseif (time()>$reply['end_time']) {//判断活动是否已经结束
				$statpraisetitle = '<h1>活动已结束！</h1>';
				
				$stopurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('stop', array('status' => '1', 'rid' => $rid));
				header("location:$stopurl");
				exit;
			}
			
			
			if ($reply['isipv']==1) {
				$this->stopip($rid, $uniacid, $from_user,getip(), $_GPC['do'], $reply['ipturl']);				
			}
		}else{
		    echo "一般不会进入这里，进入这里说明有ＢＵＧ，请联系开发者";
			exit;
		}
		
		
		if ($reply['ipannounce'] == 1) {
			$announce = pdo_fetchall("SELECT * FROM " . tablename($this->table_announce) . " WHERE uniacid= '{$_W['uniacid']}' AND rid= '{$rid}' ORDER BY id DESC");
			
		}
				
		
		//赞助商
		if ($reply['isreg'] == 1) {
			$advs = pdo_fetchall("SELECT * FROM " . tablename($this->table_advs) . " WHERE enabled=1 AND uniacid= '{$_W['uniacid']}'  AND rid= '{$rid}' ORDER BY displayorder ASC");
			foreach ($advs as &$adv) {
				if (substr($adv['link'], 0, 5) != 'http:') {
					$adv['link'] = "http://" . $adv['link'];
				}
			}
			unset($adv);
		}
		
		//查询是否参与活动
		//if(!empty($from_user)) {
		$where = '';
		
		
		$mygift = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and from_user = :from_user and rid = :rid", array(':uniacid' => $uniacid,':from_user' => $from_user,':rid' => $rid));			
		//}
		if ($mygift['picarr']) {
			$picarr = iunserializer($mygift['picarr']);
		}else {
			$pacarr = array();
			for ($i = 1; $i <= $reply['tpxz']; $i++) {
				$n = $i - 1;
				$picarr[$n] .= $mygift['picarr_'.$i];				
			}
		}
		
		$reply['sharetitle']= $this->get_share($uniacid,$rid,$from_user,$reply['sharetitle']);
		$reply['sharecontent']= $this->get_share($uniacid,$rid,$from_user,$reply['sharecontent']);
		//整理数据进行页面显示
		$myavatar = $avatar;
		$mynickname = $nickname;
		$title = $reply['sharetitle'] . '报名';
		
		$_share['link'] = $_W['siteroot'] .'app/'.$this->createMobileUrl('shareuserview', array('rid' => $rid,'fromuser' => $from_user));//分享URL
		 $_share['title'] = $reply['sharetitle'];
		$_share['content'] =  $reply['sharecontent'];
		$_share['imgUrl'] = toimage($reply['sharephoto']);
		
		
		$toye = $this->_stopllq('reg');
		include $this->template($toye);
		
	}
	/**public function doMobileuploadifyqn() {
				//关健词触发页面显示。
		global $_GPC,$_W;
		$uniacid = $_W['uniacid'];//当前公众号ID		
		$rid = $_GPC['rid'];
		$oauthuser = $this->FM_checkoauth();
			$from_user = $oauthuser['from_user'];
			$avatar = $oauthuser['avatar'];
			$nickname = $oauthuser['nickname'];
			$follow = $oauthuser['follow'];
		$from_user = !empty($_GPC["from_user"]) ? $_GPC["from_user"] : $from_user;	
		if (!empty($rid)) {
			$reply = pdo_fetch("SELECT * FROM ".tablename($this->table_reply)." WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $rid));
			$bgarr = iunserializer($reply['bgarr']);
			$qiniu = iunserializer($reply['qiniu']);
			$now= time();
			if ($reply['status']==0) {
				$statpraisetitle = '<h1>活动暂停！请稍候再试！</h1>';
				$stopurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('stop', array('status' => '0', 'rid' => $rid));
				header("location:$stopurl");
				exit;
			}
			if (time()<$reply['start_time']) {//判断活动是否已经开始
				$statpraisetitle = '<h1>活动未开始！</h1>';
				
				$stopurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('stop', array('status' => '-1', 'rid' => $rid));
				header("location:$stopurl");
				exit;
			}elseif (time()>$reply['end_time']) {//判断活动是否已经结束
				$statpraisetitle = '<h1>活动已结束！</h1>';
				
				$stopurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('stop', array('status' => '1', 'rid' => $rid));
				header("location:$stopurl");
				exit;
			}
			
			
			if ($reply['isipv']==1) {
				$this->stopip($rid, $uniacid, $from_user,getip(), $_GPC['do'], $reply['ipturl']);				
			}
		}else{
		    echo "一般不会进入这里，进入这里说明有ＢＵＧ，请联系开发者";
			exit;
		}
		
		$targetFolder = '../addons/fm_photosvote/qiniu/uploads'; // Relative to the root

		$verifyToken = md5('unique_salt' . $_POST['timestamp']);

		 if(strstr($_FILES['photo']['name'],".jpg") or strstr($_FILES['photo']['name'],".gif") or strstr($_FILES['photo']['name'],".bmp") or strstr($_FILES['photo']['name'],".jpeg") or strstr($_FILES['photo']['name'],".png")){
				date_default_timezone_set('PRC');

				$time=date('YmdHis');
				$nfilename=$time.rand(10000,99999).$_FILES["photo"]["name"];
			
				require_once("../addons/fm_photosvote/qiniu/qiniu/io.php");
				require_once("../addons/fm_photosvote/qiniu/qiniu/rs.php");

				
				$key1 = $nfilename;											//$_FILES["photo"]["name"];
				$accessKey = $qiniu['accesskey'];
				$secretKey = $qiniu['secretkey'];
				$bucket = $qiniu['bucket'];
				$qiniuurl = $qiniu['qnlink'];
				
				Qiniu_SetKeys($accessKey, $secretKey);
				$putPolicy = new Qiniu_RS_PutPolicy($bucket);
				$upToken = $putPolicy->Token(null);
				$putExtra = new Qiniu_PutExtra();
				$putExtra->Crc32 = 1;
				list($ret, $err) = Qiniu_PutFile($upToken, $key1, $_FILES["photo"]["tmp_name"], $putExtra);
				//echo "====> Qiniu_PutFile result: \n";
				if ($err !== null) {
					var_dump($err);
					$fmdata = array(
						"success" => 1,
						"linkurl" => $ipurl,
						"msg" => $err,
					);
					die(json_encode($fmdata));
					exit();	
				} else {
					echo "http://".$qiniuurl."/".$nfilename;
					$mediaurl = "http://".$qiniuurl."/".$nfilename;
					
				//	mysql_query("insert into lts_pic(picname,picurl) values("."'".$nfilename."','". $mediaurl."')");
				//mysql_query("insert into lts_msg(pic,picname,msgtime,username) values("."true,'".$nfilename."','".$time."','".htmlspecialchars($_COOKIE['ltsuser']).""."')");

					
				}
				
			}else{
				echo "error";
			}
	}
	**/
	

	
	public function doMobileSaverecord() {
		global $_GPC,$_W;
		$uniacid = $_W['uniacid'];//当前公众号ID		
		$rid = $_GPC['rid'];
		$oauthuser = $this->FM_checkoauth();
		$from_user = !empty($_GPC["from_user"]) ? $_GPC["from_user"] :$oauthuser['from_user'];	
			
		header('Content-type: application/json');
		$fmmid = random(16);
		$now = time();
		$udata = array(
			'uniacid' => $uniacid,
			'rid' => $rid,
			'from_user' => $from_user,
			'fmmid' => $fmmid,
			'mediaid'  =>$_POST['serverId'],	
			'timelength' => $_GPC['timelength'],	
			'ip' => getip(),
			'createtime' => $now,
		);
		if ($udata['mediaid']) {
			$voice = $this->downloadVoice($udata['mediaid'], $fmmid);	
			$udata['voice'] = $voice;
		}
		
		pdo_insert($this->table_users_voice, $udata);
		pdo_update($this->table_users, array('fmmid' => $fmmid, 'mediaid'=>$_POST['serverId'], 'lastip' => getip(), 'lasttime' => $now, 'voice' => $voice, 'timelength' => $_GPC['timelength']), array('uniacid' => $uniacid, 'rid' => $rid, 'from_user' => $from_user));
		
		$data=json_encode(array('ret'=>0,'serverId'=>$_POST['serverId']));
		die($data);
	}
	public function doMobileSaverecord1() {
		global $_GPC,$_W;
		$uniacid = $_W['uniacid'];//当前公众号ID		
		$rid = $_GPC['rid'];
		$oauthuser = $this->FM_checkoauth();
		$from_user = !empty($_GPC["from_user"]) ? $_GPC["from_user"] :$oauthuser['from_user'];	
		$reply = pdo_fetch("SELECT qiniu FROM ".tablename($this->table_reply)." WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $rid));
		$qiniu = iunserializer($reply['qiniu']);		
		header('Content-type: application/json');
		$fmmid = random(16);
		$now = time();
		$udata = array(
			'uniacid' => $uniacid,
			'rid' => $rid,
			'from_user' => $from_user,
			'fmmid' => $fmmid,
			'mediaid'  =>$_POST['serverId'],	
			'timelength' => $_GPC['timelength'],	
			'ip' => getip(),
			'createtime' => $now,
		);
		if ($udata['mediaid']) {
			$voice = $this->downloadVoice($udata['mediaid'], $fmmid);	
			$udata['voice'] = $voice;
		}		
		pdo_insert($this->table_users_voice, $udata);
		pdo_update($this->table_users, array('fmmid' => $fmmid,'mediaid'  =>$_POST['serverId'],'lastip' => getip(),'lasttime' => $now,'voice' => $voice,'timelength' => $_GPC['timelength']), array('uniacid' => $uniacid, 'rid' => $rid, 'from_user' => $from_user));
				
		$data=json_encode(array('ret'=>0,'serverId'=>$_POST['serverId']));
		die($data);
	}
		
	public function doMobileplay() {
		//关健词触发页面显示。
		global $_GPC,$_W;
		$uniacid = $_W['uniacid'];//当前公众号ID		
		$rid = $_GPC['rid'];
		$oauthuser = $this->FM_checkoauth();
			$from_user = $oauthuser['from_user'];
			$avatar = $oauthuser['avatar'];
			$nickname = $oauthuser['nickname'];
			$follow = $oauthuser['follow'];
		$from_user = !empty($_GPC["from_user"]) ? $_GPC["from_user"] : $from_user;	
		$reply['sharetitle']= $this->get_share($uniacid,$rid,$from_user,$reply['sharetitle']);
		$reply['sharecontent']= $this->get_share($uniacid,$rid,$from_user,$reply['sharecontent']);
		//整理数据进行页面显示
		$myavatar = $avatar;
		$mynickname = $nickname;
		$title = $nickname . '的录音室';
		$serverid = $_GPC['serverId'];
		$date = $_GPC['date'];
		$recordtime = $_GPC['recordtime'];
		$_share['link'] = $_W['siteroot'] .'app/'.$this->createMobileUrl('shareuserview', array('rid' => $rid,'fromuser' => $from_user));//分享URL
		 $_share['title'] = $reply['sharetitle'];
		$_share['content'] =  $reply['sharecontent'];
		$_share['imgUrl'] = toimage($reply['sharephoto']);
		
		
		
			
		$toye = $this->_stopllq('Tregs');
		include $this->template('play');
		
	}	
	
	public function doMobileTregs() {
				//关健词触发页面显示。
		global $_GPC,$_W;
		$uniacid = $_W['uniacid'];//当前公众号ID		
		$rid = $_GPC['rid'];
		$oauthuser = $this->FM_checkoauth();
			$from_user = $oauthuser['from_user'];
			$avatar = $oauthuser['avatar'];
			$nickname = $oauthuser['nickname'];
			$follow = $oauthuser['follow'];
		$from_user = !empty($_GPC["from_user"]) ? $_GPC["from_user"] : $from_user;	
		$reply['sharetitle']= $this->get_share($uniacid,$rid,$from_user,$reply['sharetitle']);
		$reply['sharecontent']= $this->get_share($uniacid,$rid,$from_user,$reply['sharecontent']);
		//整理数据进行页面显示
		$myavatar = $avatar;
		$mynickname = $nickname;
		$title = $nickname . '的录音室';
		
		$_share['link'] = $_W['siteroot'] .'app/'.$this->createMobileUrl('shareuserview', array('rid' => $rid,'fromuser' => $from_user));//分享URL
		 $_share['title'] = $reply['sharetitle'];
		$_share['content'] =  $reply['sharecontent'];
		$_share['imgUrl'] = toimage($reply['sharephoto']);
		
		
		if ($reply['voicemoshi'] == 0) {
			if (preg_match('/Android/i',$agent)) {
				$isAndroid='true';
			}else {
				$isAndroid='false';
			}
			$toye = $this->_stopllq('treg1');
			include $this->template($toye);
		}elseif ($reply['voicemoshi'] == 1) {
			
			$toye = $this->_stopllq('treg1');
			include $this->template($toye);
		}
	}	
 
	function downloadImage($mediaid, $filename) {
		//下载图片	
		global $_W;
		$uniacid = $_W['uniacid'];//当前公众号ID		
		load()->func('file');
		$access_token = $_W['account']['access_token']['token'];
		$url = "http://file.api.weixin.qq.com/cgi-bin/media/get?access_token=$access_token&media_id=$mediaid";
		$fileInfo = $this->downloadWeixinFile($url);
		$updir = '../attachment/images/'.$uniacid.'/'.date("Y").'/'.date("m").'/';		
		if(!is_dir($updir)){ 
			mkdirs($updir);	
		}  
		$filename = $updir.$filename.".jpg"; 
		$this->saveWeixinFile($filename, $fileInfo["body"]);
		return $filename;
	}
	function downloadVoice($mediaid, $filename, $savetype = 0) {
		//下载语音		
		global $_W;
		load()->func('file');
		$uniacid = $_W['uniacid'];//当前公众号ID		
		$access_token = $_W['account']['access_token']['token'];
		$url = "http://file.api.weixin.qq.com/cgi-bin/media/get?access_token=$access_token&media_id=$mediaid";
		$fileInfo = $this->downloadWeixinFile($url);	
				
		$updir = '../attachment/audios/'.$uniacid.'/'.date("Y").'/'.date("m").'/';		
		if(!is_dir($updir)){ 
			mkdirs($updir);	
		}  
		//$key = $filename.".mp3";
		$filename = $updir.$filename.".mp3";
		
		$this->saveWeixinFile($filename, $fileInfo["body"]);
		//$localfilename = $_W['siteroot'].'attachment/audios/'.$uniacid.'/'.date("Y").'/'.date("m").'/'.$key;
		//$qimedia = $this->qiniusaveWeixinFile($key , $localfilename, $fileInfo["body"], $rid);
		if ($savetype == 1) {
			return $qimedia;
		} else {
			return $filename;
		}
		
		
	}
	function downloadThumb($mediaid, $filename) {
		//下载缩略图
		global $_W;
		load()->func('file');
		$uniacid = $_W['uniacid'];//当前公众号ID		
		$access_token = $_W['account']['access_token']['token'];
		$url = "http://file.api.weixin.qq.com/cgi-bin/media/get?access_token=$access_token&media_id=$mediaid";
		$fileInfo = $this->downloadWeixinFile($url);
		$updir = '../attachment/images/'.$uniacid.'/'.date("Y").'/'.date("m").'/';		
		if(!is_dir($updir)){ 
			mkdirs($updir);	
		}  
		$filename = $updir.$filename.".jpg"; 
		$this->saveWeixinFile($filename, $fileInfo["body"]);
		return $filename;
	}
 
	function downloadWeixinFile($url) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HEADER, 0);    
		curl_setopt($ch, CURLOPT_NOBODY, 0);    //只取body头
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$package = curl_exec($ch);
		$httpinfo = curl_getinfo($ch);
		curl_close($ch);
		$imageAll = array_merge(array('header' => $httpinfo), array('body' => $package)); 
		return $imageAll;
	}
	 
	function qiniusaveWeixinFile($key,$filename, $filecontent, $rid) {
		$reply = pdo_fetch("SELECT qiniu FROM ".tablename($this->table_reply)." WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $rid));
		$qiniu = iunserializer($reply['qiniu']);						
			
			require_once("../addons/fm_photosvote/qiniu/qiniu/io.php");
			require_once("../addons/fm_photosvote/qiniu/qiniu/rs.php");					
			$key1 = $key;											//$_FILES[$photo]["name"];
			$accessKey = $qiniu['accesskey'];
			$secretKey = $qiniu['secretkey'];
			$bucket = $qiniu['bucket'];
			$qiniuurl = $qiniu['qnlink'];
			
			Qiniu_SetKeys($accessKey, $secretKey);
			$putPolicy = new Qiniu_RS_PutPolicy($bucket);
			$upToken = $putPolicy->Token(null);
			$putExtra = new Qiniu_PutExtra();
			$putExtra->Crc32 = 1;
			list($ret, $err) = Qiniu_PutFile($upToken, $key1, $filename, $putExtra);
			if ($err !== null) {
				var_dump($err);						
			} else {
				
				$photo = "http://".$qiniuurl."/".$key;
				//var_dump($insertdata);
				return $photo;
				//pdo_update($this->table_users, $insertdata, array('from_user'=>$from_user, 'rid' => $rid, 'uniacid' => $uniacid));
					
			}
		
	}
	function saveWeixinFile($filename, $filecontent) {
		
		
		
		$local_file = fopen($filename, 'w');
		if (false !== $local_file){
			if (false !== fwrite($local_file, $filecontent)) {
				fclose($local_file);
			}
		}
	}
	
	
	
	public function doMobileTreg() {
		
		//关健词触发页面显示。
		global $_GPC,$_W;
		$uniacid = $_W['uniacid'];//当前公众号ID		
		$rid = $_GPC['rid'];
		//load()->func('tpl');
		load()->func('file');
		$oauthuser = $this->FM_checkoauth();
			$from_user = !empty($_GPC['from_user']) ? $_GPC['from_user'] : $oauthuser['from_user'];
			$nickname = $oauthuser['nickname'];
			$avatar = $oauthuser['avatar'];
			$follow = $oauthuser['follow'];
		
		$fromuser = $_GPC["fromuser"];//分享人

		
		if (empty($fromuser)){
			$fromuser = $_COOKIE["user_fromuser_openid"];//分享人
		}
		
		//活动规则

			$reply = pdo_fetch("SELECT * FROM ".tablename($this->table_reply)." WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $rid));
			$bgarr = iunserializer($reply['bgarr']);	
			$qiniu = iunserializer($reply['qiniu']);
			$now= time();
			
		
		$mineip = getip();
		$iplist = pdo_fetchall('SELECT * FROM '.tablename($this->table_iplist).' WHERE uniacid= :uniacid  AND  rid= :rid order by `createtime` desc ', array(':uniacid' => $uniacid, ':rid' => $rid));
		$mineipz = sprintf("%u",ip2long($mineip));
		foreach ($iplist as $i) {
			$iparrs = iunserializer($i['iparr']);
			$ipstart = sprintf("%u",ip2long($iparrs['ipstart']));
			$ipend = sprintf("%u",ip2long($iparrs['ipend']));					
			if ($mineipz >= $ipstart && $mineipz <= $ipend) {						
				$ipdate = array(
					'rid' => $rid,
					'uniacid' => $uniacid,
					'avatar' => $avatar,
					'nickname' => $nickname,
					'from_user' => $from_user,
					'ip' => $mineip,
					'hitym' => 'tvote',
					'createtime' => time(),
				);
				pdo_insert($this->table_iplistlog, $ipdate);
				if ($reply['ipstopvote'] == 1) {
					$ipurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('stopip', array('from_user' => $from_user, 'rid' => $rid));
					
					$fmdata = array(
						"success" => 9,
						"linkurl" => $ipurl,
						"msg" => '你存在刷票的嫌疑或者您的网络不稳定，请重新进入！',
					);
					echo json_encode($fmdata);
					exit();	
				}
				break;
			}
		}

		
						
			//查询自己是否参与活动
		if(!empty($from_user)) {
			$mygift = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and from_user = :from_user and rid = :rid", array(':uniacid' => $uniacid,':from_user' => $from_user,':rid' => $rid));
		}
		
		if (!$follow) {
			$linkurl = '';
			$fmdata = array(
				"success" => 9,
				"rid" => $rid,
				"msg" => '请关注后报名！',
				"linkurl" => $linkurl,
			);
			echo json_encode($fmdata);
			exit();	
		}
		
		
		
		$now = time();
		if($now <= $reply['bstart_time'] || $now >= $reply['bend_time']) {
					
			if ($now <= $reply['bstart_time']) {
				$fmdata = array(
					"success" => -1,
					"msg" => $reply['btipstart'],
				);
				echo json_encode($fmdata);
				exit();	
			}
			if ($now >= $reply['bend_time']) {
				$fmdata = array(
					"success" => -1,
					"msg" => $reply['btipend'],
				);
				echo json_encode($fmdata);
				exit();	
			}
		}
		if (!$mygift) {
			$insertdata = array(
				'rid'       => $rid,
				'uniacid'      => $uniacid,
				'from_user' => $from_user,
				'avatar'    => $avatar,
				'nickname'  => $nickname,			    
				'photo'  => '',			    
				'description'  => '',
				'photoname'  => '',
				'realname'  => '',
				'mobile'  => '',
				'weixin'  => '',
				'qqhao'  => '',
				'email'  => '',
				'address'  => '',
				'photosnum'  => '0',
				'xnphotosnum'  => '0',
				'hits'  => '1',
				'xnhits'  => '1',
				'yaoqingnum'  => '0',
				'createip' => getip(),
				'lastip' => getip(),
				'status'  => $reply['tpsh'] == 1 ? '0' : '1',
				'sharetime' => $now,
				'createtime'  => $now,
			);
			pdo_insert($this->table_users, $insertdata);
			
			   if($reply['isfans']){
					if($myavatar){
				        fans_update($from_user, array(
					        'avatar' => $myavatar,					
		                ));
				    } 
					if($mynickname){
				        fans_update($from_user, array(
					        'nickname' => $mynickname,					
		                ));
				    }
					
			        if($reply['isrealname']){
				        fans_update($from_user, array(
					        'realname' => $realname,					
		                ));
				    }
				    if($reply['ismobile']){
				        fans_update($from_user, array(
					        'mobile' => $mobile,					
		                ));
				    }				
				    if($reply['isqqhao']){
				        fans_update($from_user, array(
					        'qq' => $qqhao,					
		                ));
				    }
				    if($reply['isemail']){
				        fans_update($from_user, array(
					        'email' => $email,					
		                ));
				    }
				    if($reply['isaddress']){
				        fans_update($from_user, array(
					        'address' => $address,					
		                ));
				    }				
			    }
				 //查询是否被邀请人员
				$yaoqing = pdo_fetch("SELECT id,uid FROM ".tablename($this->table_data)." WHERE uniacid = :uniacid and from_user = :from_user and rid = :rid ORDER BY `visitorstime` asc", array(':uniacid' => $uniacid,':from_user' => $from_user,':rid' => $rid));
				if (!empty($yaoqing)){//更新被邀请人员状态 是以时间为标准为邀请人加资格
					pdo_update($this->table_data,array('isin' => 4),array('id' => $yaoqing['id']));
					$yaoqingren = pdo_fetch("SELECT yaoqingnum FROM ".tablename($this->table_users)." WHERE id = :id", array(':id' => $yaoqing['uid']));
					pdo_update($this->table_users,array('yaoqingnum' => $yaoqingren['yaoqingnum']+1),array('id' => $yaoqing['uid']));
					//查询所有其他邀请人并相互增加人气
					$yaoqingall = pdo_fetchall("SELECT id,uid FROM ".tablename($this->table_data)." WHERE uniacid = :uniacid and from_user = :from_user and rid = :rid and id!=".$yaoqing['id']." ORDER BY `visitorstime` asc", array(':uniacid' => $uniacid,':from_user' => $from_user,':rid' => $rid));
					foreach ($yaoqingall as $row) {
						pdo_update($this->table_data,array('isin' => 2),array('id' => $row['id']));
						if($reply['opensubscribe']==2){
							$yaoqingren = pdo_fetch("SELECT yaoqingnum FROM ".tablename($this->table_users)." WHERE id = :id", array(':id' => $row['uid']));
							pdo_update($this->table_users,array('yaoqingnum' => $yaoqingren['yaoqingnum']+1),array('id' => $row['uid']));					
						}
					}
				}
				
		}
					
		if ($_GPC['upphotosone'] == 'start') {
			$base64=file_get_contents("php://input"); //获取输入流			
			$base64=json_decode($base64,1);
			$data = $base64['base64'];
			
			if($data){
				preg_match("/data:image\/(.*);base64,/",$data,$res);
				$ext = $res[1];
				
				if(!in_array($ext,array("jpg","jpeg","png","gif"))){
					$fmdata = array(
						"success" => -1,
						"msg" => '您上传的文件为扩展名为：'.$ext.',请上传扩展名为：".jpg",".jpeg",".png",".gif"格式的文件',
					);
					echo json_encode($fmdata);
					die;
				}
				
				$nfilename = 'FM'.date('YmdHis').random(16).'.'.$ext;
				$updir = '../attachment/images/'.$uniacid.'/'.date("Y").'/'.date("m").'/';
				mkdirs($updir);	
				$data = preg_replace("/data:image\/(.*);base64,/","",$data);
				if (file_put_contents($updir.$nfilename,base64_decode($data))===false) {
					$fmdata = array(
						"success" => -1,
						"msg" => '上传错误',
					);
					echo json_encode($fmdata);
					//echo json_encode(array("error"=>1));
				}else{
					$mid = $_GPC['mid'];
					
					if (!$qiniu['isqiniu']) {
						$picurl = $updir.$nfilename;
						if ($mid == 0) {
							pdo_update($this->table_users, array('photo' => $picurl), array('from_user'=>$from_user, 'rid' => $rid, 'uniacid' => $uniacid));
						}else {
							$insertdata = array();								
							$insertdata['picarr_'.$mid] = $updir.$nfilename;
							pdo_update($this->table_users, $insertdata, array('from_user'=>$from_user, 'rid' => $rid, 'uniacid' => $uniacid));
						}
						$fmdata = array(
							"success" => 1,
							"msg" => '上传成功！',
							"imgurl" => $picurl,
						);
						echo json_encode($fmdata);
						exit();	
					}else {
						require_once("../addons/fm_photosvote/qiniu/qiniu/io.php");
						require_once("../addons/fm_photosvote/qiniu/qiniu/rs.php");					
						$key1 = $nfilename;											//$_FILES['photo']["name"];
						$accessKey = $qiniu['accesskey'];
						$secretKey = $qiniu['secretkey'];
						$bucket = $qiniu['bucket'];
						$qiniuurl = $qiniu['qnlink'];					
						$upurl = $_W['siteroot'].'attachment/images/'.$uniacid.'/'.date("Y").'/'.date("m").'/'.$nfilename;
						
						Qiniu_SetKeys($accessKey, $secretKey);
						$putPolicy = new Qiniu_RS_PutPolicy($bucket);
						$upToken = $putPolicy->Token(null);
						$putExtra = new Qiniu_PutExtra();
						$putExtra->Crc32 = 1;
						
						
						list($ret, $err) = Qiniu_RS_Fetch($upurl,$bucket,$key1);
						
						
						if ($err !== null) {
						//	var_dump($err);
							$fmdata = array(
								"success" => -1,
								"msg" => $err,
							);
							echo json_encode($fmdata);
							exit();	
						} else {
							$insertdata = array();	
							
							if ($mid == 0) {
								$insertdata['photo'] = "http://".$qiniuurl."/".$nfilename;
								/**
								//list($ret, $err) = Qiniu_RS_Delete($bucket,$mygift['photo']);
								$client = new Qiniu_MacHttpClient(null);							
								
								
								$err = Qiniu_RS_Delete($client, $bucket, $mygift['photo']);
								var_dump($err);
								$fmdata = array(
										"success" => -1,
										"msg" => $errt,
									);
									echo json_encode($fmdata);
									exit();	
								
								if ($err !== null) {
									var_dump($err);
									$fmdata = array(
										"success" => -1,
										"msg" => $err,
									);
									echo json_encode($fmdata);
									exit();	
								} else {
									$fmdata = array(
										"success" => -1,
										"msg" => 'cg',
									);
									echo json_encode($fmdata);
									exit();	
								}
								**/
								pdo_update($this->table_users, $insertdata, array('from_user'=>$from_user, 'rid' => $rid, 'uniacid' => $uniacid));
								file_delete($updir.$nfilename);
								
								
								//echo $insertdata['photo'];
								$fmdata = array(
									"success" => 1,
									"msg" => '上传成功',
									"imgurl" => $insertdata['photo'],
								);
								echo json_encode($fmdata);
								exit();	
							}else {
								$insertdata = array();								
								$insertdata['picarr_'.$mid] = "http://".$qiniuurl."/".$nfilename;
								//Qiniu_RS_Delete($bucket,$mygift['picarr_'.$mid]);
								pdo_update($this->table_users, $insertdata, array('from_user'=>$from_user, 'rid' => $rid, 'uniacid' => $uniacid));
								file_delete($updir.$nfilename);
								
								//echo $insertdata['photo'];
								$fmdata = array(
									"success" => 1,
									//"msg" => '上传成功',
									"imgurl" => $insertdata['picarr_'.$mid],
								);
								echo json_encode($fmdata);
								exit();	
							}
								
							
						}
					}
				}
				
			}else{
				$fmdata = array(
					"success" => -1,
					"msg" =>'没有发现上传图片',
				);
				echo json_encode($fmdata);
				exit();	
			}
		}
		if ($_GPC['upaudios'] == 'start') {	
			//var_dump($_FILES);
			$audiotype = $_GPC['audiotype'];
			
			if($_FILES[$audiotype]["tmp_name"]){
				
				$ext = $_FILES[$audiotype]["type"];
				//preg_match("[^\\\\/]+.$",$ext,$res);
				//$data = preg_replace("/^[^\/].+/","",$data);
				//$ext = $res['1'];
				//var_dump($res);
								
				if ($audiotype == 'music') {
					if($ext == 'audio/mpeg' || $ext == 'audio/mp3'){
					}else{
						$fmdata = array(
							"success" => -1,
							"msg" => '您上传的文件为扩展名为：'.$ext.',请上传扩展名为：".mp3"格式的文件',
						);
						echo json_encode($fmdata);
						die;
					}
					$nfilename = 'FM'.date('YmdHis').random(16).'.mp3';	
				}elseif ($audiotype == 'vedio') {
					if($ext <> 'video/mp4'){
						$fmdata = array(
							"success" => -1,
							"msg" => '您上传的文件为扩展名为：'.$ext.',请上传扩展名为：".mp4"格式的文件',
						);
						echo json_encode($fmdata);
						die;
					}
					$nfilename = 'FM'.date('YmdHis').random(16).'.mp4';	
				}
				
				
						
				if ($qiniu['isqiniu']) {	//开启七牛存储			
					require_once("../addons/fm_photosvote/qiniu/qiniu/io.php");
					require_once("../addons/fm_photosvote/qiniu/qiniu/rs.php");					
					$key1 = $nfilename;											//$_FILES["music"]["name"];
					$accessKey = $qiniu['accesskey'];
					$secretKey = $qiniu['secretkey'];
					$bucket = $qiniu['bucket'];
					$qiniuurl = $qiniu['qnlink'];
					
					Qiniu_SetKeys($accessKey, $secretKey);
					$putPolicy = new Qiniu_RS_PutPolicy($bucket);
					$upToken = $putPolicy->Token(null);
					$putExtra = new Qiniu_PutExtra();
					$putExtra->Crc32 = 1;
					list($ret, $err) = Qiniu_PutFile($upToken, $key1, $_FILES[$audiotype]["tmp_name"], $putExtra);
					if ($err !== null) {
					//	var_dump($err);
						$fmdata = array(
							"success" => -1,
							"msg" => $err,
						);
						echo json_encode($fmdata);
						exit();	
					} else {
						$insertdata = array();		
						$insertdata[$audiotype] = "http://".$qiniuurl."/".$nfilename;
						//Qiniu_RS_Delete($bucket,$mygift[$audiotype]);					
						pdo_update($this->table_users, $insertdata, array('from_user'=>$from_user, 'rid' => $rid, 'uniacid' => $uniacid));
						
						$fmdata = array(
							"success" => 1,
							"imgurl" => $insertdata[$audiotype],
						);
						echo json_encode($fmdata);
						exit();	
					}
				}else {
					$insertdata = array();
					$updir = '../attachment/audios/'.$uniacid.'/'.date("Y").'/'.date("m").'/';
					mkdirs($updir);	
					if ($mygift[$audiotype]) {
						file_delete($mygift[$audiotype]);	
					}		
					$music = file_upload($_FILES[$audiotype], 'audio'); 
					$insertdata[$audiotype] = $music['path']; 
											
					pdo_update($this->table_users, $insertdata, array('from_user'=>$from_user, 'rid' => $rid, 'uniacid' => $uniacid));
					$fmdata = array(
						"success" => 1,
						"imgurl" => $insertdata[$audiotype],
					);
					echo json_encode($fmdata);
					exit();	
				}
			}else{
				if ($_GPC[$audiotype]) {
					if ($qiniu['isqiniu']) {	//开启七牛存储	
						require_once("../addons/fm_photosvote/qiniu/qiniu/io.php");
						require_once("../addons/fm_photosvote/qiniu/qiniu/rs.php");					
						$nfilename = 'FM'.date('YmdHis').random(16);
						$key1 = $nfilename;											//$_FILES['photo']["name"];
						$accessKey = $qiniu['accesskey'];
						$secretKey = $qiniu['secretkey'];
						$bucket = $qiniu['bucket'];
						$qiniuurl = $qiniu['qnlink'];					
						$upurl = $_GPC[$audiotype];
						
						Qiniu_SetKeys($accessKey, $secretKey);
						$putPolicy = new Qiniu_RS_PutPolicy($bucket);
						$upToken = $putPolicy->Token(null);
						$putExtra = new Qiniu_PutExtra();
						$putExtra->Crc32 = 1;
						
						
						list($ret, $err) = Qiniu_RS_Fetch($upurl,$bucket,$key1);
						
						
						
						if ($err !== null) {
						//	var_dump($err);
							$fmdata = array(
								"success" => -1,
								"msg" => $err,
							);
							echo json_encode($fmdata);
							exit();	
						} else {
							$insertdata = array();							
							$insertdata[$audiotype] = "http://".$qiniuurl."/".$nfilename;
							//Qiniu_RS_Delete($bucket,$mygift[$audiotype]);
							pdo_update($this->table_users, $insertdata, array('from_user'=>$from_user, 'rid' => $rid, 'uniacid' => $uniacid));
								
							//echo $insertdata['photo'];
							$fmdata = array(
								"success" => 1,
								"msg" => '上传成功',
								"imgurl" => $insertdata[$audiotype],
							);
							echo json_encode($fmdata);
							exit();							
						}
					}else {
						$insertdata = array();							
						$insertdata[$audiotype] = $_GPC[$audiotype];
						pdo_update($this->table_users, $insertdata, array('from_user'=>$from_user, 'rid' => $rid, 'uniacid' => $uniacid));
						$fmdata = array(
							"success" => 1,
							"imgurl" => $_GPC[$audiotype],
						);
						echo json_encode($fmdata);
						exit();	
					}
					
					
					
					
				}else {
					if ($audiotype == 'music') {
						$msg = '请上传音频或者填写远程音频地址';
					}elseif ($audiotype == 'vedio') {
						$msg = '请上传视频或者填写远程视频地址';
					}
					
					$fmdata = array(
						"success" => -1,
						"msg" => $msg,
					);
					echo json_encode($fmdata);
					die;
					
				}
				
			}
		}
		
		if ($_GPC['treg'] == 1) {
				
			
		    if (empty($mygift)) {					
				$msg = '请先上传封面照片！';
				$fmdata = array(
					"success" => -1,
					"msg" => $msg,
				);
				echo json_encode($fmdata);
				exit();						
			}
			
			if (empty($_GPC['photoname'])) {
				//message('照片主题名没有填写！');
				$msg = '照片主题名没有填写！';
				$fmdata = array(
					"success" => -1,
					"msg" => $msg,
				);
				echo json_encode($fmdata);
				exit();	
			}
			if (empty($_GPC['description'])) {
				//message('介绍没有填写');
				$msg = '介绍没有填写';
				$fmdata = array(
					"success" => -1,
					"msg" => $msg,
				);
				echo json_encode($fmdata);
				exit();	
			}
			
			if($reply['isrealname']){
				if (empty($_GPC['realname'])) {
					//message('您的真实姓名没有填写，请填写！');
					$msg = '您的真实姓名没有填写，请填写！';
					$fmdata = array(
						"success" => -1,
						"msg" => $msg,
					);
					echo json_encode($fmdata);
					exit();	
				}
			}
			if($reply['ismobile']){
				if(!preg_match(REGULAR_MOBILE, $_GPC['mobile'])) {
					//message('必须输入手机号，格式为 11 位数字。');
					$msg = '必须输入手机号，格式为 11 位数字。';
					$fmdata = array(
						"success" => -1,
						"msg" => $msg,
					);
					echo json_encode($fmdata);
					exit();	
				}
			}
			
			if($reply['isrealname']){
				if ($mygift['realname']) {
					if ($mygift['realname'] == $_GPC['realname']) {
					
					}else {
						$realname = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and realname = :realname and rid = :rid", array(':uniacid' => $uniacid,':realname' => $_GPC['realname'],':rid' => $rid));
						if (!empty($realname)) {
							//message('您的真实姓名已经参赛，请重新填写！');
							$msg = '您的真实姓名已经参赛，请重新填写！';
							$fmdata = array(
								"success" => -1,
								"msg" => $msg,
							);
							echo json_encode($fmdata);
							exit();	
						}
					}
				
				}
				
			}
			
			if($reply['ismobile']){
				if ($mygift['mobile']) {
					if ($mygift['mobile'] == $_GPC['mobile']) {
					
					}else {
						$ymobile = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and mobile = :mobile and rid = :rid", array(':uniacid' => $uniacid,':mobile' => $_GPC['mobile'],':rid' => $rid));
						if(!empty($ymobile)) {
							//message('非常抱歉，此手机号码已经被注册，你需要更换注册手机号！');
							$msg = '非常抱歉，此手机号码已经被注册，你需要更换注册手机号！';
							$fmdata = array(
								"success" => -1,
								"msg" => $msg,
							);
							echo json_encode($fmdata);
							exit();	
						}
					}
				}
			}
			
		    $now = time();
			
				$udata = array(
					'avatar'    => $avatar,
					'nickname'  => $nickname,
					'description'  => $_GPC["description"],
					'photoname'  => $_GPC["photoname"],
					'realname'  => $_GPC["realname"],
					'mobile'  => $_GPC["mobile"],
					'weixin'  => $_GPC["weixin"],
					'qqhao'  => $_GPC["qqhao"],
					'email'  => $_GPC["email"],
					'address'  => $_GPC["address"],
					'lastip' => getip(),
					'lasttime' => $now,
				);
				pdo_update($this->table_users, $udata , array('uniacid' => $uniacid, 'rid' => $rid, 'from_user' => $from_user));
				
			    if($reply['isfans']){
			        if($avatar){
				        fans_update($from_user, array(
					        'avatar' => $avatar,					
		                ));
				    } 
					if($mynickname){
				        fans_update($from_user, array(
					        'nickname' => $mynickname,					
		                ));
				    }
					if($reply['isrealname']){
				        fans_update($from_user, array(
					        'realname' => $realname,					
		                ));
				    }
				    if($reply['ismobile']){
				        fans_update($from_user, array(
					        'mobile' => $mobile,					
		                ));
				    }				
				    if($reply['isqqhao']){
				        fans_update($from_user, array(
					        'qq' => $qqhao,					
		                ));
				    }
				    if($reply['isemail']){
				        fans_update($from_user, array(
					        'email' => $email,					
		                ));
				    }
				    if($reply['isaddress']){
				        fans_update($from_user, array(
					        'address' => $address,					
		                ));
				    }				
			    }
				$msg = '报名更新成功！';
				$linkurl = $_W['siteroot'].'app/'.$this->createMobileUrl('tuser', array('rid' => $rid,'tfrom_user' => $tfrom_user));
				$fmdata = array(
					"success" => 1,
					"msg" => $msg,
					"linkurl" => $linkurl,
				);
				echo json_encode($fmdata);
				exit();	
		}
	
	}
	
	
public function _UPLOADPIC($upfile, $maxsize, $updir, $newname = 'date') {     
         
    if ($newname == 'date')     
        $newname = date ( "Ymdhis" ).random(6); //使用日期做文件名       
    $name = $upfile ["name"];     
    $type = $upfile ["type"];     
    $size = $upfile ["size"];     
    $tmp_name = $upfile["tmp_name"];     
   
   
	
    switch ($type) {     
        case 'image/pjpeg' :     
        case 'image/jpeg' :     
            $extend = ".jpg";     
            break;     
        case 'image/gif' :     
            $extend = ".gif";     
            break;     
        case 'image/png' :     
            $extend = ".png";     
            break;     
    }     
	
  // exit;
    if (empty($extend)) {     
	
        echo  ( "警告！只能上传图片类型：GIF JPG PNG" );     
        exit ();     
    } 
	
	$maxsize = $maxsize * 1000;
    if ($size > $maxsize) {     
       // $maxpr = $maxsize / 1000; 
		$maxpr = sizecount($maxsize);
		
        echo  ( "警告！上传图片大小不能超过" . $maxpr . "!" );     
        exit ();     
    }     
	//print_r($updir . $newname . $extend);
	//exit;
	
    if (move_uploaded_file($tmp_name, $updir . $newname . $extend )) {     
	
	
        return $updir . $newname . $extend;     
    }     
}     	

	
public function show_pic_scal($width, $height, $picpath) {     
    $imginfo = GetImageSize ( $picpath );
	
	
    $imgw = $imginfo [0];     
    $imgh = $imginfo [1];     
         
    $ra = number_format ( ($imgw / $imgh), 1 ); //宽高比     
    $ra2 = number_format ( ($imgh / $imgw), 1 ); //高宽比     
         
    
    if ($imgw > $width or $imgh > $height) {     
        if ($imgw > $imgh) {     
            $newWidth = $width;     
            $newHeight = round ( $newWidth / $ra );     
             
        } elseif ($imgw < $imgh) {     
            $newHeight = $height;     
            $newWidth = round ( $newHeight / $ra2 );     
        } else {     
            $newWidth = $width;     
            $newHeight = round ( $newWidth / $ra );     
        }     
    } else {     
        $newHeight = $imgh;     
        $newWidth = $imgw;     
    }     
    $newsize [0] = $newWidth;     
    $newsize [1] = $newHeight;     
      
    return $newsize;     
}     

	
/**   
* 创建图片，返回资源类型   
* @param string $src 图片路径   
* @return resource $im 返回资源类型    
* **/    
public function create($src)  {     
    $info=getimagesize($src);     
    switch ($info[2])     
    {     
        case 1:     
            $im=imagecreatefromgif($src);     
            break;     
        case 2:     
            $im=imagecreatefromjpeg($src);     
            break;     
        case 3:     
            $im=imagecreatefrompng($src);     
            break;     
    }     
    return $im;     
}  

	
/**   
* 缩略图主函数   
* @param string $src 图片路径   
* @param int $w 缩略图宽度   
* @param int $h 缩略图高度   
* @return mixed 返回缩略图路径   
* **/    
    
	
public function resize($src,$w,$h,$autozl)  {     
    global $_GPC,$_W;
	$temp=pathinfo($src);     
    $niename=$temp["filename"];//去掉扩展名文件名     
    $name=$temp["basename"];//文件名     
    $dir=$temp["dirname"];//文件所在的文件夹     
    $extension=$temp["extension"];//文件扩展名     
    $savepath="{$dir}/{$name}";//缩略图保存路径,新的文件名为*.thumb.jpg     
	// 所有者可读写，其他人没有任何权限chmod("test.txt",0600);// 所有者可读写，其他人可读   
	
	
	//@chmod($savepath,0644);// 所有者有所有权限，其他所有人可读和执行chmod("test.txt",0755);// 所有者有所有权限，所有者所在的组可读chmod("test.txt",0740);   
	@chmod($savepath,0777);
	
	//chmod -R www:www;
	
 // print_r($savepath);

	//echo  $name;
	//echo '<br />';
   // echo  $dir;
	//echo '<br />';
	
   // echo  $extension;
	//exit; 

    //获取图片的基本信息     
    $info= @getimagesize($src);     
    $width=$info[0];//获取图片宽度     
    $height=$info[1];//获取图片高度     
    $per1=round($width/$height,2);//计算原图长宽比     
    $per2=round($w/$h,2);//计算缩略图长宽比     
    
    //计算缩放比例     
    if($per1>$per2 || $per1==$per2)  {     
        //原图长宽比大于或者等于缩略图长宽比，则按照宽度优先     
        $per=$w/$width;     
    }     
    if($per1<$per2)     
    {     
        //原图长宽比小于缩略图长宽比，则按照高度优先     
        $per=$h/$height;     
    }
    $temp_w=intval($width*$per);//计算原图缩放后的宽度     
    $temp_h=intval($height*$per);//计算原图缩放后的高度     
    $temp_img=@imagecreatetruecolor($temp_w,$temp_h);//创建画布     
    $im=$this->create($src);   

	
   @imagecopyresampled($temp_img,$im,0,0,0,0,$temp_w,$temp_h,$width,$height);     
    
	
	
	if($per1>$per2)   {     
        imagejpeg($temp_img,$savepath, $autozl);     
        imagedestroy($im);     
        return $this->addBg($savepath,$w,$h,"w");     
        //宽度优先，在缩放之后高度不足的情况下补上背景     
    }
	
    if($per1==$per2)  {
		
		
        imagejpeg($temp_img,$savepath, $autozl);     
        imagedestroy($im);   
		//echo $savepath;
		//exit; 
        return $savepath;     
        //等比缩放     
    }    
	
    if($per1<$per2)   {     
        imagejpeg($temp_img,$savepath, $autozl);     
        imagedestroy($im);     
        return $this->addBg($savepath,$w,$h,"h");     
        //高度优先，在缩放之后宽度不足的情况下补上背景     
    }     
}     
	   
/**   
* 添加背景   
* @param string $src 图片路径   
* @param int $w 背景图像宽度   
* @param int $h 背景图像高度   
* @param String $first 决定图像最终位置的，w 宽度优先 h 高度优先 wh:等比   
* @return 返回加上背景的图片   
* **/    
public function addBg($src,$w,$h,$fisrt="w")  {     
    $bg=imagecreatetruecolor($w,$h);     
    $white = imagecolorallocate($bg,255,255,255);     
    imagefill($bg,0,0,$white);//填充背景     
    
    //获取目标图片信息     
    $info=getimagesize($src);     
    $width=$info[0];//目标图片宽度     
    $height=$info[1];//目标图片高度     
    $img=$this->create($src);     
    if($fisrt=="wh")     
    {     
        //等比缩放     
        return $src;     
    }     
    else    
    {     
        if($fisrt=="w")     
        {     
            $x=0;     
            $y=($h-$height)/2;//垂直居中     
        }     
        if($fisrt=="h")     
        {     
            $x=($w-$width)/2;//水平居中     
            $y=0;     
        }     
        imagecopymerge($bg,$img,$x,$y,0,0,$width,$height,100);     
        imagejpeg($bg,$src,100);     
        imagedestroy($bg);     
        imagedestroy($img);     
        return $src;     
    }
    
}    
	
	
	public function doMobilereguser() {
		//关健词触发页面显示。
		global $_GPC,$_W;
		$uniacid = $_W['uniacid'];//当前公众号ID		
		$rid = $_GPC['rid'];
		$oauthuser = $this->FM_checkoauth();
			$from_user = $oauthuser['from_user'];
			$avatar = $oauthuser['avatar'];
			$nickname = $oauthuser['nickname'];
			$follow = $oauthuser['follow'];
		$toye = $this->_stopllq('reguser');
		include $this->template($toye);
		
	}
	
	public function doMobilePaihang() {
		//关健词触发页面显示。
		global $_GPC,$_W;
		$uniacid = $_W['uniacid'];//当前公众号ID		
		$rid = $_GPC['rid'];
		$oauthuser = $this->FM_checkoauth();
			$from_user = $oauthuser['from_user'];
			$avatar = $oauthuser['avatar'];
			$nickname = $oauthuser['nickname'];
			$follow = $oauthuser['follow'];
		$tfrom_user = $_GPC['tfrom_user'];
		//$from_user = $_COOKIE["user_oauth2_openid"];
		load()->model('account');
		//$from_user = base64_encode(authcode($_COOKIE["user_oauth2_openid"], 'ENCODE'));
		//$this->checkoauth2($rid,$from_user);//查询是否有cookie信息
		//if (empty($from_user)){
		//    $from_user = !empty($_W['fans']['from_user']) ? $_W['fans']['from_user'] : $from_user;			
		 //   $from_user = $from_user;	
		//}
		//$from_user = base64_encode(authcode($from_user, 'ENCODE'));
		
        //活动规则
      	if (!empty($rid)) {
			$reply = pdo_fetch("SELECT * FROM ".tablename($this->table_reply)." WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $rid));$bgarr = iunserializer($reply['bgarr']);			
			$number_num_day = $reply['number_num_day'];
			$picture = $reply['picture'];			
			$bgcolor = $reply['bgcolor'];				
			$share_shownum = $reply['share_shownum'];	
			
			if (substr($picture,0,6)=='images'){
			   $picture = $_W['attachurl'] . $picture;
			}else{
			   $picture = $_W['siteroot'] . $picture;
			}
			
			if ($reply['isipv']==1) {
				$this->stopip($rid, $uniacid, $from_user,getip(), $_GPC['do'], $reply['ipturl']);				
			}
			
 		}
		
		
		if ($reply['ipannounce'] == 1) {
			$announce = pdo_fetchall("SELECT * FROM " . tablename($this->table_announce) . " WHERE uniacid= '{$_W['uniacid']}' AND rid= '{$rid}' ORDER BY id DESC");
			
		}
		//赞助商
		if ($reply['ispaihang'] == 1) {
			$advs = pdo_fetchall("SELECT * FROM " . tablename($this->table_advs) . " WHERE enabled=1 AND uniacid= '{$_W['uniacid']}'  AND rid= '{$rid}' ORDER BY displayorder ASC");
			foreach ($advs as &$adv) {
				if (substr($adv['link'], 0, 5) != 'http:') {
					$adv['link'] = "http://" . $adv['link'];
				}
			}
			unset($adv);
		}
		
		//统计
		$csrs = pdo_fetchcolumn("SELECT COUNT(*) FROM ".tablename($this->table_users)." WHERE rid= ".$rid."") + $reply['xuninum'];//参赛人数
		$ljtp = pdo_fetchcolumn("SELECT COUNT(*) FROM ".tablename($this->table_log)." WHERE rid= ".$rid."") + pdo_fetchcolumn("SELECT sum(xnphotosnum) FROM ".tablename($this->table_users)." WHERE rid= ".$rid."");//累计投票
		$cyrs = $csrs + $reply['hits'] + pdo_fetchcolumn("SELECT sum(hits) FROM ".tablename($this->table_users)." WHERE rid= ".$rid."") + pdo_fetchcolumn("SELECT sum(xnhits) FROM ".tablename($this->table_users)." WHERE rid= ".$rid."");//参与人数
		
		if(!empty($from_user)) {
		    $mygift = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and from_user = :from_user and rid = :rid", array(':uniacid' => $uniacid,':from_user' => $from_user,':rid' => $rid));
			//此处更新一下分享量和邀请量
			
		}
		
		
		
		if ($_GPC['votelog'] == 1) {//投票人
			$tuser = pdo_fetch("SELECT avatar,nickname FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and from_user = :from_user and rid = :rid", array(':uniacid' => $uniacid,':from_user' => $tfrom_user,':rid' => $rid));
			
			$pindex = max(1, intval($_GPC['page']));
			$psize = $reply['phbtpxz'];
			$m = ($pindex-1) * $psize+1;
			//取得用户列表
			$where = '';			
			
			if (!empty($tfrom_user)) {				
				$where .= " AND tfrom_user = '".$tfrom_user."'";				
			}
			
			$userlist = pdo_fetchall('SELECT * FROM '.tablename($this->table_log).' WHERE uniacid= :uniacid and rid = :rid '.$where.' ORDER BY `id` DESC LIMIT ' . ($pindex - 1) * $psize . ',' . $psize, array(':uniacid' => $uniacid,':rid' => $rid));
			$total = pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_log).' WHERE uniacid= :uniacid and rid = :rid '.$where.'', array(':uniacid' => $uniacid,':rid' => $rid));
			$pager = paginationm($total, $pindex, $psize, '', array('before' => 0, 'after' => 0, 'ajaxcallback' => ''));
			
			$title = $tuser['nickname'] . ' 的投票用户 - ' . $reply['title']; 
			$sharetitle = $tuser['nickname'] . '正在参加'. $reply['title'] .'，快来为'.$tuser['nickname'].'投一票吧！';
			$sharecontent = $tuser['nickname'] . '正在参加'. $reply['title'] .'，快来为'.$tuser['nickname'].'投一票吧！';
			$sharephoto = !empty($mygift['photo']) ? toimage($mygift['photo']) : toimage($tuser['avatar']);
			
			 $_share['title'] = $tuser['nickname'] . '正在参加'. $reply['title'] .'，快来为'.$tuser['nickname'].'投一票吧！';
			$_share['content'] = $tuser['nickname'] . '正在参加'. $reply['title'] .'，快来为'.$tuser['nickname'].'投一票吧！';
			$_share['imgUrl'] =  !empty($mygift['photo']) ? toimage($mygift['photo']) : toimage($tuser['avatar']);
		}else {//排行榜用户
			$pindex = max(1, intval($_GPC['page']));
			$psize = $reply['phbtpxz'];
			$m = ($pindex-1) * $psize+1;
			//取得用户列表
			$where = '';
			if (!empty($_GPC['keyword'])) {
					$keyword = $_GPC['keyword'];
					if (is_numeric($keyword)) 
						$where .= " AND id = '".$keyword."'";
					else 				
						$where .= " AND nickname LIKE '%{$keyword}%'";
				
			}
			
			if (!empty($tfrom_user)) {				
				$where .= " AND tfrom_user = '".$tfrom_user."'";				
			}
			$where .= " AND status = '1'";
			$userlist = pdo_fetchall('SELECT * FROM '.tablename($this->table_users).' WHERE uniacid= :uniacid and rid = :rid '.$where.' order by `photosnum` + `xnphotosnum` desc LIMIT ' . ($pindex - 1) * $psize . ',' . $psize, array(':uniacid' => $uniacid,':rid' => $rid));
			$total = pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_users).' WHERE uniacid= :uniacid and rid = :rid '.$where.'', array(':uniacid' => $uniacid,':rid' => $rid));
			$pager = paginationm($total, $pindex, $psize, '', array('before' => 0, 'after' => 0, 'ajaxcallback' => ''));
			
			
			
			$title = $reply['title'] . ' 排行榜 - ' . $_W['account']['name']; 
			$sharetitle = $reply['title'] . ' 排行榜 - ' . $_W['account']['name'];
			$sharecontent = $reply['title'] . ' 排行榜 - ' . $_W['account']['name'];
			$sharephoto = !empty($mygift['photo']) ? toimage($mygift['photo']) : toimage($mygift['avatar']);
			
			$_share['title'] = $reply['title'] . ' 排行榜 - ' . $_W['account']['name']; 
			$_share['content'] = $reply['title'] . ' 排行榜 - ' . $_W['account']['name']; 
			$_share['imgUrl'] = toimage($reply['sharephoto']);	
			
		}

				
		//整理数据进行页面显示		
		$reply['sharetitle']= $this->get_share($uniacid,$rid,$from_user,$reply['sharetitle']);
		$reply['sharecontent']= $this->get_share($uniacid,$rid,$from_user,$reply['sharecontent']);
		
		$myavatar = $avatar;
		$mynickname = $nickname;
		$shareurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('shareuserview', array('rid' => $rid,'fromuser' => $from_user));//分享URL
		$regurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('reg', array('rid' => $rid));//关注或借用直接注册页
		$guanzhu = $reply['shareurl'];//没有关注用户跳转引导页
		$lingjiangurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('lingjiang', array('rid' => $rid));//领奖URL
		$mygifturl = $_W['siteroot'] .'app/'.$this->createMobileUrl('photosvoteview', array('rid' => $rid));//我的页面
		
		$_share['link'] = $_W['siteroot'] .'app/'.$this->createMobileUrl('shareuserview', array('rid' => $rid,'fromuser' => $from_user));//分享URL		
		
				
		
		$toye = $this->_stopllq('paihang');
		include $this->template($toye);

	}
	
	public function doMobileDes() {
		//关健词触发页面显示。
		global $_GPC,$_W;
		$uniacid = $_W['uniacid'];//当前公众号ID		
		$rid = $_GPC['rid'];
		$oauthuser = $this->FM_checkoauth();
			$from_user = $oauthuser['from_user'];
			$avatar = $oauthuser['avatar'];
			$nickname = $oauthuser['nickname'];
			$follow = $oauthuser['follow'];
		//$from_user = $_COOKIE["user_oauth2_openid"];
		//$from_user = base64_encode(authcode($_COOKIE["user_oauth2_openid"], 'ENCODE'));
		//$this->checkoauth2($rid,$from_user);//查询是否有cookie信息
		//if (empty($from_user)){
		  //  $from_user = !empty($_W['fans']['from_user']) ? $_W['fans']['from_user'] : $from_user;			
		 //   $from_user = $from_user;	
		//}
		//$from_user = base64_encode(authcode($from_user, 'ENCODE'));
		
		

	   //活动规则
      	if (!empty($rid)) {
			$reply = pdo_fetch("SELECT * FROM ".tablename($this->table_reply)." WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $rid));$bgarr = iunserializer($reply['bgarr']);			
			$number_num_day = $reply['number_num_day'];
			$picture = $reply['picture'];			
			$bgcolor = $reply['bgcolor'];		
			$sharephoto = toimage($reply['sharephoto']);		
			$share_shownum = $reply['share_shownum'];	
			
			if (substr($picture,0,6)=='images'){
			   $picture = $_W['attachurl'] . $picture;
			}else{
			   $picture = $_W['siteroot'] . $picture;
			}			
			
			if ($reply['status']==0) {
				$statpraisetitle = '<h1>活动暂停！请稍候再试！</h1>';
				$stopurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('stop', array('status' => '0', 'rid' => $rid));
				header("location:$stopurl");
				exit;
			}
			if (time()<$reply['start_time']) {//判断活动是否已经开始
				$statpraisetitle = '<h1>活动未开始！</h1>';
				
				$stopurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('stop', array('status' => '-1', 'rid' => $rid));
				header("location:$stopurl");
				exit;
			}elseif (time()>$reply['end_time']) {//判断活动是否已经结束
				$statpraisetitle = '<h1>活动已结束！</h1>';
				
				$stopurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('stop', array('status' => '1', 'rid' => $rid));
				header("location:$stopurl");
				exit;
			}
			
			if ($reply['isipv']==1) {
				$this->stopip($rid, $uniacid, $from_user,getip(), $_GPC['do'], $reply['ipturl']);				
			}
 		}
		
		
		if ($reply['ipannounce'] == 1) {
			$announce = pdo_fetchall("SELECT * FROM " . tablename($this->table_announce) . " WHERE uniacid= '{$_W['uniacid']}' AND rid= '{$rid}' ORDER BY id DESC");
			
		}
		//赞助商
		if ($reply['isdes'] == 1) {
			$advs = pdo_fetchall("SELECT * FROM " . tablename($this->table_advs) . " WHERE enabled=1 AND uniacid= '{$_W['uniacid']}'  AND rid= '{$rid}' ORDER BY displayorder ASC");
			foreach ($advs as &$adv) {
				if (substr($adv['link'], 0, 5) != 'http:') {
					$adv['link'] = "http://" . $adv['link'];
				}
			}
			unset($adv);
		}
		//统计
		$csrs = pdo_fetchcolumn("SELECT COUNT(*) FROM ".tablename($this->table_users)." WHERE rid= ".$rid."") + $reply['xuninum'];//参赛人数
		$ljtp = pdo_fetchcolumn("SELECT COUNT(*) FROM ".tablename($this->table_log)." WHERE rid= ".$rid."") + pdo_fetchcolumn("SELECT sum(xnphotosnum) FROM ".tablename($this->table_users)." WHERE rid= ".$rid."");//累计投票
		$cyrs = $csrs +  $reply['hits'] + pdo_fetchcolumn("SELECT sum(hits) FROM ".tablename($this->table_users)." WHERE rid= ".$rid."") + pdo_fetchcolumn("SELECT sum(xnhits) FROM ".tablename($this->table_users)." WHERE rid= ".$rid."");//参与人数
		
		
		if(!empty($from_user)) {
		    $mygift = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and from_user = :from_user and rid = :rid", array(':uniacid' => $uniacid,':from_user' => $from_user,':rid' => $rid));
			//此处更新一下分享量和邀请量
			
		}

		$reply['sharetitle']= $this->get_share($uniacid,$rid,$from_user,$reply['sharetitle']);
		$reply['sharecontent']= $this->get_share($uniacid,$rid,$from_user,$reply['sharecontent']);
		
		//整理数据进行页面显示		
		$myavatar = $avatar;
		$mynickname = $nickname;
		$shareurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('shareuserview', array('rid' => $rid,'fromuser' => $from_user));//分享URL
		$regurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('reg', array('rid' => $rid));//关注或借用直接注册页
		$guanzhu = $reply['shareurl'];//没有关注用户跳转引导页
		$lingjiangurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('lingjiang', array('rid' => $rid));//领奖URL
		$mygifturl = $_W['siteroot'] .'app/'.$this->createMobileUrl('photosvoteview', array('rid' => $rid));//我的页面
		$title = $reply['title'];
		
		
		$_share['link'] = $_W['siteroot'] .'app/'.$this->createMobileUrl('shareuserview', array('rid' => $rid,'fromuser' => $from_user));//分享URL
		 $_share['title'] = $reply['sharetitle'];
		$_share['content'] =  $reply['sharecontent'];
		$_share['imgUrl'] = toimage($reply['sharephoto']);		
		
		
		$toye = $this->_stopllq('des');
		include $this->template($toye);

	}

	private function sendMobileRegMsg($from_user, $rid, $uniacid) {
		global $_GPC,$_W;
		$reply = pdo_fetch("SELECT * FROM ".tablename($this->table_reply)." WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $rid));$bgarr = iunserializer($reply['bgarr']);
		
		
		$userinfo = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and from_user = :from_user and rid = :rid", array(':uniacid' => $uniacid,':from_user' => $from_user,':rid' => $rid));	
		include 'mtemplate/regvote.php';
		$url =  $_W['siteroot'] .'app/'.$this->createMobileUrl('Tuser', array('rid' => $rid,'from_user' => $from_user,'tfrom_user' => $from_user));
		if (!empty($template_id)) {
			$this->sendtempmsg($template_id, $url, $data, '#FF0000', $from_user);
		}
	}
	
	private function sendMobileVoteMsg($tuservote,$tousers, $rid, $uniacid) {
		global $_GPC,$_W;
		$reply = pdo_fetch("SELECT * FROM ".tablename($this->table_reply)." WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $rid));$bgarr = iunserializer($reply['bgarr']);
		$u = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid  AND from_user = :from_user AND rid = :rid", array(':uniacid' => $uniacid,':from_user' => $tuservote['tfrom_user'],':rid' => $rid));
		include 'mtemplate/vote.php';
		$url = $_W['siteroot'] .'app/'.$this->createMobileUrl('Tuser', array('rid' => $rid,'from_user' => $tousers,'tfrom_user' => $tuservote['tfrom_user']));
		if (!empty($template_id)) {
			$this->sendtempmsg($template_id, $url, $data, '#FF0000', $tousers);
		}
	}
	private function sendMobileHsMsg($from_user, $rid, $uniacid) {
		global $_GPC,$_W;
		$reply = pdo_fetch("SELECT * FROM ".tablename($this->table_reply)." WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $rid));		
		$userinfo = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and from_user = :from_user and rid = :rid", array(':uniacid' => $uniacid,':from_user' => $from_user,':rid' => $rid));	
		include 'mtemplate/shenhe.php';
		$url =  $_W['siteroot'] .'app/'.$this->createMobileUrl('Tuser', array('rid' => $rid,'from_user' => $from_user,'tfrom_user' => $from_user));
		if (!empty($template_id)) {
			$this->sendtempmsg($template_id, $url, $data, '#FF0000', $from_user);
		}
	}
	
	public function sendtempmsg($template_id, $url, $data, $topcolor, $tousers = '') {		
		global $_GPC,$_W;
		$uniacid = $_W['uniacid'];//当前公众号ID		
		$rid = $_GPC['rid'];
			
		load()->func('communication');
		$oauthuser = $this->FM_checkoauth();
			$from_user = $oauthuser['from_user'];
			$avatar = $oauthuser['avatar'];
			$nickname = $oauthuser['nickname'];
			$follow = $oauthuser['follow'];	
		if (empty($tousers)) {
			$from_user =$from_user;
		}else {
			$from_user =$tousers;
		}
		load()->classs('weixin.account');
		//$accObj= WeixinAccount::create($uniacid);
		//$access_token = $accObj->fetch_token();
		$access_token = WeAccount::token();
		//$tokens =$this->get_weixin_token();
		if(empty($access_token)) {
			return;
		}
		$postarr = '{"touser":"'.$from_user.'","template_id":"'.$template_id.'","url":"'.$url.'","topcolor":"'.$topcolor.'","data":'.$data.'}';
		$res = ihttp_post('https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.$access_token,$postarr);
		return true;
	}
	
	
	
	public function doMobileshareuserview() {
	    global $_GPC,$_W;
		
		
		$uniacid = $_W['uniacid'];//当前公众号ID
		load()->model('account');	
		$rid = $_GPC['rid'];
		$oauthuser = $this->FM_checkoauth();
			$from_user = $oauthuser['from_user'];
			$avatar = $oauthuser['avatar'];
			$nickname = $oauthuser['nickname'];
			$follow = $oauthuser['follow'];
		//$from_user = $_W['openid'];
		$tfrom_user = $_GPC['tfrom_user'];
		//$from_user = base64_encode(authcode($from_user, 'ENCODE'));
		$fromuser = $_GPC['fromuser'];
		$page_fromuser = $_GPC['fromuser'];
		$serverapp = $_W['account']['level'];	//是否为高级号
		$cfg = $this->module['config'];
	    $appid = $cfg['appid'];
		$secret = $cfg['secret'];
		load()->func('communication');
		
		
		
		
		if(!empty($fromuser)){
			if (!isset($_COOKIE["user_fromuser_openid"])) {
				setcookie("user_fromuser_openid", $fromuser, time()+3600*24*7*30);
			}
		}
		if(!empty($fromuser)){
			if (!isset($_COOKIE["user_tfrom_user_openid"])) {
				setcookie("user_tfrom_user_openid", $tfrom_user, time()+3600*24*7*30);
			}
		}
	
        if ( isset($avatar) && isset($nickname) && isset($from_user) ){
			
			 //$appid = $_W['account']['key'];
		           			
		    $photosvoteviewurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('shareuserdata', array('rid' => $rid,'fromuser' => $page_fromuser,'duli' => $_GPC['duli'],'tfrom_user' => $_GPC['tfrom_user']));
			header("location:$photosvoteviewurl");
			exit;
		}else{
				$this->FM_checkoauth();
				$photosvoteviewurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('shareuserdata', array('rid' => $rid,'fromuser' => $page_fromuser,'duli' => $_GPC['duli'],'tfrom_user' => $_GPC['tfrom_user']));
				header("location:$photosvoteviewurl");
				exit;
				//$reguser = $_W['siteroot'] .'app/'.$this->createMobileUrl('reguser', array('rid' => $rid));
				//header("location:$reguser");
				//exit;
			
		}
	
	}
	
	public function doMobileshareuserdata() {
		//关健词触发页面显示。
		global $_GPC,$_W;
		$uniacid = $_W['uniacid'];//当前公众号ID		
		$rid = $_GPC['rid'];
		$oauthuser = $this->FM_checkoauth();
			$from_user = $oauthuser['from_user'];
			$avatar = $oauthuser['avatar'];
			$nickname = $oauthuser['nickname'];
			$follow = $oauthuser['follow'];
		$isvisits = $_GPC['isvisits'];//是否互访
		$fromuser = $_GPC['fromuser'];
		$page_fromuser = $_GPC['fromuser'];
		$tfrom_user = $_GPC['tfrom_user'];
		//$from_user = $_COOKIE["user_oauth2_openid"];
		//$from_user = base64_encode(authcode($_COOKIE["user_oauth2_openid"], 'ENCODE'));
		//$this->checkoauth2($rid,$from_user, $page_fromuser);//查询是否有cookie信息
		$visitorsip = getip();
		$now = time();
		//活动规则
      	if (!empty($rid)) {
			$reply = pdo_fetch("SELECT * FROM ".tablename($this->table_reply)." WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $rid));$bgarr = iunserializer($reply['bgarr']);
		}
	
		//查询是否参与活动
		if(!empty($fromuser)) {
		    $usergift = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and from_user = :from_user and rid = :rid", array(':uniacid' => $uniacid,':from_user' => $fromuser,':rid' => $rid));
            $user_gift = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and from_user = :from_user and rid = :rid", array(':uniacid' => $uniacid,':from_user' => $from_user,':rid' => $rid));
            if(!empty($usergift)){
			    //添加分享人气记录
				if($fromuser!=$from_user){//自己不能给自己加人气
				    $sharedata = pdo_fetch("SELECT * FROM ".tablename($this->table_data)." WHERE uniacid = :uniacid and fromuser = :fromuser and rid = :rid and from_user = :from_user", array(':uniacid' => $uniacid,':fromuser' => $fromuser,':from_user' => $from_user,':rid' => $rid));
					if(empty($sharedata)){//一个朋友只加一次人气	
					    $insertdata = array(
		                    'uniacid'           => $uniacid,
		                    'tfrom_user'      => $tfrom_user,//分享的选手
							'fromuser'       => $fromuser,	//分享者
		                    'from_user'      => $from_user,//当前的用户
							'avatar'         => $avatar,                            
							'nickname'       => $nickname,
		                    'rid'            => $rid,
 		                    'uid'            => $usergift['id'],
		                    'visitorsip'	 => $visitorsip,
		                    'visitorstime'   => $now
		                ); 
						pdo_insert($this->table_data, $insertdata);
						$dataid = pdo_insertid();//取id
						//给分享人添加人气量
						$sharenum = pdo_fetchcolumn("SELECT COUNT(*) FROM ".tablename($this->table_data)." WHERE uniacid = :uniacid and fromuser = :fromuser and rid = :rid", array(':uniacid' => $uniacid,':fromuser' => $fromuser,':rid' => $rid));
						$updatelist = array(
		                    'sharenum'  => $sharenum,
		                    'sharetime' => $now
		                );
						pdo_update($this->table_users,$updatelist,array('id' => $usergift['id']));					
					    //是否为互访
						if($isvisits==1){
						    if (!empty($user_gift)){
							    pdo_update($this->table_data,array('isin' => 1),array('id' => $dataid));
							    if($reply['opensubscribe']<=1){
								    pdo_update($this->table_users,array('yaoqingnum' => $usergift['yaoqingnum']+1),array('id' => $usergift['id']));
							    }
							}else{
							    pdo_update($this->table_data,array('isin' => -1),array('id' => $dataid));
							}
						}else{
						    //查询是是否为参与活动人并第一次访问好友,如果是第一次为分享人添加邀请量					
					        if (!empty($user_gift)){
					            $one_user = pdo_fetchcolumn("SELECT COUNT(*) FROM ".tablename($this->table_data)." WHERE uniacid = :uniacid and from_user = :from_user and rid = :rid", array(':uniacid' => $uniacid,':from_user' => $from_user,':rid' => $rid));
						        if ($one_user==1){
						            pdo_update($this->table_data,array('isin' => 3),array('id' => $dataid));
							        if($reply['opensubscribe']<=3){
								        pdo_update($this->table_users,array('yaoqingnum' => $usergift['yaoqingnum']+1),array('id' => $usergift['id']));
								    }								
						        }else{
						            pdo_update($this->table_data,array('isin' => 2),array('id' => $dataid));
								    if($reply['opensubscribe']<=2){
								        pdo_update($this->table_users,array('yaoqingnum' => $usergift['yaoqingnum']+1),array('id' => $usergift['id']));
								    }
						        }
					        }else{
							    if($reply['opensubscribe']<=0){
								    pdo_update($this->table_users,array('yaoqingnum' => $usergift['yaoqingnum']+1),array('id' => $usergift['id']));
							    }
							}
					        //查询是是否为参与活动人并第一次访问好友,如果是第一次为分享人添加邀请量
						}
					}
				}
				
				
				
				//转分享人页
				if ($_GPC['duli'] == '1') {
					$gifturl = $_W['siteroot'] .'app/'.$this->createMobileUrl('Tuser', array('rid' => $rid,'fromuser' => $fromuser,'tfrom_user' => $tfrom_user));
				}elseif ($_GPC['duli'] == '2') {
					$gifturl = $_W['siteroot'] .'app/'.$this->createMobileUrl('Tuserphotos', array('rid' => $rid,'fromuser' => $fromuser,'tfrom_user' => $tfrom_user));					
				}else {
					$gifturl = $_W['siteroot'] .'app/'.$this->createMobileUrl('photosvoteview', array('rid' => $rid,'fromuser' => $fromuser));
				}
				
				
				header("location:$gifturl");
				exit;
			}else{
				$userdata = pdo_fetch("SELECT * FROM ".tablename($this->table_data)." WHERE uniacid = :uniacid and from_user = :from_user and tfrom_user = :tfrom_user and fromuser = :fromuser and rid = :rid", array(':uniacid' => $uniacid,':from_user' => $from_user,':tfrom_user' => $tfrom_user,':fromuser' => $fromuser,':rid' => $rid));
				if (empty($userdata)) {
					$insertdata = array(
						'uniacid'           => $uniacid,
						'tfrom_user'      => $tfrom_user,//分享的选手
						'fromuser'       => $fromuser,	//分享者
						'from_user'      => $from_user,//当前的用户
						'avatar'         => $avatar,                            
						'nickname'       => $nickname,
						'rid'            => $rid,
						'uid'            => $usergift['id'],
						'visitorsip'	 => $visitorsip,
						'visitorstime'   => $now
					); 
					pdo_insert($this->table_data, $insertdata);
				}
				
				
				
				if ($_GPC['duli'] == '1') {
					$mygifturl = $_W['siteroot'] .'app/'.$this->createMobileUrl('Tuser', array('rid' => $rid,'tfrom_user' => $tfrom_user,'fromuser' => $fromuser));
				}elseif ($_GPC['duli'] == '2') {
					$mygifturl = $_W['siteroot'] .'app/'.$this->createMobileUrl('Tuserphotos', array('rid' => $rid,'tfrom_user' => $tfrom_user,'fromuser' => $fromuser));					
				}else {
					$mygifturl = $_W['siteroot'] .'app/'.$this->createMobileUrl('photosvoteview', array('rid' => $rid));
				}
			    //转自己页
			    
				header("location:$mygifturl");
				exit;
			}
		}else{
		//分享人出错。一般不会出现
		}		
		
	}
	
	public function doMobileoauth2() {
		global $_GPC,$_W;
		$uniacid = $_W['uniacid'];//当前公众号ID
		$rid = $_GPC['rid'];
		load()->func('communication');
		$fromuser = authcode(base64_decode($_GPC['fromuser']), 'DECODE');
		$page_fromuser = $_GPC['fromuser'];
		$putonghao = authcode(base64_decode($_GPC['putonghao']), 'DECODE');	
		$serverapp = $_W['account']['level'];	//是否为高级号
		
		
		
		//借用还是本身为认证号
		if ($serverapp==4) {
		    $appid = $_W['account']['key'];
		    $secret = $_W['account']['secret'];
		}else{
		    $cfg = $this->module['config'];
			$appid = $cfg['appid'];
			$secret = $cfg['secret'];
		}
		
		
		//用户不授权返回提示说明
		if ($_GPC['code']=="authdeny"){
		    $url = $_W['siteroot'] .'app/'.$this->createMobileUrl('oauth2shouquan', array('rid' => $rid));
			header("location:$url");
			exit;
		}
		
		
		//高级接口取未关注用户Openid
		if (isset($_GPC['code'])){
		    //第二步：获得到了OpenID		    			
		    $code = $_GPC['code'];			
		    $oauth2_code = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=".$appid."&secret=".$secret."&code=".$code."&grant_type=authorization_code";
		    $content = ihttp_get($oauth2_code);
			//print_r($content);
			//exit;
		    $token = @json_decode($content['content'], true);
			if(empty($token) || !is_array($token) || empty($token['access_token']) || empty($token['openid'])) {
				echo '<h1>获取微信公众号授权'.$code.'失败[无法取得token以及openid], 请稍后重试！ 公众平台返回原始数据为: <br />' . $content['meta'].'<h1>';
				exit;
			}
		    $from_user = $token['openid'];
			$access_token = $token['access_token'];
			$oauth2_url = "https://api.weixin.qq.com/sns/userinfo?access_token=".$access_token."&openid=".$from_user."&lang=zh_CN";
			
			//使用全局ACCESS_TOKEN获取OpenID的详细信息			
			$content = ihttp_get($oauth2_url);
			$info = @json_decode($content['content'], true);
			if(empty($info) || !is_array($info) || empty($info['openid'])  || empty($info['nickname']) ) {
				echo '<h1>获取微信公众号授权失败[无法取得info], 请稍后重试！ 公众平台返回原始数据为: <br />' . $content['meta'].'<h1>';
				exit;
			}
		    $avatar = $info['headimgurl'];
		    $nickname = $info['nickname'];
			$from_user = base64_encode(authcode($from_user, 'ENCODE'));
		    //设置cookie信息
		    setcookie("user_oauth2_avatar", $avatar, time()+3600*24*7);
		    setcookie("user_oauth2_nickname", $nickname, time()+3600*24*7);
			setcookie("user_oauth2_openid", $from_user, time()+3600*24*7);
			if(!empty($fromuser) && !isset($_COOKIE["user_fromuser_openid"])){
			    setcookie("user_fromuser_openid", $fromuser, time()+3600*24*7*30);
			}
			
			
			if(!empty($putonghao)){
			    setcookie("user_putonghao_openid", $putonghao, time()+3600*24*7);
			}
			if(!empty($fromuser)){
			    $photosvoteviewurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('shareuserdata', array('rid' => $rid,'fromuser' => $page_fromuser,'duli' => $_GPC['duli'],'tfrom_user' => $_GPC['tfrom_user']));
			}else{
			    $photosvoteviewurl = $_W['siteroot'] .'app/'.$this->createMobileUrl('photosvoteview', array('rid' => $rid,'from_user' => $from_user));
			}		    
		    header("location:$photosvoteviewurl");
			exit;
		}else{
			echo '<h1>不是高级认证号或网页授权域名设置出错!</h1>';
			exit;		
		}
	
	}
	
	public function doMobileoauth2shouquan() {
	    global $_GPC,$_W;
		$uniacid = $_W['uniacid'];//当前公众号ID
		$rid = $_GPC['rid'];
		//活动规则
		if (!empty($rid)) {
			$reply = pdo_fetch("SELECT shareurl FROM ".tablename($this->table_reply)." WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $rid));
			$url = $reply['shareurl'];
	        header("location:$url");
			exit;
 		}
		
	}
	private function checkoauth2($rid,$oauth2, $page_fromuser = '') {//如果没有取得cookie信息	重新授权
        global $_W;
		load()->model('account');
		$uniacid = $_W['uniacid'];//当前公众号ID
		$serverapp = $_W['account']['level'];	//是否为高级号
		$cfg = $this->module['config'];
	    $appid = $cfg['appid'];
		$secret = $cfg['secret'];		
		if(empty($oauth2)){
		    if ($serverapp==4) {//高级号
			    $appid = $_W['account']['key'];
			    $url = $_W['siteroot'] .'app/'.$this->createMobileUrl('oauth2', array('rid' => $rid,'fromuser' => $page_fromuser));
			    $oauth2_code = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$appid."&redirect_uri=".urlencode($url)."&response_type=code&scope=snsapi_userinfo&state=0#wechat_redirect";				
			    header("location:$oauth2_code");
				exit;
			}else{
			    if(!empty($appid)){//有借用跳转授权页没有则跳转普通注册页
				    $url = $_W['siteroot'] .'app/'.$this->createMobileUrl('oauth2', array('rid' => $rid,'fromuser' => $page_fromuser));
				    $oauth2_code = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$appid."&redirect_uri=".urlencode($url)."&response_type=code&scope=snsapi_userinfo&state=0#wechat_redirect";				
				    header("location:$oauth2_code");
					exit;
				}else{
				    $reguser = $_W['siteroot'] .'app/'.$this->createMobileUrl('reguser', array('rid' => $rid));
				    header("location:$reguser");
					exit;
				}
			}
		}
	}
	
	public function doMobileCode() {
		global $_W,$_GPC;		
		$x = 120;
		$y = 28;
		$im = @imagecreatetruecolor($x, $y);
		if(empty($im)) {
			exit();
		}
		$trans = imagecolorallocatealpha($im, 255, 255, 255 , 127);
		imagecolortransparent($im, $trans);
		imagefilledrectangle($im, 0, 0, $x, $y, $trans);
		for($i = 0; $i < $x; $i++) {
			$p = 255 - $i;
			$line = imagecolorallocatealpha($im, $p, $p, $p, 0);
			imagefilledrectangle($im, $i, 0, $i + 1, $y, $line);
		}
		$letters = random(4, true);
		$hash = md5($letters . $_W['config']['setting']['authkey']);
		isetcookie('__code', $hash);
		$fontColor = imagecolorallocatealpha($im, 40, 40, 40, 0);
		$len = strlen($letters);
		for($i = 0; $i < $len; $i++) {
			imagestring($im, 5, 35 + $i * 15, 3 + rand(0, 10), $letters[$i], $fontColor);
		}
		header('content-type: image/png');
		imagepng($im);
		imagedestroy($im);

	
	}
	
	public function doWebIndex() {		
		global $_GPC, $_W;
		$uniacid = $_W['uniacid'];//当前公众号ID		
		$pindex = max(1, intval($_GPC['page']));
		$psize = 15;
		$list_praise = pdo_fetchall('SELECT * FROM '.tablename($this->table_reply).' WHERE uniacid= :uniacid order by `id` desc LIMIT ' . ($pindex - 1) * $psize . ',' . $psize, array(':uniacid' => $uniacid) );
		$pager = pagination($total, $pindex, $psize);
		
		if (!empty($list_praise)) {
			foreach ($list_praise as $mid => $list) {
				$count = pdo_fetch("SELECT count(id) as tprc FROM ".tablename($this->table_log)." WHERE rid= ".$list['rid']."");
				//$count1 = pdo_fetch("SELECT count(id) as share FROM ".tablename($this->table_log)." WHERE rid= ".$list['rid']." AND afrom_user != ''");
				$count1 = pdo_fetch("SELECT COUNT(id) as share FROM ".tablename($this->table_data)." WHERE uniacid = :uniacid and rid = :rid", array(':uniacid' => $uniacid,':rid' => $list['rid']));
				$count2 = pdo_fetch("SELECT count(id) as ysh FROM ".tablename($this->table_users)." WHERE rid= ".$list['rid']." AND status = '1' ");
				$count3 = pdo_fetch("SELECT count(id) as wsh FROM ".tablename($this->table_users)." WHERE rid= ".$list['rid']." AND status = '0' ");
				$count4 = pdo_fetch("SELECT count(id) as cyrs FROM ".tablename($this->table_users)." WHERE rid= ".$list['rid']."");
		        $list_praise[$mid]['user_tprc'] = $count['tprc'];//投票人次
		        $list_praise[$mid]['user_share'] = $count1['share'];//分享人数
		        $list_praise[$mid]['user_ysh'] = $count2['ysh'];//已审核
		        $list_praise[$mid]['user_wsh'] = $count3['wsh'];//未审核
		        $list_praise[$mid]['user_cyrs'] = $count4['cyrs'] + $list['xuninum'];//参与人数
				
				 $list_praise[$mid]['user_hits'] =   $list_praise[$mid]['user_cyrs'] +  $list['hits'] + pdo_fetchcolumn("SELECT sum(hits) FROM ".tablename($this->table_users)." WHERE rid= ".$list['rid']."") + pdo_fetchcolumn("SELECT sum(xnhits) FROM ".tablename($this->table_users)." WHERE rid= ".$list['rid']."");
				 //点击&参与
				//$count = pdo_fetch("SELECT count(id) as dd FROM ".tablename($this->table_data)." WHERE rid= ".$list['rid']."");
		       // $list_praise[$mid]['share_znum'] = $count['dd'];//分享人数
				
				$listpraise = pdo_fetchall('SELECT * FROM '.tablename($this->table_gift).' WHERE rid=:rid  order by `id`',array(':rid' => $list['rid']));
				if (!empty($listpraise)) {
			         $praiseinfo = '';
					 foreach ($listpraise as $row) {
					   $zigenum = pdo_fetchcolumn("SELECT COUNT(*) FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and rid = :rid and yaoqingnum>= :yaoqingnum", array(':uniacid' => $_W['uniacid'], ':rid' => $list['rid'], ':yaoqingnum' => $row['break']));
					   $praiseinfo = $praiseinfo.'奖品：'.$row['title'].'；总数为：'.$row['total'].'；已领奖品数为：'.$row['total_winning'].'；拥有奖品资格粉丝数：'.$zigenum.'；没有领取奖品粉丝数：'.($zigenum-$row['total_winning']).'；还剩：<b>'.($row['total']-$row['total_winning']).'</b>个奖品没有发放<br/>';
			        }
		        }
				$praiseinfo = substr($praiseinfo,0,strlen($praiseinfo)-5); 
				$list_praise[$mid]['praiseinfo'] = $praiseinfo;//奖品情况
			}
		}
		include $this->template('index');

	}
	
	 public function doWebDeleteAll() {
        global $_GPC, $_W;
        foreach ($_GPC['idArr'] as $k => $rid) {
            $rid = intval($rid);
            if ($rid == 0)
                continue;
            $rule = pdo_fetch("select id, module from " . tablename('rule') . " where id = :id and uniacid=:uniacid", array(':id' => $rid, ':uniacid' => $_W['uniacid']));
            if (empty($rule)) {
                $this->webmessage('抱歉，要修改的规则不存在或是已经被删除！');
            }
            if (pdo_delete('rule', array('id' => $rid))) {
                pdo_delete('rule_keyword', array('rid' => $rid));
                //删除统计相关数据
                pdo_delete('stat_rule', array('rid' => $rid));
                pdo_delete('stat_keyword', array('rid' => $rid));
                //调用模块中的删除
                $module = WeUtility::createModule($rule['module']);
                if (method_exists($module, 'ruleDeleted')) {
                    $module->ruleDeleted($rid);
                }
            }
        }
        $this->webmessage('选择中的活动删除成功！', '', 0);
    }
	
	public function doWebDelete() {
        global $_GPC, $_W;
        $rid = intval($_GPC['rid']);
        $rule = pdo_fetch("select id, module from " . tablename('rule') . " where id = :id and uniacid=:uniacid", array(':id' => $rid, ':uniacid' => $_W['uniacid']));
        if (empty($rule)) {
            message('抱歉，要修改的规则不存在或是已经被删除！');
        }
        if (pdo_delete('rule', array('id' => $rid))) {
            pdo_delete('rule_keyword', array('rid' => $rid));
            //删除统计相关数据
            pdo_delete('stat_rule', array('rid' => $rid));
            pdo_delete('stat_keyword', array('rid' => $rid));
            //调用模块中的删除
            $module = WeUtility::createModule($rule['module']);
            if (method_exists($module, 'ruleDeleted')) {
                $module->ruleDeleted($rid);
            }
        }
        message('活动删除成功！', referer(), 'success');
    }
	
	public function doWebMembers() {
		global $_GPC, $_W;
		checklogin();
		$uniacid = $_W['uniacid'];//当前公众号ID
		$rid = intval($_GPC['rid']);
		$reply = pdo_fetch('SELECT * FROM '.tablename($this->table_reply).' WHERE uniacid= :uniacid AND rid =:rid ', array(':uniacid' => $uniacid, ':rid' => $rid) );
		
		
			
			if (checksubmit('delete')) {
				pdo_delete($this->table_users, " id IN ('".implode("','", $_GPC['select'])."')");
				message('删除成功！', create_url('site/module', array('do' => 'members', 'name' => 'fm_photosvote', 'rid' => $rid, 'page' => $_GPC['page'], 'foo' => 'display')));
			}
			$where = '';
			//!empty($_GPC['keyword']) && $where .= " AND nickname LIKE '%{$_GPC['keywordnickname']}%'";
			if (!empty($_GPC['keyword'])) {
				$keyword = $_GPC['keyword'];
				
				$where .= " AND (id LIKE '%{$keyword}%' OR nickname LIKE '%{$keyword}%' OR mobile LIKE '%{$keyword}%' OR photoname LIKE '%{$keyword}%') ";					
				//$where .= " OR nickname LIKE '%{$keyword}%'";
				//$where .= " OR mobile LIKE '%{$keyword}%'";
				//$where .= " OR photoname LIKE '%{$keyword}%'";
			}
			!empty($rid) && $where .= " AND rid = '{$rid}'";

			$where .= " AND status = '1'";
			
			$pindex = max(1, intval($_GPC['page']));
			$psize = 15;

			//取得用户列表
			$members = pdo_fetchall('SELECT * FROM '.tablename($this->table_users).' WHERE uniacid= :uniacid '.$where.' order by `id` desc LIMIT ' . ($pindex - 1) * $psize . ',' . $psize, array(':uniacid' => $uniacid) );
			$total = pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_users).' WHERE uniacid= :uniacid '.$where.' ', array(':uniacid' => $uniacid));
			$pager = pagination($total, $pindex, $psize);
			$sharenum = array();
			foreach ($members as $mid => $m) {
				$sharenum[$mid] = pdo_fetchcolumn("SELECT COUNT(*) FROM ".tablename($this->table_data)." WHERE uniacid = :uniacid and tfrom_user = :tfrom_user and rid = :rid", array(':uniacid' => $uniacid,':tfrom_user' => $m['from_user'],':rid' => $rid));
			}
		
			include $this->template('members');

	}
	public function doWebDeletefans() {
        global $_GPC, $_W;
		$rid = intval($_GPC['rid']);
		$reply = pdo_fetch("select * from ".tablename($this->table_reply)." where rid = :rid and uniacid=:uniacid", array(':rid' => $rid, ':uniacid' => $_W['uniacid']));
        if (empty($reply)) {
            $this->webmessage('抱歉，要修改的活动不存在或是已经被删除！');
        }
		
		
        foreach ($_GPC['idArr'] as $k => $id) {
			
			
            $id = intval($id);
			
			
            if ($id == 0)
                continue;
			 
			$fans = pdo_fetch("select from_user from ".tablename($this->table_users)." where id = :id", array(':id' => $id));
            
			
			if (empty($fans)) {
                $this->webmessage('抱歉，选中的粉丝数据不存在！');
            }
			
			//删除粉丝参与记录
			pdo_delete($this->table_users, array('id' => $id));
			
        }
        $this->webmessage('粉丝记录删除成功！', '', 0);
    }
	
	public function doWebDeletemsg() {
        global $_GPC, $_W;
		$rid = intval($_GPC['rid']);
		$reply = pdo_fetch("select * from ".tablename($this->table_reply)." where rid = :rid and uniacid=:uniacid", array(':rid' => $rid, ':uniacid' => $_W['uniacid']));
        if (empty($reply)) {
            $this->webmessage('抱歉，要修改的活动不存在或是已经被删除！');
        }
		
		
        foreach ($_GPC['idArr'] as $k => $id) {
			
			
            $id = intval($id);
			
			
            if ($id == 0)
                continue;
			 
			$fans = pdo_fetch("select from_user from ".tablename($this->table_bbsreply)." where id = :id", array(':id' => $id));
            
			
			if (empty($fans)) {
                $this->webmessage('抱歉，选中的评论数据不存在！');
            }
			
			//删除粉丝参与记录
			pdo_delete($this->table_bbsreply, array('id' => $id));
			
        }
        $this->webmessage('评论记录删除成功！', '', 0);
    }
	public function doWebDeletevote() {
        global $_GPC, $_W;
		$rid = intval($_GPC['rid']);
		$reply = pdo_fetch("select * from ".tablename($this->table_reply)." where rid = :rid and uniacid=:uniacid", array(':rid' => $rid, ':uniacid' => $_W['uniacid']));
        if (empty($reply)) {
            $this->webmessage('抱歉，要修改的活动不存在或是已经被删除！');
        }
		
        foreach ($_GPC['idArr'] as $k => $id) {
			
			
            $id = intval($id);
			
			
            if ($id == 0)
                continue;
			 
			$fans = pdo_fetch("select * from ".tablename($this->table_log)." where id = :id", array(':id' => $id));
            $tfans = pdo_fetch('SELECT * FROM '.tablename($this->table_users).' WHERE uniacid= :uniacid AND rid =:rid AND from_user =:from_user ', array(':uniacid' => $_W['uniacid'], ':rid' => $rid, ':from_user' => $fans['tfrom_user']) );
			
			if (empty($fans)) {
                $this->webmessage('抱歉，选中的投票数据不存在！');
            }
			
			//删除粉丝参与记录
			pdo_delete($this->table_log, array('id' => $id));
			//更新粉丝数据
			pdo_update($this->table_users, array(
						'photosnum' => $tfans['photosnum'] - 1,
						'hits' => $tfans['hits'] - 1,
						),
						array('from_user' => $fans['tfrom_user'], 'rid' => $rid)
					);
			
        }
        $this->webmessage('投票记录删除成功！', '', 0);
    }
	public function doWebProvevote() {
		global $_GPC, $_W;
		checklogin();
		$uniacid = $_W['uniacid'];//当前公众号ID
		$rid = intval($_GPC['rid']);
		load()->func('tpl');
		$reply = pdo_fetch('SELECT * FROM '.tablename($this->table_reply).' WHERE uniacid= :uniacid AND rid =:rid ', array(':uniacid' => $uniacid, ':rid' => $rid) );
		$foo = !empty($_GPC['foo']) ? $_GPC['foo'] : 'display';		
		if ($foo == 'display') {
		
			if ($_GPC['sh'] == 1) {
				$status = intval($_GPC['status']);
				$from_user = $_GPC['from_user'];
				$now = time();
				pdo_update($this->table_users, array('status' => $status, 'lasttime' => $now), array('from_user' => $from_user, 'rid' => $rid));
				$this->sendMobileHsMsg($from_user, $rid, $uniacid);
				message('审核通过成功！',referer(),'success');
			}
			if (checksubmit('delete')) {
				pdo_delete($this->table_users, " id IN ('".implode("','", $_GPC['select'])."')");
				message('删除成功！', create_url('site/module', array('do' => 'Provevote', 'name' => 'fm_photosvote', 'rid' => $rid, 'page' => $_GPC['page'], 'foo' => 'display')));
			}
			$where = '';
			//!empty($_GPC['keywordnickname']) && $where .= " AND nickname LIKE '%{$_GPC['keywordnickname']}%'";
			if (!empty($_GPC['keyword'])) {
				$keyword = $_GPC['keyword'];
				if (is_numeric($keyword)) 
					$where .= " AND id = '".$keyword."'";
				else 				
					$where .= " AND nickname LIKE '%{$keyword}%'";
				
			}
			
			!empty($_GPC['keywordid']) && $where .= " AND rid = '{$_GPC['keywordid']}'";
			!empty($rid) && $where .= " AND rid = '{$rid}'";

			$where .= " AND status = '0'";
			
			$pindex = max(1, intval($_GPC['page']));
			$psize = 15;

			//取得用户列表
			$list_praise = pdo_fetchall('SELECT * FROM '.tablename($this->table_users).' WHERE uniacid= :uniacid '.$where.' order by `id` desc LIMIT ' . ($pindex - 1) * $psize . ',' . $psize, array(':uniacid' => $uniacid) );
			$total = pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_users).' WHERE uniacid= :uniacid '.$where.' ', array(':uniacid' => $uniacid));
			$pager = pagination($total, $pindex, $psize);
			//include $this->template('provevote');
		} elseif ($foo == 'post') {
					
			$from_user = $_GPC['from_user'];
			
			if (!empty($rid) && !empty($from_user)) {
				$item = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE rid = :rid AND from_user = :from_user" , array(':rid' => $rid, ':from_user' => $from_user));
				if (empty($item)) {
					message('抱歉，报名人不存在或是已经删除！', '', 'error');
				}
			}
			
			if ($item['picarr']) {
				$picarr = iunserializer($item['picarr']);
			}else {
				$pacarr = array();
				for ($i = 1; $i <= $reply['tpxz']; $i++) {
					$n = $i - 1;
					$picarr[$n] .= $item['picarr_'.$i];				
				}
			}
			
			
			
			if (checksubmit('fileupload-delete')) {
				file_delete($_GPC['fileupload-delete']);
				pdo_update($this->table_users, array('photo' => ''), array('rid' => $rid, 'from_user' => $from_user));
				message('删除成功！', referer(), 'success');
			}
			if (checksubmit('submit')) {
				if (empty($_GPC['photoname'])) {
					message('名字不能为空，请输入名字！');
				}
				/**
				// code provided and updated by steve of phpsnaps ! thanks
				// accepts:
				// 1: the input video file
				// 2: path to thumb jpg
				// 3: path to transcoded mpeg?
				function flv_convert_get_thumb($in, $out_thumb, $out_vid) {
				  // get thumbnail
				  $cmd = 'ffmpeg -v 0 -y -i '.$in.' -vframes 1 -ss 5 -vcodec mjpeg -f rawvideo -s 286x160 -aspect 16:9 '.$out_thumb;
				  $res = shell_exec($cmd);
				  // $res is the output of the command
				  // transcode video
				  $cmd = 'mencoder '.$in.' -o '.$out_vid.' -af volume=10 -aspect 16:9 -of avi -noodml -ovc x264 -x264encopts bitrate=500:level_idc=41:bframes=3:frameref=2: nopsnr: nossim: pass=1: threads=auto -oac mp3lame';
				  $res = shell_exec($cmd);
				}
				$mp4 = flv_convert_get_thumb($_GPC['vedio'], 'output.jpg', 'output.ogm');
				print_r($mp4);
				exit;**/
				$data = array(
					'uniacid' => $_W['uniacid'],
					'photoname' => $_GPC['photoname'],
					'avatar' => $_GPC['avatar'],
					'realname'  => $_GPC["realname"],
					'mobile'  => $_GPC["mobile"],
					'weixin'  => $_GPC["weixin"],
					'qqhao'  => $_GPC["qqhao"],
					'email'  => $_GPC["email"],
					'address'  => $_GPC["address"],
					'photosnum' => intval($_GPC['photosnum']),
					'xnphotosnum' => intval($_GPC['xnphotosnum']),
					'hits' => intval($_GPC['hits']),
					'xnhits' => intval($_GPC['xnhits']),
					'photo' => $_GPC['photo'],
					'music' => $_GPC['music'],
					'voice' => $_GPC['voice'],
					'vedio' => $_GPC['vedio'],
					'status' => intval($_GPC['status']),
					'description' => htmlspecialchars_decode($_GPC['description']),
					
				);
				
				//多图上传
				
				
				$picarrTmp = array();
				for ($i = 1; $i <= $reply['tpxz']; $i++) {
					$picarrTmp[] .= $_GPC['picarr_'.$i];	
				}
				$data['picarr'] = iserializer($picarrTmp);
				
				/**if (!empty($_GPC['photo'])) {
					$data['photo'] = $_GPC['photo'];
					file_delete($_GPC['photo-old']);
				} elseif (!empty($_GPC['autolitpic'])) {
					$match = array();
					preg_match('/attachment\/(.*?)(\.gif|\.jpg|\.png|\.bmp)/', $_GPC['content'], $match);
					if (!empty($match[1])) {
						$data['photo'] = $match[1].$match[2];
					}
				}**/
				///if (empty($id)) {
				//	pdo_insert($this->table_users, $data);
				//} else {
					//unset($data['createtime']);
					pdo_update($this->table_users, $data, array('rid' => $rid, 'from_user' => $from_user));
				//}
				if ($_GPC['member'] == '1') {
					message('报名用户更新成功！', $this->createWebUrl('members', array('rid' => $rid, 'foo' => 'display')), 'success');
				}else {
					message('报名用户更新成功！', $this->createWebUrl('provevote', array('rid' => $rid, 'foo' => 'display')), 'success');
				}
				
				
			}
			//include $this->template('provevote_post');
		}
		
		include $this->template('provevote');

	}
	
	public function doWebAddProvevote() {
		global $_GPC, $_W;
		checklogin();
		$uniacid = $_W['uniacid'];//当前公众号ID
		$rid = intval($_GPC['rid']);
		load()->func('tpl');
		$reply = pdo_fetch('SELECT * FROM '.tablename($this->table_reply).' WHERE uniacid= :uniacid AND rid =:rid ', array(':uniacid' => $uniacid, ':rid' => $rid) );
		//$item = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE rid = :rid AND from_user = :from_user" , array(':rid' => $rid, ':from_user' => $from_user));
			if (checksubmit('submit')) {
				
				$now = time();
				if (empty($_GPC['photoname'])) {
					message('照片主题名没有填写！');
				}
				/**if (empty($_GPC['description'])) {
					message('介绍没有填写');
				}
				if (empty($_GPC['realname'])) {
					message('您的真实姓名没有填写，请填写！');
				}
				if(!preg_match(REGULAR_MOBILE, $_GPC['mobile'])) {
					message('必须输入手机号，格式为 11 位数字。');
				}
									
				$realname = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and realname = :realname and rid = :rid", array(':uniacid' => $uniacid,':realname' => $_GPC['realname'],':rid' => $rid));
				if (!empty($realname)) {
					message('您的真实姓名已经参赛，请重新填写！');
				}
				$ymobile = pdo_fetch("SELECT * FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and mobile = :mobile and rid = :rid", array(':uniacid' => $uniacid,':mobile' => $_GPC['mobile'],':rid' => $rid));
				if(!empty($ymobile)) {
					message('非常抱歉，此手机号码已经被注册，你需要更换注册手机号！');
				}
				**/
				$insertdata = array(
					'rid'       => $rid,
					'uniacid'      => $uniacid,
					'from_user' => random(16).$now,
					'avatar'    => $_GPC["avatar"],
					'nickname'  => $_GPC["realname"],	    
					'photo'  => $_GPC["photo"],	
					'music' => $_GPC['music'],
					'vedio' => $_GPC['vedio'],	
					'description'  => $_GPC["description"],
					'photoname'  => $_GPC["photoname"],
					'realname'  => $_GPC["realname"],
					'mobile'  => $_GPC["mobile"],
					'weixin'  => $_GPC["weixin"],
					'qqhao'  => $_GPC["qqhao"],
					'email'  => $_GPC["email"],
					'address'  => $_GPC["address"],
					'photosnum' => '0',
					'xnphotosnum' => intval($_GPC['xnphotosnum']),	    
					'hits'  => '1',
					'xnhits'  =>  intval($_GPC['xnhits']),
					'yaoqingnum'  => '0',
					'sharenum'  => '0',
					'createip' => getip(),
					'lastip' => getip(),
					'status'  =>intval($_GPC['status']),
					'sharetime' =>'',
					'createtime'  => $now,
				);
				//多图上传
				$picarrTmp = array();
				for ($i = 1; $i <= $reply['tpxz']; $i++) {
					$picarrTmp[] .= $_GPC['picarr_'.$i];	
				}
				$insertdata['picarr'] = iserializer($picarrTmp);
				
				pdo_insert($this->table_users, $insertdata);
				message('报名用户添加成功！', $this->createWebUrl('members', array('rid' => $rid, 'foo' => 'display')), 'success');
				
			}
		
		include $this->template('addprovevote');

	}
	
	public function doWebVotelog() {
		global $_GPC, $_W;
		checklogin();
		$uniacid = $_W['uniacid'];//当前公众号ID
		$rid = intval($_GPC['rid']);
		$afrom_user = $_GPC['afrom_user'];
		$tfrom_user = $_GPC['tfrom_user'];
		load()->model('mc');
		$Where = "";
		if (!empty($tfrom_user)){
		$Where .= " AND `tfrom_user` = '{$tfrom_user}'";		
		}
		if (!empty($afrom_user)){
			$Where .= " AND `afrom_user` = '{$afrom_user}'";		
		}
		if (!empty($rid)){
			$Where .= " AND `rid` = $rid";		
		}

		$pindex = max(1, intval($_GPC['page']));
		$psize = 15;

		//取得分享点击详细数据
		$votelogs = pdo_fetchall('SELECT * FROM '.tablename($this->table_log).' WHERE uniacid= :uniacid '.$Where.'  order by `createtime` desc LIMIT ' . ($pindex - 1) * $psize . ',' . $psize, array(':uniacid' => $uniacid) );
		
		//查询分享人姓名电话结束
		$total = pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_log).' WHERE uniacid= :uniacid '.$Where.'  order by `createtime` desc ', array(':uniacid' => $uniacid));
		$pager = pagination($total, $pindex, $psize);
		include $this->template('votelog');

	}
	
	public function doWebMessage() {
		global $_GPC, $_W;
		checklogin();
		$uniacid = $_W['uniacid'];//当前公众号ID
		$rid = intval($_GPC['rid']);
		$afrom_user = $_GPC['afrom_user'];
		$tfrom_user = $_GPC['tfrom_user'];
		
		$keyword = $_GPC['keyword'];
		
		$Where = "";
		if (!empty($keyword)){
			
			$Where .= " AND content LIKE '%{$keyword}%' OR nickname LIKE '%{$keyword}%'";	
			
			$Where .= " OR ip LIKE '%{$keyword}%'";	
			
		}
		if (!empty($tfrom_user)){
		$Where .= " AND `tfrom_user` = '{$tfrom_user}'";		
		}
		if (!empty($afrom_user)){
			$Where .= " AND `afrom_user` = '{$afrom_user}'";		
		}
		if (!empty($rid)){
			$Where .= " AND `rid` = $rid";		
		}

		$pindex = max(1, intval($_GPC['page']));
		$psize = 15;

		//取得分享点击详细数据
		$messages = pdo_fetchall('SELECT * FROM '.tablename($this->table_bbsreply).' WHERE uniacid= :uniacid '.$Where.'  order by `createtime` desc LIMIT ' . ($pindex - 1) * $psize . ',' . $psize, array(':uniacid' => $uniacid) );
		
		//查询分享人姓名电话结束
		$total = pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_bbsreply).' WHERE uniacid= :uniacid '.$Where.'  order by `createtime` desc ', array(':uniacid' => $uniacid));
		$pager = pagination($total, $pindex, $psize);
		include $this->template('message');

	}
	
	public function doWebAnnounce() {
		global $_GPC, $_W;
		checklogin();
		$uniacid = $_W['uniacid'];//当前公众号ID
		$rid = intval($_GPC['rid']);
		$afrom_user = $_GPC['afrom_user'];
		$tfrom_user = $_GPC['tfrom_user'];
		
				
				
		if (checksubmit('submit')) {
		if (!empty($_GPC['content'])) {
			foreach ($_GPC['content'] as $index => $row) {
				$data = array(
					'content' => $_GPC['content'][$index],
					'rid' => $rid,
					'createtime' => time(),
				);
				if (!empty($_GPC['nickname'][$index])) {
					$data['nickname'] = $_GPC['nickname'][$index];
				}
				if (!empty($_GPC['url'][$index])) {
					$data['url'] = $_GPC['url'][$index];
				}
				if(!empty($data['content'])) {
					if(pdo_fetch("SELECT id FROM ".tablename($this->table_announce)." WHERE content = :content AND id != :id", array(':content' => $data['content'], ':id' => $index))) {
						continue;
					}
					if(pdo_fetch("SELECT id FROM ".tablename($this->table_announce)." WHERE nickname = :nickname AND id != :id", array(':nickname' => $data['nickname'], ':id' => $index))) {
						continue;
					}
					if(pdo_fetch("SELECT id FROM ".tablename($this->table_announce)." WHERE url = :url AND id != :id", array(':url' => $data['url'], ':id' => $index))) {
						continue;
					}
					$row = pdo_fetch("SELECT id FROM ".tablename($this->table_announce)." WHERE content = :content AND nickname = :nickname AND url = :url  AND rid = :rid  LIMIT 1",array(':content' => $data['content'],':nickname' => $data['nickname'],':url' => $data['url'],':rid' => $rid));
					if(empty($row)) {
						pdo_update($this->table_announce, $data, array('id' => $index));
					}
					unset($row);
				}
			}
		}
		
		if (!empty($_GPC['content-new'])) {
			foreach ($_GPC['content-new'] as $index => $row) {
				$data = array(
						'uniacid' => $_W['uniacid'],
						'rid' => $rid,
						'content' => $_GPC['content-new'][$index],
						'nickname' => $_GPC['nickname-new'][$index],
						'url' => $_GPC['url-new'][$index],
						'createtime' => time(),
				);
				if(!empty($data['content']) && !empty($data['nickname'])) {
					if(pdo_fetch("SELECT id FROM ".tablename($this->table_announce)." WHERE content = :content", array(':content' => $data['content']))) {
						continue;
					}
					pdo_insert($this->table_announce, $data);
					unset($row);
				}
			}
		}
		
		if (!empty($_GPC['delete'])) {
			pdo_query("DELETE FROM ".tablename($this->table_announce)." WHERE id IN (".implode(',', $_GPC['delete']).")");
		}

		message('更新成功！', referer(), 'success');
	}
	$list = pdo_fetchall("SELECT * FROM ".tablename($this->table_announce)." WHERE uniacid = :uniacid AND rid = :rid", array(':uniacid' => $_W['uniacid'], ':rid' => $rid));
		
		include $this->template('announce');

	}
	public function doWebAddMessage() {
		global $_GPC, $_W;
		checklogin();
		$uniacid = $_W['uniacid'];//当前公众号ID
		$rid = intval($_GPC['rid']);
		$afrom_user = $_GPC['afrom_user'];
		$tfrom_user = $_GPC['tfrom_user'];
		
				
				
		if (checksubmit('submit')) {
		if (!empty($_GPC['content'])) {
			foreach ($_GPC['content'] as $index => $row) {
				$data = array(
					'content' => $_GPC['content'][$index],
					'rid' => $rid,
					'status' => '9',
					'createtime' => time(),
				);
				if (!empty($_GPC['nickname'][$index])) {
					$data['nickname'] = $_GPC['nickname'][$index];
				}
				if(!empty($data['content'])) {
					if(pdo_fetch("SELECT id FROM ".tablename($this->table_bbsreply)." WHERE content = :content AND id != :id", array(':content' => $data['content'], ':id' => $index))) {
						continue;
					}
					if(pdo_fetch("SELECT id FROM ".tablename($this->table_bbsreply)." WHERE nickname = :nickname AND id != :id", array(':nickname' => $data['nickname'], ':id' => $index))) {
						continue;
					}
					$row = pdo_fetch("SELECT id FROM ".tablename($this->table_bbsreply)." WHERE content = :content AND nickname = :nickname  AND rid = :rid   AND status = :status LIMIT 1",array(':content' => $data['content'],':nickname' => $data['nickname'],':rid' => $rid,':status' => '9'));
					if(empty($row)) {
						pdo_update($this->table_bbsreply, $data, array('id' => $index));
					}
					unset($row);
				}
			}
		}
		
		if (!empty($_GPC['content-new'])) {
			foreach ($_GPC['content-new'] as $index => $row) {
				$data = array(
						'uniacid' => $_W['uniacid'],
						'rid' => $rid,
						'content' => $_GPC['content-new'][$index],
						'nickname' => $_GPC['nickname-new'][$index],
						'status' => '9',
						'createtime' => time(),
				);
				if(!empty($data['content']) && !empty($data['nickname'])) {
					if(pdo_fetch("SELECT id FROM ".tablename($this->table_bbsreply)." WHERE content = :content", array(':content' => $data['content']))) {
						continue;
					}
					pdo_insert($this->table_bbsreply, $data);
					unset($row);
				}
			}
		}
		
		if (!empty($_GPC['delete'])) {
			pdo_query("DELETE FROM ".tablename($this->table_bbsreply)." WHERE id IN (".implode(',', $_GPC['delete']).")");
		}

		message('更新成功！', referer(), 'success');
	}
	$status = '9';
	$list = pdo_fetchall("SELECT * FROM ".tablename($this->table_bbsreply)." WHERE uniacid = :uniacid AND rid = :rid AND status = :status", array(':uniacid' => $_W['uniacid'], ':rid' => $rid, ':status' => $status));
		
		
		
		include $this->template('addmessage');

	}
	
	public function doWebIplist() {
		global $_GPC, $_W;
		checklogin();
		
		$uniacid = $_W['uniacid'];//当前公众号ID
		$rid = intval($_GPC['rid']);
		$reply = pdo_fetch('SELECT * FROM '.tablename($this->table_reply).' WHERE uniacid= :uniacid AND rid =:rid ', array(':uniacid' => $uniacid, ':rid' => $rid) );
		$foo = !empty($_GPC['foo']) ? $_GPC['foo'] : 'post';		
		if ($foo == 'display') {	
			
				$vote = pdo_fetchall("SELECT distinct(ip) FROM ".tablename($this->table_log)." WHERE uniacid = :uniacid AND rid = :rid  ", array(':uniacid' => $_W['uniacid'], ':rid' => $rid));
				$tvtotal = array();
				foreach ($vote as $v) {
					$total = pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_log).' WHERE uniacid= :uniacid  AND rid= :rid AND ip = :ip order by `ip` desc ', array(':uniacid' => $uniacid, ':rid' => $rid, ':ip' => $v['ip']));
					$tvtotal[$v[ip]] .= $total;
					
				}
				arsort($tvtotal);
				
			
		}elseif ($foo == 'post') {			
			$pindex = max(1, intval($_GPC['page']));
			$psize = 15;

			//取得ip详细数据
			$iplist = pdo_fetchall('SELECT * FROM '.tablename($this->table_iplist).' WHERE uniacid= :uniacid  AND  rid= :rid order by `createtime` desc LIMIT ' . ($pindex - 1) * $psize . ',' . $psize, array(':uniacid' => $uniacid, ':rid' => $rid));
			//$iparr = iunserializer($item['iparr']);
			
			
			$total = pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_iplist).' WHERE uniacid= :uniacid  AND rid= :rid  order by `createtime` desc ', array(':uniacid' => $uniacid, ':rid' => $rid));
			$pager = pagination($total, $pindex, $psize);
			
			if (checksubmit('submit')) {
				if (!empty($_GPC['ipstart'])) {					
					foreach ($_GPC['ipstart'] as $index => $row) {
						$iparr = array(
								'ipstart' =>$_GPC['ipstart'][$index],
								'ipend' =>$_GPC['ipend'][$index]
								);
						$data = array(
							'iparr' =>iserializer($iparr),
							'rid' => $rid,
							'createtime' => time(),
						);
						if (!empty($_GPC['ipadd'][$index])) {
							$data['ipadd'] = $_GPC['ipadd'][$index];
						}
						if(!empty($data['iparr'])) {
							if(pdo_fetch("SELECT id FROM ".tablename($this->table_iplist)." WHERE iparr = :iparr AND id != :id", array(':iparr' => $data['iparr'], ':id' => $index))) {
								continue;
							}
							if(pdo_fetch("SELECT id FROM ".tablename($this->table_iplist)." WHERE ipadd = :ipadd AND id != :id", array(':ipadd' => $data['ipadd'], ':id' => $index))) {
								continue;
							}
							$row = pdo_fetch("SELECT id FROM ".tablename($this->table_iplist)." WHERE iparr = :iparr AND ipadd = :ipadd  AND rid = :rid   LIMIT 1",array(':iparr' => $data['iparr'],':ipadd' => $data['ipadd'],':rid' => $rid));
							if(empty($row)) {
								pdo_update($this->table_iplist, $data, array('id' => $index));
							}
							unset($row);
						}
					}
				}
				if (!empty($_GPC['ipstart-new'])) {
					foreach ($_GPC['ipstart-new'] as $index => $row) {
						$iparr = array(
								'ipstart' =>$_GPC['ipstart-new'][$index],
								'ipend' =>$_GPC['ipend-new'][$index]
								);
						$data = array(
								'uniacid' => $_W['uniacid'],
								'rid' => $rid,
								'iparr' =>iserializer($iparr),
								'ipadd' => $_GPC['ipadd-new'][$index],
								'createtime' => time(),
						);
						if(!empty($data['iparr']) && !empty($data['ipadd'])) {
							if(pdo_fetch("SELECT id FROM ".tablename($this->table_iplist)." WHERE iparr = :iparr", array(':iparr' => $data['iparr']))) {
								continue;
							}
							if(pdo_fetch("SELECT id FROM ".tablename($this->table_iplist)." WHERE ipadd = :ipadd", array(':ipadd' => $data['ipadd']))) {
								continue;
							}
							pdo_insert($this->table_iplist, $data);
							unset($row);
						}
					}
				}
				
				if (!empty($_GPC['delete'])) {
					pdo_query("DELETE FROM ".tablename($this->table_iplist)." WHERE id IN (".implode(',', $_GPC['delete']).")");
				}

				message('更新成功！', referer(), 'success');
			}
		
		}
		
		
		
		
		include $this->template('iplist');

	}
	
	
	public function doWebdeletealllog() {
		global $_GPC;
		$rid = $_GPC['rid'];
		if (!empty($rid)) {
			pdo_delete($this->table_log, " rid = ".$rid);
			message('删除成功！', referer(),'success');
		}		
		
	}
	public function doWebdeleteallmessage() {
		global $_GPC;
		$rid = $_GPC['rid'];
		if (!empty($rid)) {
			pdo_delete($this->table_bbsreply, " rid = ".$rid);
			message('删除成功！', referer(),'success');
		}		
		
	}
	public function _getuser($rid, $tfrom_user) {
		global $_GPC, $_W;
		return pdo_fetch("SELECT avatar, nickname FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and  rid = :rid and from_user = :tfrom_user ", array(':uniacid' => $_W['uniacid'], ':rid' => $rid, ':tfrom_user' => $tfrom_user));
	}
	public function _auser($rid, $afrom_user) {
		global $_GPC, $_W;
		load()->model('mc');
		$auser = pdo_fetch("SELECT avatar, nickname FROM ".tablename($this->table_users)." WHERE uniacid = :uniacid and  rid = :rid and from_user = :afrom_user ", array(':uniacid' => $_W['uniacid'], ':rid' => $rid, ':afrom_user' => $afrom_user));
		if (empty($auser)) {
			$auser = pdo_fetch("SELECT avatar, nickname FROM ".tablename($this->table_data)." WHERE uniacid = :uniacid and  rid = :rid and from_user = :afrom_user ", array(':uniacid' => $_W['uniacid'], ':rid' => $rid, ':afrom_user' => $afrom_user));
			if (empty($auser)) {
				$auser = mc_fansinfo($row['afrom_user']);
			}
		}	
		return $auser;
	}
	
	
	public function doWebRankinglist() {		
		global $_GPC, $_W;
		checklogin();
		$uniacid = $_W['uniacid'];//当前公众号ID
		$rid = intval($_GPC['rid']);
		$indexpx = intval($_GPC['indexpx']);
		$indexpxf = intval($_GPC['indexpxf']);
		if (empty($page)){$page = 1;}
		$where = '';
		!empty($_GPC['keywordnickname']) && $where .= " AND nickname LIKE '%{$_GPC['keywordnickname']}%'";
		!empty($_GPC['keywordid']) && $where .= " AND rid = '{$_GPC['keywordid']}'";
		!empty($rid) && $where .= " AND rid = '{$rid}'";

		
		$pindex = max(1, intval($_GPC['page']));
		$psize = 15;
		$order = '';
		//0 按最新排序 1 按人气排序 3 按投票数排序
		if ($indexpx == '-1') {
			$order .= " `createtime` DESC";
		}elseif ($indexpx == '1') {
			$order .= " `hits` + `xnhits` DESC";
		}elseif ($indexpx == '2') {
			$order .= " `photosnum` + `xnphotosnum` DESC";
		}
		
		//0 按最新排序 1 按人气排序 3 按投票数排序  倒叙
		if ($indexpxf == '-1') {
			$order .= " `createtime` ASC";
		}elseif ($indexpxf == '1') {
			$order .= " `hits` + `xnhits` ASC";
		}elseif ($indexpxf == '2') {
			$order .= " `photosnum` + `xnphotosnum` ASC";
		}
		
		if (empty($indexpx) && empty($indexpxf)) {
			$order .= " `createtime` DESC";
		}
		
		
		//取得用户列表
		$list_praise = pdo_fetchall('SELECT * FROM '.tablename($this->table_users).' WHERE uniacid= :uniacid '.$where.' order by '.$order.' LIMIT ' . ($pindex - 1) * $psize . ',' . $psize, array(':uniacid' => $uniacid) );
		$total = pdo_fetchcolumn('SELECT COUNT(*) FROM '.tablename($this->table_users).' WHERE uniacid= :uniacid '.$where.' ', array(':uniacid' => $uniacid));
		$pager = pagination($total, $pindex, $psize);
		include $this->template('rankinglist');

	}
	
	public function doWebstatus() {
		global $_GPC;
		$rid = intval($_GPC['rid']);
		//echo $rid;
		$insert = array(
			'status' => intval($_GPC['status'])
		);
		
		pdo_update($this->table_reply,$insert,array('rid' => $rid));
		if ($_GPC['status'] == 1) {
			$msg = '开启活动成功！';
		} else {
			$msg = '暂停活动成功！';
		}
		message($msg, referer(), 'success');
	}
	
	public function doWebBanner() {
        global $_W, $_GPC;
		$rid= $_GPC['rid'];
		load()->func('tpl');
        $operation = !empty($_GPC['op']) ? $_GPC['op'] : 'display';
        if ($operation == 'display') {
            $list = pdo_fetchall("SELECT * FROM " . tablename($this->table_banners) . " WHERE uniacid = '{$_W['uniacid']}' AND rid = '{$rid}' ORDER BY displayorder DESC");
		//	include $this->template('banner');
        } elseif ($operation == 'post') {

            $id = intval($_GPC['id']);
            if (checksubmit('submit')) {
                $data = array(
                    'uniacid' => $_W['uniacid'],
                    'rid' => $rid,
                    'bannername' => $_GPC['bannername'],
                    'link' => $_GPC['link'],
                    'thumb' => $_GPC['thumb'],
                    'enabled' => intval($_GPC['enabled']),
                    'displayorder' => intval($_GPC['displayorder'])
                );
               
                if (!empty($id)) {
                    pdo_update($this->table_banners, $data, array('id' => $id));
					load()->func('file');
					file_delete($_GPC['thumb_old']);
                } else {
                    pdo_insert($this->table_banners, $data);
                    $id = pdo_insertid();
                }
                message('更新幻灯片成功！', $this->createWebUrl('banner', array('op' => 'display', 'rid' => $rid)), 'success');
            }
            $banner = pdo_fetch("select * from " . tablename($this->table_banners) . " where id=:id and uniacid=:uniacid and rid=:rid limit 1", array(":id" => $id, ":uniacid" => $_W['uniacid'], ':rid' => $rid));
			//include $this->template('banner_post');
        } elseif ($operation == 'delete') {
            $id = intval($_GPC['id']);
            $banner = pdo_fetch("SELECT id  FROM " . tablename($this->table_banners) . " WHERE id = '$id' AND uniacid=" . $_W['uniacid'] . " AND rid=" . $rid . "");
            if (empty($banner)) {
                message('抱歉，幻灯片不存在或是已经被删除！', $this->createWebUrl('banner', array('op' => 'display', 'rid' => $rid)), 'error');
            }
            pdo_delete($this->table_banners, array('id' => $id));
            message('幻灯片删除成功！', $this->createWebUrl('banner', array('op' => 'display', 'rid' => $rid)), 'success');
        } else {
            message('请求方式不存在');
        }
		include $this->template('banner');
    }

	public function doWebAdv() {
        global $_W, $_GPC;
		$rid= $_GPC['rid'];
		load()->func('tpl');
        $operation = !empty($_GPC['op']) ? $_GPC['op'] : 'display';
        if ($operation == 'display') {
            $list = pdo_fetchall("SELECT * FROM " . tablename($this->table_advs) . " WHERE uniacid = '{$_W['uniacid']}' AND rid = '{$rid}' ORDER BY displayorder DESC");
			//include $this->template('adv');
        } elseif ($operation == 'post') {
			
            $id = intval($_GPC['id']);
            if (checksubmit('submit')) {
				//exit;
                $data = array(
                    'uniacid' => $_W['uniacid'],
                    'rid' => $rid,
                    'advname' => $_GPC['advname'],
                    'link' => $_GPC['link'],
                    'thumb' => $_GPC['thumb'],
                    'enabled' => intval($_GPC['enabled']),
                    'displayorder' => intval($_GPC['displayorder'])
                );
                if (!empty($id)) {
                    pdo_update($this->table_advs, $data, array('id' => $id));
					load()->func('file');
					file_delete($_GPC['thumb_old']);
                } else {
                    pdo_insert($this->table_advs, $data);
                    $id = pdo_insertid();
                }
                message('更新广告成功！', $this->createWebUrl('adv', array('op' => 'display', 'rid' => $rid)), 'success');
            }
            $adv = pdo_fetch("select * from " . tablename($this->table_advs) . " where id=:id and uniacid=:uniacid and rid=:rid limit 1", array(":id" => $id, ":uniacid" => $_W['uniacid'], ':rid' => $rid));
			//include $this->template('adv_post');
	   } elseif ($operation == 'delete') {
            $id = intval($_GPC['id']);
            $adv = pdo_fetch("SELECT id  FROM " . tablename($this->table_advs) . " WHERE id = '$id' AND uniacid=" . $_W['uniacid'] . " AND rid=" . $rid . "");
            if (empty($adv)) {
                message('抱歉，广告不存在或是已经被删除！', $this->createWebUrl('adv', array('op' => 'display', 'rid' => $rid)), 'error');
            }
            pdo_delete($this->table_advs, array('id' => $id));
            message('广告删除成功！', $this->createWebUrl('adv', array('op' => 'display', 'rid' => $rid)), 'success');
        } else {
            message('请求方式不存在');
        }
        include $this->template('adv');
    }
	//导出数据
	public function doWebdownload(){
		require_once 'download.php';
	}
	####################################
		
	public function webmessage($error, $url = '', $errno = -1) {
        $data = array();
        $data['errno'] = $errno;
        if (!empty($url)) {
            $data['url'] = $url;
        }
        $data['error'] = $error;
        echo json_encode($data);
        exit;
    }
	
	####################################
	public function doWebFmoth() {
		global $_GPC, $_W;
		checklogin();
		$uniacid = $_W['uniacid'];//当前公众号ID
				
				
				
		if (checksubmit('submit')) {
		if (!empty($_GPC['oauthurl'])) {
			foreach ($_GPC['oauthurl'] as $index => $row) {
				$data = array(
					'oauthurl' => $_GPC['oauthurl'][$index],
					'createtime' => time(),
				);
				if (!empty($_GPC['visitorsip'][$index])) {
					$data['visitorsip'] = $_GPC['visitorsip'][$index];
				}
				if(!empty($data['oauthurl'])) {
					if(pdo_fetch("SELECT id FROM ".tablename('fm_api_oauth')." WHERE oauthurl = :oauthurl AND id != :id", array(':oauthurl' => $data['oauthurl'], ':id' => $index))) {
						continue;
					}
					if(pdo_fetch("SELECT id FROM ".tablename('fm_api_oauth')." WHERE visitorsip = :visitorsip AND id != :id", array(':visitorsip' => $data['visitorsip'], ':id' => $index))) {
						continue;
					}
					$row = pdo_fetch("SELECT id FROM ".tablename('fm_api_oauth')." WHERE oauthurl = :oauthurl AND visitorsip = :visitorsip LIMIT 1",array(':oauthurl' => $data['oauthurl'],':visitorsip' => $data['visitorsip']));
					if(empty($row)) {
						pdo_update('fm_api_oauth', $data, array('id' => $index));
					}
					unset($row);
				}
			}
		}
		
		if (!empty($_GPC['oauthurl-new'])) {
			foreach ($_GPC['oauthurl-new'] as $index => $row) {
				$data = array(
						'oauthurl' => $_GPC['oauthurl-new'][$index],
						'visitorsip' => $_GPC['visitorsip-new'][$index],
						'createtime' => time(),
				);
				if(!empty($data['oauthurl']) && !empty($data['visitorsip'])) {
					if(pdo_fetch("SELECT id FROM ".tablename('fm_api_oauth')." WHERE oauthurl = :oauthurl", array(':oauthurl' => $data['oauthurl']))) {
						continue;
					}
					pdo_insert('fm_api_oauth', $data);
					unset($row);
				}
			}
		}
		
		if (!empty($_GPC['delete'])) {
			pdo_query("DELETE FROM ".tablename('fm_api_oauth')." WHERE id IN (".implode(',', $_GPC['delete']).")");
		}

		message('更新成功！', referer(), 'success');
	}
	$list = pdo_fetchall("SELECT * FROM ".tablename('fm_api_oauth')." WHERE 1");
		
		include $this->template('fmoth');

	}
	
	
}

if(!function_exists('paginationm')) {
	/**
	 * 生成分页数据
	 * @param int $currentPage 当前页码
	 * @param int $totalCount 总记录数
	 * @param string $url 要生成的 url 格式，页码占位符请使用 *，如果未写占位符，系统将自动生成
	 * @param int $pageSize 分页大小
	 * @return string 分页HTML
	 */
	function paginationm($tcount, $pindex, $psize = 15, $url = '', $context = array('before' => 5, 'after' => 4, 'ajaxcallback' => '')) {
		global $_W;
		$pdata = array(
			'tcount' => 0,
			'tpage' => 0,
			'cindex' => 0,
			'findex' => 0,
			'pindex' => 0,
			'nindex' => 0,
			'lindex' => 0,
			'options' => ''
		);
		if($context['ajaxcallback']) {
			$context['isajax'] = true;
		}

		$pdata['tcount'] = $tcount;
		$pdata['tpage'] = ceil($tcount / $psize);
		if($pdata['tpage'] <= 1) {
			return '';
		}
		$cindex = $pindex;
		$cindex = min($cindex, $pdata['tpage']);
		$cindex = max($cindex, 1);
		$pdata['cindex'] = $cindex;
		$pdata['findex'] = 1;
		$pdata['pindex'] = $cindex > 1 ? $cindex - 1 : 1;
		$pdata['nindex'] = $cindex < $pdata['tpage'] ? $cindex + 1 : $pdata['tpage'];
		$pdata['lindex'] = $pdata['tpage'];

		if($context['isajax']) {
			if(!$url) {
				$url = $_W['script_name'] . '?' . http_build_query($_GET);
			}
			$pdata['faa'] = 'href="javascript:;" onclick="p(\'' . $_W['script_name'] . $url . '\', \'' . $pdata['findex'] . '\', ' . $context['ajaxcallback'] . ')"';
			$pdata['paa'] = 'href="javascript:;" onclick="p(\'' . $_W['script_name'] . $url . '\', \'' . $pdata['pindex'] . '\', ' . $context['ajaxcallback'] . ')"';
			$pdata['naa'] = 'href="javascript:;" onclick="p(\'' . $_W['script_name'] . $url . '\', \'' . $pdata['nindex'] . '\', ' . $context['ajaxcallback'] . ')"';
			$pdata['laa'] = 'href="javascript:;" onclick="p(\'' . $_W['script_name'] . $url . '\', \'' . $pdata['lindex'] . '\', ' . $context['ajaxcallback'] . ')"';
		} else {
			if($url) {
				$pdata['faa'] = 'href="?' . str_replace('*', $pdata['findex'], $url) . '"';
				$pdata['paa'] = 'href="?' . str_replace('*', $pdata['pindex'], $url) . '"';
				$pdata['naa'] = 'href="?' . str_replace('*', $pdata['nindex'], $url) . '"';
				$pdata['laa'] = 'href="?' . str_replace('*', $pdata['lindex'], $url) . '"';
			} else {
				$_GET['page'] = $pdata['findex'];
				$pdata['faa'] = 'href="' . $_W['script_name'] . '?' . http_build_query($_GET) . '"';
				$_GET['page'] = $pdata['pindex'];
				$pdata['paa'] = 'href="' . $_W['script_name'] . '?' . http_build_query($_GET) . '"';
				$_GET['page'] = $pdata['nindex'];
				$pdata['naa'] = 'href="' . $_W['script_name'] . '?' . http_build_query($_GET) . '"';
				$_GET['page'] = $pdata['lindex'];
				$pdata['laa'] = 'href="' . $_W['script_name'] . '?' . http_build_query($_GET) . '"';
			}
		}

		$html = '<div class="pagination pagination-centered"><ul class="pagination pagination-centered">';
		if($pdata['cindex'] > 1) {
			$html .= "<li><a {$pdata['faa']} class=\"pager-nav\">首页</a></li>";
			$html .= "<li><a {$pdata['paa']} class=\"pager-nav\">&laquo;上一页</a></li>";
		}
		//页码算法：前5后4，不足10位补齐
		if(!$context['before'] && $context['before'] != 0) {
			$context['before'] = 5;
		}
		if(!$context['after'] && $context['after'] != 0) {
			$context['after'] = 4;
		}

		if($context['after'] != 0 && $context['before'] != 0) {
			$range = array();
			$range['start'] = max(1, $pdata['cindex'] - $context['before']);
			$range['end'] = min($pdata['tpage'], $pdata['cindex'] + $context['after']);
			if ($range['end'] - $range['start'] < $context['before'] + $context['after']) {
				$range['end'] = min($pdata['tpage'], $range['start'] + $context['before'] + $context['after']);
				$range['start'] = max(1, $range['end'] - $context['before'] - $context['after']);
			}
			for ($i = $range['start']; $i <= $range['end']; $i++) {
				if($context['isajax']) {
					$aa = 'href="javascript:;" onclick="p(\'' . $_W['script_name'] . $url . '\', \'' . $i . '\', ' . $context['ajaxcallback'] . ')"';
				} else {
					if($url) {
						$aa = 'href="?' . str_replace('*', $i, $url) . '"';
					} else {
						$_GET['page'] = $i;
						$aa = 'href="?' . http_build_query($_GET) . '"';
					}
				}
				$html .= ($i == $pdata['cindex'] ? '<li class="active"><a href="javascript:;">' . $i . '</a></li>' : "<li><a {$aa}>" . $i . '</a></li>');
			}
		}

		if($pdata['cindex'] < $pdata['tpage']) {
			$html .= "<li><a {$pdata['naa']} class=\"pager-nav\">下一页&raquo;</a></li>";
			$html .= "<li><a {$pdata['laa']} class=\"pager-nav\">尾页</a></li>";
		}
		$html .= '</ul></div>';
		return $html;
	}
}