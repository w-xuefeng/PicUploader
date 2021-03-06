<?php
	/**
	 * Created by PhpStorm.
	 * User: bruce
	 * Date: 2018-08-29
	 * Time: 00:35
	 */
	
	/*
	 * 关于$argv与$argc变量，这两个变量是php作为脚本执行时，获取输入参数的变量
	 * 比如 php test.php aa bb，那么在test.php里打印$argv变量就是一个数组，包含3个元素test.php, aa, bb，而$argc就是$argv的元素个数，相当于count($argc)，当然也可用$_SERVER['argv']，$_SERVER['argc']表示。
	 */
	
	/*header('Content-Type: application/json; charset=UTF-8');
    echo '{"code":"success","data":{"filename":"20190418194552.png","url":"https://ws2.sinaimg.cn/large/6db312f1gy1g270z0i9qtj207205m3yv.jpg"}}';exit;*/
	
	date_default_timezone_set('Asia/Shanghai');
	
	require 'vendor/autoload.php';
	require 'common/EasyImage.php';
	
	define('APP_PATH', strtr(__DIR__, '\\', '/'));
	
	$defaultConfig = [];
	if(is_file(APP_PATH.'/config/.config.json')){
		$defaultConfig = json_decode(file_get_contents(APP_PATH.'/config/.config.json'), true);
	}
	
	if(!empty($defaultConfig)){
		$storageDir = APP_PATH.'/config/.settings';
		
		//通用设置
		$generalSettingsFile = $storageDir . '/general-settings.json';
		if(is_file($generalSettingsFile)){
			$generalSettings = json_decode(file_get_contents($generalSettingsFile), true);
		}else{
			$generalSettings = $defaultConfig;
		}
		
		$storageType = '';
		//把后台保存的配置文件的格式处理为可用格式
		if(isset($generalSettings['storageType']) && is_array($generalSettings['storageType'])){
			foreach($generalSettings['storageType'] as $key=>$val){
				if(isset($val['isActive']) && $val['isActive']=="1"){
					$storageType .= ucfirst($key).',';
				}
			}
			$storageType = rtrim($storageType, ',');
		}else{
			$storageType = $generalSettings['storageType'];
		}
		$generalSettings['storageType'] = $storageType;
		
		/*//把后台allowMimeTypes处理为可用格式
		$allowMimeTypes = '';
		if(isset($generalSettings['allowMimeTypes']) && is_array($generalSettings['allowMimeTypes'])){
			foreach($generalSettings['allowMimeTypes'] as $key=>&$val){
				if(isset($val['isActive']) && $val['isActive']=="1"){
					$val = $val['value'];
				}
			}
		}*/
		
		//自定义返回链接格式
		$customFormatFile = $storageDir . '/customFormat.json';
		$customFormat = '';
		if(is_file($customFormatFile)){
			$customFormat = json_decode(file_get_contents($customFormatFile), true);
		}
		
		//图片水印设置
		$imageWatermarkFile = $storageDir . '/image-watermark.json';
		$imageWatermark = [];
		if(is_file($imageWatermarkFile)){
			$imageWatermark = json_decode(file_get_contents($imageWatermarkFile), true);
			!is_file(APP_PATH.$imageWatermark['watermark']) && $imageWatermark['watermark'] = '/static/watermark/watermark.png';
		}
		
		//文字水印设置
		$textWatermarkFile = $storageDir . '/text-watermark.json';
		$textWatermark = [];
		if(is_file($textWatermarkFile)){
			$textWatermark = json_decode(file_get_contents($textWatermarkFile), true);
			!is_file(APP_PATH.$textWatermark['fontFile']) && $textWatermark['fontFile'] = '/static/watermark/jdchj.ttf';
		}
		
		//存储引擎配置
		$storagesFiles = glob($storageDir.'/storage-*');
		$storageTypes = [];
		if(!empty($storagesFiles)){
			foreach($storagesFiles as $storagesFile){
				$key = str_replace('.json', '', substr($storagesFile, strrpos($storagesFile,'-') + 1));
				$storageTypes[$key] = json_decode(file_get_contents($storagesFile), true);
			}
			//特殊处理sm.ms，因为它可以不需要任何参数
			if(!array_key_exists('smms', $storageTypes)){
				$storageTypes['smms'] = [
					'proxy' => '',
					'name' => 'sm.ms',
				];
			}
		}else{
			$storageTypes['smms'] = [
				'proxy' => '',
				'name' => 'sm.ms',
			];
		}
		
		//把以上配置合并到通用配置中
		$generalSettings['customFormat'] = $customFormat;
		$generalSettings['watermark']['image'] = $imageWatermark;
		$generalSettings['watermark']['text'] = $textWatermark;
		$generalSettings['storageTypes'] = $storageTypes;
	}
	
	if(!empty($storageTypes) && !empty($textWatermark)){
		$config = $generalSettings;
	}else{
		$localConfigFile = APP_PATH . '/config/config-local.php';
		if(is_file($localConfigFile)){
			$config = require APP_PATH . '/config/config-local.php';
		}else{
			$config = require APP_PATH . '/config/config.php';
		}
	}
	
	require APP_PATH . '/thirdpart/bce-php-sdk-0.9/BaiduBce.phar';
	require APP_PATH . '/thirdpart/ufile-phpsdk/v1/ucloud/proxy.php';
	
	//autoload class
	spl_autoload_register(function ($class_name) {
		require_once APP_PATH . '/' . str_replace('\\', '/', $class_name) . '.php';
	});
	
	$isMweb = false;
	if(isset($_FILES['mweb'])){
		$isMweb = true;
		$_FILES['file'] = $_FILES['mweb'];
		unset($_FILES['mweb']);
	}
	$isPicgo = false;
	if(isset($_FILES['picgo'])){
		$isPicgo = true;
		$_FILES['file'] = $_FILES['picgo'];
		unset($_FILES['picgo']);
	}
	
	//if has post file
	if(isset($_FILES['file']) && $files = $_FILES['file']){
		$tmpDir = APP_PATH.'/.tmp';
		!is_dir($tmpDir) && @mkdir($tmpDir, 0777);
		//receive multi file upload
		if(is_array($files['tmp_name'])){
			$argv = [];
			foreach($files['tmp_name'] as $key=>$tmp_name){
				$dest = $tmpDir.'/'.$files['name'][$key];
				if(move_uploaded_file($tmp_name, $dest)){
					$argv[] = $dest;
				}
			}
		}else{
			//receive single file upload
			$dest = $tmpDir.'/'.$files['name'];
			$tmp_name = $files['tmp_name'];
			if(move_uploaded_file($tmp_name, $dest)){
				$argv[] = $dest;
			}
		}
	}else{
		//去除第一个元素（因为第一个元素是index.php，因为$argv是linux/mac的参数，
		//用php执行index.php的时候，index.php也算是一个参数）
		if(isset($argv) && $argv){
			array_shift($argv);
		}else{
			exit('未检测到图片');
		}
	}
	
	//getPublickLink
	$link = (new uploader\Upload($argv, $config))->getPublickLink([
		'do_not_format' => ($isMweb || $isPicgo),
	]);
	
	//如果是MWeb或PicGo，则返回Mweb/Picgo支持的json格式
	if($isMweb || $isPicgo){
		if($isMweb){
			$data = [
				'code' => 'success',
				'data' => [
					'filename' => $_FILES['file']['name'],
					'url' => $link,
				],
			];
		}else{
			$data = [
				'code' => 'success',
				'url' => $link,
			];
		}
		
		header('Content-Type: application/json; charset=UTF-8');
		$json = json_encode($data, JSON_UNESCAPED_UNICODE);
		echo $json;
	}else{
		//如果是client模式，则直接返回链接
		echo $link;
	}