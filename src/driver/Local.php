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
use dungang\storage\InitResponse;
use dungang\storage\ChunkRequest;
use dungang\storage\InitRequest;
use dungang\storage\ChunkResponse;
use yii\base\Exception;
use dungang\storage\ListResponse;

class Local extends Driver
{

    public $baseDir = '@webroot';

    private $_abBaseDir;

    public function init()
    {
        $this->_abBaseDir = \Yii::getAlias($this->baseDir);
    }

    /**
     *
     * @return string 生产guid
     */
    public static function guid()
    {
        // from stack overflow.
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', 
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), 
            // 16 bits for "time_mid"
            mt_rand(0, 0xffff), 
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000, 
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000, 
            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
    }
    

    /**
     * 遍历获取目录下的指定类型的文件
     *
     * @param
     *            $basePath
     * @param null $path
     * @param array $files
     * @return array|null
     */
    protected function getFiles($basePath, $path = null, &$files = array())
    {
        if (! is_dir($path))
            return null;
        if (substr($path, strlen($path) - 1) != '/')
            $path .= '/';
        $handle = opendir($path);
        while (false !== ($file = readdir($handle))) {
            if ($file != '.' && $file != '..') {
                $path2 = $path . $file;
                if (is_dir($path2)) {
                    $this->getFiles($basePath, $path2, $files);
                } else {
                    $extension = strtolower(substr(strrchr($file, '.'), 1));
                    if (in_array($extension, $this->accept)) {
                        $files[] = array(
                            'object' =>$this->normalizeWebPath($path2)
                        );
                    }
                }
            }
        }
        return $files;
    }

    /**
     *
     * {@inheritdoc}
     * @see \dungang\storage\IDriver::initUpload()
     * @param InitRequest $initRequest
     * @return InitResponse
     */
    public function initUpload($initRequest)
    {
        $extension = $this->fileExtension($initRequest->name);
        $res = new InitResponse();
        $res->uploadId = self::guid();
        $res->key = $this->normalizeWebPath($this->uploadDir . DIRECTORY_SEPARATOR . $this->dirSuffix . DIRECTORY_SEPARATOR . md5($res->uploadId . $initRequest->timestamp) . '.' . $extension);
        return $res;
    }

    /**
     *
     * {@inheritdoc}
     * @see \dungang\storage\IDriver::write()
     * @param ChunkRequest $chunkRequest
     * @return ChunkResponse
     */
    public function write($chunkRequest)
    {
        $chunkResponse = new ChunkResponse();
        $chunkResponse->uploadId = $chunkRequest->uploadId;
        $chunkResponse->extension = $this->fileExtension($chunkRequest->name);
        if (empty($chunkRequest->key)) {
            $chunkResponse->key = $this->normalizeWebPath($this->uploadDir . DIRECTORY_SEPARATOR . $this->dirSuffix . DIRECTORY_SEPARATOR . md5(self::guid()) . '.' . $chunkResponse->extension);
        } else {
            $chunkResponse->key = $chunkRequest->key;
        }

        $abKeyFile = BaseFileHelper::normalizePath($this->_abBaseDir . DIRECTORY_SEPARATOR . $chunkResponse->key);
        // 绝对路径
        $parentDir = dirname($abKeyFile);

        if (! file_exists($parentDir)) {
            try {
                BaseFileHelper::createDirectory($parentDir);
            } catch (Exception $e) {
                // 并发忽略错误
            }
        }

        // 没有分片
        if (null == $chunkRequest->chunks || $chunkRequest->chunks == 0) {
            if ($chunkRequest->uploadFile->saveAs($abKeyFile)) {
                $chunkResponse->isCompleted = true;
                $chunkResponse->eTag = md5_file($abKeyFile);
                $chunkResponse->url = $this->getKeyUrl($chunkResponse->type, $chunkResponse->key);
            }
        } else {
            // 绝对路径
            $chunksDir = $parentDir . DIRECTORY_SEPARATOR . $chunkRequest->uploadId;
            if (! file_exists($chunksDir)) {
                try {
                    BaseFileHelper::createDirectory($chunksDir);
                } catch (Exception $e) {
                    // 并发忽略错误
                }
            }

            $chunkRequest->uploadFile->saveAs($chunksDir . DIRECTORY_SEPARATOR . $chunkRequest->chunk);
            $chunkFiles = $this->getFiles($parentDir, $chunkRequest->uploadId);
            if (count($chunkFiles) == $chunkRequest->chunks) {
                // 合并文件
                for ($i = 0; $i < $chunkRequest->chunks; $i ++) {
                    $chunkFile = file_get_contents($chunksDir . DIRECTORY_SEPARATOR . $i);
                    file_put_contents($abKeyFile, $chunkFile, FILE_APPEND);
                }

                $chunkResponse->isCompleted = true;
                $chunkResponse->eTag = md5_file($abKeyFile);
                $chunkResponse->url = $this->getKeyUrl($chunkResponse->type, $chunkResponse->key);
            }
        }
        return $chunkResponse;
    }

    /**
     *
     * {@inheritdoc}
     * @see \dungang\storage\IDriver::delete()
     */
    public function delete($key)
    {
        $file = BaseFileHelper::normalizePath(ltrim($key, '/\\'), '/');
        $dir = BaseFileHelper::normalizePath(ltrim($this->uploadDir, '/\\'), '/');
        $prefix = substr($file, 0, strlen($dir));
        if (strcasecmp($prefix, $dir) == 0) {
            $path = $this->_abBaseDir . DIRECTORY_SEPARATOR . $file;
            return @unlink($path);
        }
        return false;
    }

    /**
     *
     * @param int|string $start
     * @param int $size
     * @return ListResponse
     */
    public function listFiles($start = 0, $size = 10)
    {
        $files = $this->getFiles($this->_abBaseDir, $this->uploadDir);
        $len = count($files);
        $response = new ListResponse();
        if ($len) {
            $end = $start + $size;
            for ($i = min($end, $len) - 1, $list = array(); $i < $len && $i >= 0 && $i >= $start; $i --) {
                $list[] = $files[$i];
            }
            $response->list = $list;
            $response->start = $start;
            $response->total = $len;
            $response->size = $size;
        }
        return $response;
    }
}