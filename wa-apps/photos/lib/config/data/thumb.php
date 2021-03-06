<?php
/**
 * @todo check allowed sizes
 * @todo use resize options (quiality and filters)
 * @todo use error handlers to display error while resize
 */


$path = realpath(dirname(__FILE__)."/../../../../../");
$config_path =$path."/wa-config/SystemConfig.class.php";
if (!file_exists($config_path)) {
    header("Location: ../../../../../wa-apps/photos/img/image-not-found.png");
    exit;
}

require_once($config_path);
$config = new SystemConfig();
waSystem::getInstance(null, $config);

$request_file = wa()->getConfig()->getRequestUrl(true, true);

if (preg_match("@^thumb.php/(.+)@", $request_file, $matches)) {
    $request_file = $matches[1];
}

$public_path = $path.'/wa-data/public/photos/';
$protected_path = $path.'/wa-data/protected/photos/';

$main_thumb_file = false;
$file = false;
$size = false;

// app settings
$app_config = wa('photos')->getConfig();

$main_thumbnail_size = photosPhoto::getBigPhotoSize();

if (preg_match('@((?:\d{2}/){2}([0-9]+)(?:\.[0-9a-f]+)?)/\\2\.(\d+(?:x\d+)?)\.([a-z]{3,4})@i', $request_file, $matches)) {
    $file = $matches[1].'.'.$matches[4];
    $main_thumb_file = $matches[1].'/'.$matches[2].'.'.$main_thumbnail_size.'.'.$matches[4];

    $sizes = explode('x', $matches[3]);

    $gen_thumbs = $app_config->getOption('thumbs_on_demand');
    if ($gen_thumbs) {
        // check max size
        foreach ($sizes as $s) {
            if ($s > $app_config->getOption('max_size')) {
                $file = false;
                break;
            }
        }
    }
    $size = implode('x', $sizes);

    if ($file && !$gen_thumbs) {
        $thumbnail_sizes = $app_config->getSizes();
        if (in_array($size, $thumbnail_sizes) === false) {
            $file = false;
        }
    }
}
wa()->getStorage()->close();
if ($file && file_exists($protected_path.$file) && !file_exists($public_path.$request_file)) {

    $main_thumb_file_path = $public_path.$main_thumb_file;

    $target_dir_path = dirname($public_path.$request_file);
    if(!file_exists($target_dir_path)){
        waFiles::create($target_dir_path.'/');
    }
    $image = photosPhoto::generateThumb(array(
            'path' => $main_thumb_file_path,
            'size' => $main_thumbnail_size
        ),
        $protected_path.$file,
        $size
    );
    if ($image) {
        // sharp
        if ($app_config->getOption('sharpen')) {
            $image->sharpen(photosPhoto::SHARP_AMOUNT);
        }
        $image->save($public_path.$request_file);
        clearstatcache();
    }
}

if($file && file_exists($public_path.$request_file)){
    waFiles::readFile($public_path.$request_file);
} else{
    /*
    $url = wa()->getRootUrl();
    $url = substr($url, 0, -strlen('/wa-data/public/photo/'));
    header("Location: ".$url."wa-apps/photos/img/image-not-found.png");
    */
    header("HTTP/1.0 404 Not Found");
    exit;
}
