<?php
/**
 * Created by PhpStorm.
 * User: dungang
 * Date: 2017/3/2
 * Time: 11:24
 */

namespace dungang\storage\driver;

use dungang\storage\File;
use yii\helpers\BaseFileHelper;
use dungang\storage\Driver;

class Local extends Driver
{
    public function getFormatSaveDir()
    {
        return  $this->saveDir
        . DIRECTORY_SEPARATOR . date('Y-m-d');
    }
    /**
     * @return bool|string
     */
    public function writeFile($file,$dir)
    {

        $path =  \Yii::getAlias('@webroot') . DIRECTORY_SEPARATOR . $dir;

        $position = 0;

        if (BaseFileHelper::createDirectory($path))
        {
            $targetFile = $path . DIRECTORY_SEPARATOR . $file;
            if($this->chunked) {
                $this->triggerEvent = false;
                if ($this->chunk === 0 ) {
                    $position = 0;
                    if (file_exists($targetFile)) {
                        @unlink($targetFile);
                    }
                } else  {
                    if ($this->chunk + 1 == $this->chunks) {
                        $this->triggerEvent = true;
                    }
                    $position = $this->chunkSize * $this->chunk;
                }
            }
            if($out = @fopen($targetFile,'a+b')){
                fseek($out,$position);
                if ( flock($out, LOCK_EX) ) {
                    if ($in = fopen($this->file->tempName, 'rb')) {
                        while ($buff = fread($in, 4096)) {
                            fwrite($out, $buff);
                        }
                        @fclose($in);
                        @unlink($this->file->tempName);
                    }
                    flock($out, LOCK_UN);
                }
                @fclose($out);
                return $this->response(BaseFileHelper::normalizePath( $dir . DIRECTORY_SEPARATOR . $file),'/');
            }

        }
        return $this->response(null,500,'Upload failed');
    }

    public function deleteFile($file)
    {
        $file = BaseFileHelper::normalizePath(ltrim($file,'/\\'),'/');
        $dir = BaseFileHelper::normalizePath(ltrim($this->saveDir,'/\\'),'/');
        $prefix = substr($file,0,strlen($dir));
        if (strcasecmp($prefix,$dir)==0) {
            $path = \Yii::getAlias('@webroot') . DIRECTORY_SEPARATOR . $file;
            return @unlink($path);
        }
        return false;
    }

    /**
     * @param int|string  $start
     * @param int $size
     * @return array|null
     */
    public function listFiles($start=0,$size=10)
    {
        $result = [
            "code" => 0
        ];
        $path = \Yii::getAlias('@webroot');
        $dir = $this->saveDir;
        $files =  $this->getFiles($path,$path .DIRECTORY_SEPARATOR . $dir);
        $len = count($files);
        if ($len) {
            $end = $start + $size;
            for ($i = min($end, $len) - 1, $list = array(); $i < $len && $i >= 0 && $i >= $start; $i--) {
                $list[] = $files[$i];
            }
            $result['list'] = $list;
            $result['start'] = $start;
            $result['total'] = $len;
        } else {
            $result['code'] = self::ERROR_FILE_NOT_FOUND;
            $result['message'] = "no match file";
        }
        return $result;
    }

    public function getSourceUrl($object)
    {
        return ltrim($object,'/');
    }

    public function getBindUrl($object)
    {
        return $this->getSourceUrl($object);
    }

    /**
     * 遍历获取目录下的指定类型的文件
     * @param $basePath
     * @param null $path
     * @param array $files
     * @return array|null
     */
    protected function getFiles($basePath,$path=null, &$files = array())
    {
        if (!is_dir($path)) return null;
        if (substr($path, strlen($path) - 1) != '/') $path .= '/';
        $handle = opendir($path);
        while (false !== ($file = readdir($handle))) {
            if ($file != '.' && $file != '..') {
                $path2 = $path . $file;
                if (is_dir($path2)) {
                    $this->getFiles($basePath,$path2, $files);
                } else {
                    $extension = strtolower(substr(strrchr($file, '.'),1));
                    if (in_array($extension,$this->accept)) {
                        $files[] = array(
                            'object' => substr($path2, strlen($basePath)),
                        );
                    }
                }
            }
        }
        return $files;
    }

}