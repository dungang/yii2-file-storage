<?php
/**
 * Author: dungang
 * Date: 2017/3/22
 * Time: 10:47
 */

namespace dungang\storage\driver;


use dungang\storage\Driver;
use dungang\storage\driver\qiniu\ResumeUploader;
use Qiniu\Auth;
use Qiniu\Storage\BucketManager;
use Qiniu\Storage\UploadManager;
use yii\helpers\BaseFileHelper;

class QiNiu extends Driver
{
    public $paramKey = 'qiniu';

    public $config;

    /**
     * @var string OSS Bucket
     */
    protected $bucket;

    /**
     * @var Auth
     */
    protected $auth;

    /**
     *  可以添加行为来初始化config,如果config为空则通过 如：params[oss]获取
     */
    public function initUploader()
    {
        if (empty($this->config)) {
            $this->config = \Yii::$app->params[$this->paramKey];
        }

        $this->bucket = $this->config['Bucket'];
        $this->auth = new Auth(
            $this->config['AccessKey'],
            $this->config['SecretKey']
        );

        $this->extraData = json_decode($this->extraData, JSON_UNESCAPED_UNICODE);

        if ($this->chunks > 0 && $this->size > $this->chunkSize) {
            $this->chunked = true;
        }
    }


    public function writeFile()
    {
        if (empty($this->extraData['upToken'])) {
            $token = $this->auth->uploadToken($this->bucket);
            $this->extraData['upToken'] = $token;
        } else {
            $token = $this->extraData['upToken'];
        }

        $fileName = md5($this->guid . $this->id);

        $file = $fileName . '.' . $this->file->extension;

        $dir = $this->saveDir . DIRECTORY_SEPARATOR . date('Y-m-d');

        //aliyun oss 路径分隔符用'/'
        $object = BaseFileHelper::normalizePath($dir . DIRECTORY_SEPARATOR . $file, '/');
        $partIndex =  $this->chunk + 1;
        //除了最后一块Part，其他Part的大小不能小于100KB，否则会导致在调用CompleteMultipartUpload接口的时候失败
        $minChunkSize = 100 * 1024;
        if (intval($this->size) >= $minChunkSize && $this->chunked) {

            $resumeUploader = new ResumeUploader(
                $token,
                $object,
                $this->size,
                $this->file->type
            );

            if ($partIndex < $this->chunks && !$resumeUploader->checkBlockSize($this->chunkFileSize)) {
                return $this->response(null,500,'当前快的大小：'.$this->chunkSize.'分片块的大小必须是4M，除最后一块');
            }


            $part = file_get_contents($this->file->tempName);
            $partId = $resumeUploader->genPartId($part);

            $rst = $resumeUploader->uploadPart($partId,$part,strlen($part));

            if (empty($this->extraData['contexts'])) {
                $this->extraData['contexts'] = [];
            }

            if (!isset($rst['error'])) {

                $this->extraData['contexts'][$this->chunk] = $rst['ctx'];

                if (($this->chunk + 1) == intval($this->chunks)) {
                    $rst = $resumeUploader->completePartUpload($this->extraData['contexts']);
                    if(!empty($rst['error'])) {
                        return $this->response(null,500,$rst['error']);
                    }
                }
                return $this->response($object);
            } else {
                return $this->response(null,500,$rst['error']);
            }

        } else {
            $manager = new UploadManager();
            $rst = $manager->putFile(
                $token,
                $object,
                $this->file->tempName,
                null,
                $this->file->type
            );

            if (empty($rst['error'])) {
                return $this->response($object);
            }
            return $this->response(null,500,$rst['error']);
        }
    }

    public function deleteFile($file)
    {
        $object = BaseFileHelper::normalizePath(ltrim($file, '/\\'), '/');
        $bucketManager = new BucketManager($this->auth);
        if($bucketManager->delete($this->bucket, $object)) {
            return false;
        }
        return true;
    }


    public function getSourceUrl($object)
    {
        return $this->config['sourceBaseUrl'] .  '/' . ltrim($object,'/');
    }

    public function getBindUrl($object)
    {
        return $this->config['bindBaseUrl'] .  '/' . ltrim($object,'/');
    }


}