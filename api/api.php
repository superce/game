<?php
/**
 * [Fmoons System] Copyright (c) 2014 012wz.com
 * Fmoons is NOT a free software, it under the license terms, visited http://www.012wz.com/ for more details.
 */
define('IN_API', true);
require_once '../framework/bootstrap.inc.php';
load()->model('reply');
load()->app('common');
load()->classs('wesession');
$api = $_GPC['api'];
if ($api == 'api') {
	$ourl = '../api/api.php?&weburl=';
	$apiurl = base64_encode($ourl);
	$fmdata = array(
		"config" => 1,
		"apiurl" => $apiurl,
	);
	echo json_encode($fmdata);
	exit();	
}


$oauthurl = $_GPC['weburl'];
$visitorsip = $_GPC['visitorsip'];

$sql = 'SELECT * FROM ' . tablename('fm_api_oauth') . ' WHERE `oauthurl`=:oauthurl';
$pars = array();
$pars[':oauthurl'] = $oauthurl;
$oauth = pdo_fetch($sql, $pars);



//if ($oauth) {
//	$q = pdo_fetch('SELECT * FROM ' . tablename('fm_api_oauth') . ' WHERE `oauthurl`=:oauthurl AND `visitorsip`=:visitorsip', array(':oauthurl'=>$oauthurl,':visitorsip'=>$visitorsip));
//	if (!$q) {
//		pdo_update('fm_api_oauth',array('visitorsip'=>$visitorsip),array('oauthurl'=>$oauthurl));
//	}
//	$fmdata = array(
//		"config" => 1,
//		"s" => 1,
//		"m" => '您已授权成功！',		
//	);
//	echo json_encode($fmdata);
//	exit();	
//}else{
//	pdo_insert('fm_api_oauth_list', array('oauthurl'=>$oauthurl, 'visitorsip'=>$visitorsip, 'createtime'=>time()));
//	$fmdata = array(
//		"config" => 0,
//		"s" => 0,
//		"m" => '未授权！ 请联系我们（QQ：513200958），进行授权，否则出现问题自负！',
		
//	);
	
	
//	echo json_encode($fmdata);
//	exit();	
//}