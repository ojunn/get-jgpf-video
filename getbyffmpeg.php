<?php
error_reporting(E_ALL);
define("PROGRESS_WIDTH",40);
define("PROGRESS_STEP",100/PROGRESS_WIDTH);

$savedir = "./save";
if(!file_exists($savedir))mkdir($savedir,0777,true);
$tmpdir = "./tmp/json";
if(!file_exists($tmpdir))mkdir($tmpdir,0777,true);


//IDのファイル読み込み
$id_file = "./video_id";
if($argc>1){
	$id_file = $argv[1];
	if(!file_exists($id_file)){
		echo "\tinvalid file specified: ".$id_file."\n";
		exit;
	}
}
echo "reading ".$id_file."\n";

$ids = file_get_contents($id_file);
if(strlen($ids)){
	$ids = explode("\n",$ids);
}else{
	echo "invalid file\n";
	exit;
}

foreach($ids as $key => $id){
	if(!strlen($id))unset($ids[$key]);
}
echo count($ids)." ids found.\n";

$m3u8_file = "./m3u8-list.txt";
$json_file = "./json-list.txt";
if(file_exists($m3u8_file))unlink($m3u8_file);
if(file_exists($json_file))unlink($json_file);
foreach($ids as $id){
	echo "#".$id." ";

	$json_local = "./tmp/json/".$id.".json";
	$thumbnail_url = "https://www.nhk.or.jp/figure/thumbnail/".$id."_LL.jpg";
	
	//JSONを取得
	$jsonp_url = "https://www.nhk.or.jp/figure/live-data/js_data/".$id.".jsonp";
	if(!file_exists($json_local)||!filesize($json_local)){
		$jsonp = file_get_contents($jsonp_url);
		$json_data = substr($jsonp, 13, strlen($jsonp)-14);
		file_put_contents($json_local, $json_data);
	}else{
		$json_data = file_get_contents($json_local);
	}
	
	//JSONから情報を読み込む
	$json = json_decode($json_data,true);	
	if(is_array($json) && count($json)){
		$json = $json[0];
	}
	if(!isset($json["mp4filename"])){
		echo "\tinvalid json: ".$json_local."\n";
		var_dump($json);
		break;
	}

	$mp4filename = $json["mp4filename"];
	$category = $json["category"];
	$segment  = $json["segment"];
	$title    = $json["title"];
	$player   = $json["player_name"];
	echo "".$category." - ".$segment." - ".$player."\n";

	//JSON list
	file_put_contents($json_file, "# ".$title."\n".$jsonp_url."\n", FILE_APPEND);

	//保存ディレクトリ
	$savedir = "./save/".$category."/".$segment;
	if(!file_exists($savedir))mkdir($savedir,0777,true);
	
	//サムネイル
	$thumb_local = $savedir."/".$title.".jpg";
	if(!file_exists($thumb_local)||!filesize($thumb_local)){
		file_put_contents($thumb_local,file_get_contents($thumbnail_url));
	}

	//master.m3u8 これに含まれるURLは一定時間後に無効になる
	$m3u_url = "https://nhk-vh.akamaihd.net/i/figure/r/figure/". $mp4filename .".mp4/master.m3u8";
	file_put_contents($m3u8_file, "# ".$title."\n".$m3u_url."\n", FILE_APPEND);

	//最終ファイル：既存の場合は削除
	$video_filename_one = $savedir."/".$title.".mp4";
	if(file_exists($video_filename_one))unlink($video_filename_one);
	
	$copy = "ffmpeg -loglevel quiet -i \"".$m3u_url."\" -c copy -bsf:a aac_adtstoasc \"".$video_filename_one."\"";
	exec($copy);
	echo "\n";
	echo "\tsaved as ".$video_filename_one."\n";
//	sleep(max(mt_rand(0,10),7)-7);

}

