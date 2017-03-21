<?php
/**
 * Created by PhpStorm.
 * User: dungang
 * Date: 2017/3/21
 * Time: 16:46
 */

namespace dungang\storage\components;


use OSS\OssClient;
use yii\helpers\BaseFileHelper;

/**
 * Class AliYunOSS
 * @package dungang\storage\components
 */
class AliYunOSS extends Storage
{

    const CONTENT = 'Content';
    const BUCKET = 'Bucket';
    const KEY = 'Key';
    const UPLOAD_ID = 'UploadId';
    const PART_NUMBER = 'PartNumber';
    const PART_SIZE = 'PartSize';
    const PART_ETAG = 'ETag';
    const PART_ETAGS = 'PartETags';

    const ACCESS_KEY_ID = 'AccessKeyId';
    const ACCESS_KEY_SECRET = 'AccessKeySecret';
    const ENDPOINT = 'Endpoint';

    public $paramKey = 'oss';

    public $config;

    /**
     * @var \OSS\OSSClient
     */
    protected $client;

    /**
     * @var string OSS Bucket
     */
    public $bucket;

    /**
     *  可以添加行为来初始化config,如果config为空则通过 如：params[oss]获取
     */
    public function initUploader()
    {
        if (empty($this->config)) {
            $this->config = \Yii::$app->params[$this->paramKey];
        }
        $this->client = new OssClient(
            $this->config['AccessKeyId'],
            $this->config['AccessKeySecret'],
            $this->config['EndPoint']
        );

        $this->bucket = $this->config['Bucket'];
        $this->extraData = json_decode($this->extraData,JSON_UNESCAPED_UNICODE);

        if ($this->chunks > 0) {
            $this->chunked = true;
        }
    }

    /**
     * @return bool|string
     */
    public function writeFile()
    {
        $fileName = md5($this->guid . $this->id);

        $file = $fileName . '.' . $this->file->extension;

        $dir = $this->saveDir .DIRECTORY_SEPARATOR. date('Y-m-d');

        $partNumber = 1 + $this->chunk;
        //aliyun oss 路径分隔符用'/'
        $key = BaseFileHelper::normalizePath($dir . DIRECTORY_SEPARATOR . $file,'/');

        //除了最后一块Part，其他Part的大小不能小于100KB，否则会导致在调用CompleteMultipartUpload接口的时候失败
        $minChunkSize = 100 * 1024;
        if(intval($this->size) >= $minChunkSize && $this->chunked) {
            if ($this->chunk == 0 ) {
                $uploadId = $this->client->initiateMultipartUpload(
                    $this->bucket, $key);
                if ($uploadId) {
                    $this->extraData[OssClient::OSS_UPLOAD_ID] = $uploadId;
                }
            }
            $content = file_get_contents($this->file->tempName);
            $eTag = $this->client->uploadPart(
                    $this->bucket, $key, $this->extraData[self::UPLOAD_ID],
                    [
                        OssClient::OSS_CONTENT => $content,
                        OssClient::OSS_PART_NUM => $partNumber,
                        OssClient::OSS_PART_SIZE=> $this->chunkFileSize
                    ]);

            if ($eTag){

                $this->extraData[self::PART_ETAGS][] = [
                    self::PART_NUMBER=>$partNumber,
                    self::PART_ETAG => $eTag
                ];

                if ($partNumber == $this->chunks) {
                    $this->client->completeMultipartUpload(
                        $this->bucket, $key, $this->extraData[self::UPLOAD_ID],
                        $this->extraData[self::PART_ETAGS]);
                }
                return $key;
            }

        } else {
            $content = file_get_contents($this->file->tempName);
            $eTag = $this->client->putObject(
                $this->bucket,$key,$content,
                [OssClient::OSS_LENGTH => $this->chunkFileSize]
            );

            if ($eTag){
                return $key;
            }
        }
        return false;
    }

    public function deleteFile($file)
    {
        $key = BaseFileHelper::normalizePath(ltrim($file,'/\\'),'/');
        $this->client->deleteObject( $this->bucket, $key);
        return true;
    }
}