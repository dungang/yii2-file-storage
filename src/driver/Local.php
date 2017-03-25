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
    /**
     * @return bool|string
     */
    public function writeFile()
    {
        $fileName = md5($this->guid . $this->id);

        $file = $fileName . '.' . $this->file->extension;

        $dir = $this->saveDir .DIRECTORY_SEPARATOR. date('Y-m-d');

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
                return $this->response(BaseFileHelper::normalizePath( $dir . DIRECTORY_SEPARATOR . $file));
            }

        }
        return $this->response(null,500,'Upload failed');
    }

    public function deleteFile($file)
    {
        $file = BaseFileHelper::normalizePath(ltrim($file,'/\\'));
        $dir = BaseFileHelper::normalizePath(ltrim($this->saveDir,'/\\'));
        $prefix = substr($file,0,strlen($dir));
        if (strcasecmp($prefix,$dir)==0) {
            $path = \Yii::getAlias('@webroot') . DIRECTORY_SEPARATOR . $file;
            return @unlink($path);
        }
        return false;
    }

    public function getSourceUrl($object)
    {
        //return \Yii::$app->request->baseUrl . '/' . ltrim($object,'/');
        return ltrim($object,'/');
    }

    public function getBindUrl($object)
    {
        return $this->getSourceUrl($object);
    }


}