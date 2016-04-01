<?php
error_reporting(E_ALL);
define("PROGRESS_WIDTH",40);
define("PROGRESS_STEP",100/PROGRESS_WIDTH);
define("BASE_URI","http://aka-liveportal01-live.uliza.jp");
define("MEDIA_URI","http://aka-liveportal01-live.uliza.jp/hls/video/liveportal01/liveportal01_channel01_3/");

$savedir = "./save";
if(!file_exists($savedir))mkdir($savedir,0777,true);
$tmpdir = "./tmp";
if(!file_exists($tmpdir))mkdir($tmpdir,0777,true);

	//master.m3u8 これに含まれるURLは一定時間後に無効になる
	$m3u_url = "https://www2.uliza.jp/IF/iphone/iPhoneLivePlaylist.m3u8?v=tv-asahi-live_channel01_pc_high_hls&p=6243&d=1249&n=2919&cpv=1";//"http://aka-liveportal01-live.uliza.jp/hls/video/liveportal01/liveportal01_channel01_3/chunklist.m3u8";
	$m3u_local = $tmpdir."/master.m3u8";
	if(!file_exists($m3u_local)||!filesize($m3u_local)){
		echo "\tfetching master.m3u8\n";
		$m3u = file_get_contents($m3u_url);
		file_put_contents($m3u_local,$m3u);
	}else{
		echo "\treading local /master.m3u8\n";
		$m3u = file_get_contents($m3u_local);
	}
	$m3u = explode("\n",$m3u);

	//index_0_av.m3u8
	$video_list = "";
	$list_url = "";
	foreach($m3u as $line){
		if(!strlen($line) || $line[0] == "#") continue;
		if(strpos($line,"http://")===0){
			$list_url = trim($line);
			break;
		}
	}

	if(!strlen($list_url)){
		echo "\tfailed to fetch index\n";
		exit;
	}


	$list_local = $tmpdir."/list.m3u8";
	echo "\tfetching list \n";
	$video_list = file_get_contents($list_url);
	file_put_contents($list_local, $video_list);
	
	if(!strlen($video_list)){
		echo "\tfailed to fetch index\n";
		exit;
	}


	$video_list = explode("\n",$video_list);
	
	//crypt.key
	$key_file = $tmpdir."/crypt.key";
	foreach($video_list as $line){
		if(!strlen($line)) continue;
		if(strpos($line, "#EXT-X-KEY")===0){
			$key_url = substr($line, strpos($line,"URI="));
			$key_url = BASE_URI.substr($key_url, 5, strlen($key_url)-1);
			echo "\tfetching key    \n";//.$key_url."\n";
			file_put_contents($key_file,file_get_contents($key_url));
			break;
		}
	}

	if(!file_exists($key_file)){
		echo "\tcrypt.key not found";
		exit;
	}else{
		$key = file_get_contents($key_file);
	}

$target = range(200,695);

foreach($target as $number){
	// SAVE VIDEOS

	//復号したファイルの保存名
	$video_filename = $tmpdir."/video_decrypted_".$number.".ts";

	//暗合されたファイルを取得
	$crypted_video_filename = $tmpdir."/media-uhq5lqjfm_".$number.".ts";
	
	//復号
	if( file_exists($crypted_video_filename) && filesize($crypted_video_filename) ){
	echo "\tdecrypt video #".$number."\n";
		if(file_exists($video_filename))unlink($video_filename);
			$iv = $number;
			$decrypt = "openssl enc -d -aes-128-cbc -in \"".$crypted_video_filename."\" -K $(cat \"".$key_file."\" | hexdump -e '16/1 \"%02x\"') -iv ".$iv." -out \"".$video_filename."\"";
			#echo "\n".$decrypt."\n";
			exec($decrypt);
	}

//	sleep(max(mt_rand(0,10),8)-8);
	continue;
}

