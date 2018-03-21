<?php
session_start();
error_reporting(0);
date_default_timezone_set('Asia/Ho_Chi_Minh');
header('Content-Type: text/html; charset=utf-8');// a edut iwr cáu bnaft kyiib cxung đc
require_once '../config.php';
require_once 'fbspeed.class.php';
echo '<pre>';
$vip = mysql_query("SELECT * FROM vip_like WHERE status = 0 ORDER BY RAND() LIMIT 25");
if (mysql_num_rows($vip) === 0) die('Chưa Có Người Dùng');
reset_token();
if ($vip) {
	while ($row = mysql_fetch_array($vip)) {
		$TOKEN = array();
		if (time() < $row['time_buy']+$row['limit_time']*30*86400) {
			$getPackage = mysql_fetch_assoc(mysql_query("SELECT limit_like, limit_post FROM package_vip WHERE id = '".$row['name_package']."' LIMIT 1"));
			$limitPost = $getPackage['limit_post'];
			$speedLike = $row['speed'];
			$tokens = get_tokens_random(10);
			while ($token = mysql_fetch_array($tokens)) {
				$checkToken = checkToken($token['token']);
				if ($checkToken == 1) {
					$ACCESS_TOKEN = $token['token'];
					break;
				}
			}
			$getPost = getPost($row['fbid'], $ACCESS_TOKEN);
			if ($getPost != 0) {
				$posts = array();
	            $count_posts = count($getPost);
	            if ($count_posts > $limitPost) {
	                for ($i = $count_posts - $limitPost; $i < $count_posts; $i++) {
	                    array_push($posts, $getPost[$i]);
	                }
	            } else {
	                $posts = $getPost;
	            }
	            foreach ($posts as $key => $post) {
	            	$limitLike = $getPackage['limit_like'];
	            	$TOKEN = array();
	            	$post_data = array();
	            	$sttID = $post->id;
					$countLike = count_react($sttID, $ACCESS_TOKEN);
					$message = $post->message ? $post->message : "NULL";
					if (preg_match('/#l_\d{1,}/', $message, $match)) {
						$rl = str_replace('#l_', '', $match[0]);
						if ($rl < $limitLike) {
							$limitLike = $rl;
						} else {
							echo 'ID_POST: <b><a href="//fb.com/'.$sttID.'" target="_blank">'.$sttID.'</a></b>||FBID: <b>'.$row['fbid'].'</b>||FBNAME:<b style="color: blue;">'.$row['name'].'</b>||Số Like Yêu Cầu: <b style="color: red;">'.$limitLike.'</b>||Trạng Thái: <b style="color: red;">HashTag Vượt Quá Số Like Cho Phép</b><br />';
						}
					}
					$likeConLai = $limitLike - $countLike;
					if ($likeConLai < $speedLike) {
						if ($likeConLai <= 0) {
							echo 'ID_POST: <b><a href="//fb.com/'.$sttID.'" target="_blank">'.$sttID.'</a></b>||FBID: <b>'.$row['fbid'].'</b>||FBNAME:<b style="color: blue;">'.$row['name'].'</b>||Số Like Yêu Cầu: <b style="color: red;">'.$limitLike.'</b>||Trạng Thái: <b style="color: #62e262;">OK</b><br />';
						} else {
							echo 'ID_POST: <b><a href="//fb.com/'.$sttID.'" target="_blank">'.$sttID.'</a></b>||FBID: <b>'.$row['fbid'].'</b>||FBNAME:<b style="color: blue;">'.$row['name'].'</b>||Số Like Yêu Cầu: <b style="color: red;">'.$limitLike.'</b>||Số Like Còn Thiếu: <b style="color: red;">'.$likeConLai.'</b><br />';
							$getTokenLike = get_tokens_random($likeConLai);
							while ($T = mysql_fetch_array($getTokenLike)) {
								$TOKEN[] = $T['token'];
								has_used($T['token']);
							}
							$post_data = array(
							    'time_delay' => 500,
							    'id' => $sttID,
								'typeReact' => explode('|', $row['camxuc']),
							    'access_token' => $TOKEN
							);
						}
					} else {
					    $dalike = $limitLike - $likeConLai;
						echo 'ID_POST: <b><a href="//fb.com/'.$sttID.'" target="_blank">'.$sttID.'</a></b>||FBID: <b>'.$row['fbid'].'</b>||FBNAME:<b style="color: blue;">'.$row['name'].'</b>||Số Like Yêu Cầu: <b style="color: red;">'.$limitLike.'</b>||Số Like Đã Chạy: <b style="color: red;">'.$dalike.'</b>||Trạng Thái: <b style="color: green;">Đang Chạy...</b><br />';
						$getTokenLike = get_tokens_random($speedLike);
						while ($T = mysql_fetch_array($getTokenLike)) {
							$TOKEN[] = $T['token'];
							has_used($T['token']);
						}
						$post_data = array(
						    'time_delay' => 300,
						    'id' => $sttID,
						   'typeReact' => explode('|', $row['camxuc']),
						    'access_token' => $TOKEN
						);
					}
					if (count($TOKEN) > 0) {
						if (preg_match('/#okk/', $message, $match)) {
							$_Neiht->setType('Auto-Like');
						} else {
							$_Neiht->setType('Auto-React-Custom'); //Auto-React-Custom
						}
						$_Neiht->setPostData($post_data);
						$_Neiht->execCurl();
						$response = $_Neiht->getResponse();
						//var_dump($response);
					}
					if (count($TOKEN) > 0) {
						$_Neiht->setType('Auto-React-Custom');
						$_Neiht->setPostData($post_data);
						$_Neiht->execCurl();
						$response = $_Neiht->getResponse();
						saveFile($row['name_buy'].'||'.$row['fbid'].'||'.$row['name'].'||'.$sttID.'||'.$dalike.'/'.$limitLike.'||'.date("H:i d-m-Y"));
					}
	            }
			}
		} else {
			$update = mysql_query("UPDATE vip_like SET status = 2 WHERE id = '".$row['id']."'");
		}
	}
}

function getPost($fbid, $token){
	$start_day_time = count_time_to_current_in_day(date("d/m/Y")) - 7200;
	$getPost = json_decode(file_get_contents('https://graph.facebook.com/' . $fbid . '/feed?fields=id,likes,message&since=' . $start_day_time . '&until=' . time() . '&access_token=' . $token . '&limit=20'));
		if ($getPost->data[0]->id) {
			return $getPost->data;
		}
		return 0;
}
function get_post($post_id, $token){
	$get_post = file_get_contents('https://graph.facebook.com/' . $post_id . '/feed?limit=1&access_token=' . $token);
	$get_post = json_decode($get_post, true);
	if ($get_post['data'][0]['id']) {
		return $get_post;
	} else {
		return 0;
	}
}
function saveFile($txt){
	$file = @fopen('vipLike.log.txt', 'a+');
	@fwrite($file, $txt."\n");
	@fclose($file);
}
function count_react($post_id, $token){
    $get_json = json_decode(file_get_contents('https://graph.facebook.com/'.$post_id.'/reactions?summary=true&access_token='.$token),true);
    if($get_json['summary']['total_count']){
        return $get_json['summary']['total_count'];
    } else {
        return 0;
    }
}
function checkToken($token){
	$get = json_decode(file_get_contents('https://graph.facebook.com/me/?access_token='.$token.'&field=id'), true);
	if ($get['id']) {
		return 1;
	}
	return 0;
}
function count_time_to_current_in_day($now){
    $date = DateTime::createFromFormat("d/m/Y", $now);
    $year = $date->format("Y");
    $month = $date->format("m");
    $day = $date->format("d");
    $dt = $day . "-" . $month . "-" . $year . " 00:00:00";
    $d = new DateTime($dt, new DateTimeZone('Asia/Ho_Chi_Minh'));
    return $d->getTimestamp();
}
function get_tokens_random($limit){
    return mysql_query("SELECT token FROM token WHERE has_used = 0 ORDER BY RAND() LIMIT " . $limit);
}
function has_used($token){
    return mysql_query("UPDATE token SET has_used = 1 WHERE token = '$token'");
}
function reset_token(){
    $result = mysql_query("SELECT id FROM token WHERE has_used = 0");
    if(mysql_num_rows($result) < 200){ // nhỏ hơn 200 reset token
    	return mysql_query("UPDATE token SET has_used = 0");
    }
}
?>
