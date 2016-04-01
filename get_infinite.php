<?php

$time = array(
//	array(strtotime("2015-12-12T01:04 JST"), strtotime("2015-12-12T02:05 JST")),
	array(strtotime("2015-12-12T21:25 JST"), strtotime("2015-12-12T22:25 JST")),
);


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

$counter=0;
while(true){
		echo "\r                                 ";
	$now = time();
	if($time[0][0] <= $now && $time[0][1] >= $now){
		//do
		echo "\r                                 End at ".date("r",$time[0][1]);
	}elseif($time[count($time)-1][1] < $now){
		echo "END\n";
		exit;
	}else{
		$diff = $time[0][0] - $now;
		if($diff >0){
			if($diff < 60){// 1 mins
				sleep(10);
				echo "\r\twait for ".($diff)." seconds until ".date("r",$time[0][0]);
			}else{
				echo "\r\twait for ".ceil($diff/60)." minutes until ".date("r",$time[0][0]);
				if($diff < 600){// 10 mins
					sleep(30);
				}elseif($diff < 3600){// 60 mins
					sleep(300);//5mins
				}else{
					sleep(600);//10mins
				}
			}
		}
		continue;
	}



	$list_local = $tmpdir."/list.m3u8";
	echo "\r                                 ";
	echo "\r\tfetching list ".($counter++%2 ? "/": "\\");
	$video_list = file_get_contents($list_url);
	file_put_contents($list_local, $video_list);
	
	if(!strlen($video_list)){
		echo "\tfailed to fetch index\n";
		exit;
	}


	$video_list = explode("\n",$video_list);
	
	//crypt.key
	$key_file = $tmpdir."/crypt.key";
	if(mt_rand(0,100) > 95){
		foreach($video_list as $line){
			if(!strlen($line)) continue;
			if(strpos($line, "#EXT-X-KEY")===0){
				$key_url = substr($line, strpos($line,"URI="));
				$key_url = BASE_URI.substr($key_url, 5, strlen($key_url)-1);
				echo "\r                                 ";
				echo "\r\tfetching key";//.$key_url."\n";
				file_put_contents($key_file,file_get_contents($key_url));
				break;
			}
		}
	}

	if(!file_exists($key_file)){
		echo "\tcrypt.key not found";
		exit;
	}else{
		$key = file_get_contents($key_file);
	}
	//$key_hex = bin2hex($key);//exec("cat \"".$key_file."\" | hexdump -e '16/1 \"%02x\"'");
	
	// SAVE VIDEOS

	//動画のURL以外はリストから削除
	foreach($video_list as $key => $line){
		if(!strlen($line) || $line[0]=="#") unset($video_list[$key]);
	}

	$video_splits = count($video_list);
	foreach($video_list as $line){
		if(strpos($line,"media-")===0){
			$number = explode("_",$line);
			$number = explode(".",$number[1]);
			$number = $number[0]; 
			$line = MEDIA_URI.trim($line);

			//復号したファイルの保存名
			$video_filename = $tmpdir."/video_decrypted_".$number.".ts";
			//file_put_contents($ts_list_file, $video_filename."\n", FILE_APPEND);

			//暗合されたファイルを取得
			$crypted_video_filename = $tmpdir."/video_".$number.".crypted.ts";
			if(!file_exists($crypted_video_filename) || !filesize($crypted_video_filename)){
				echo "\r                                 ";
				echo "\r\tfetching ".$number;			
				file_put_contents($crypted_video_filename,file_get_contents($line));
			}
			
			//復号
			if( file_exists($crypted_video_filename) && filesize($crypted_video_filename) ){
				if( !file_exists($video_filename)||!filesize($video_filename) ){
					#echo "\tdecrypt video #".$counter."\n";
					$iv = $number;
					$decrypt = "openssl enc -d -aes-128-cbc -in \"".$crypted_video_filename."\" -K $(cat \"".$key_file."\" | hexdump -e '16/1 \"%02x\"') -iv ".$iv." -out \"".$video_filename."\"";
					#echo "\n".$decrypt."\n";
					exec($decrypt);
				}
			}

			sleep(max(mt_rand(0,10),8)-8);
			continue;
		}
	}

	//echo "\n";
	sleep(max(mt_rand(0,60),50)-50);

}

