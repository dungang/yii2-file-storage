<?php
/**
 * Created by PhpStorm.
 * User: Lenovo
 * Date: 2017/3/3
 * Time: 16:46
 */

namespace dungang\storage\components;


use yii\helpers\BaseFileHelper;

/**
 * @var $result \Aliyun\OSS\Models\InitiateMultipartUploadResult
 * @var $partResult  \Aliyun\OSS\Models\UploadPartResult
 * @var $clientClass  string| \Aliyun\OSS\OSSClient
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
     * @var \Aliyun\OSS\OSSClient
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
        $JohnLuiOSS = '\JohnLui\AliyunOSS\AliyunOSS';
        $clientClass= '\Aliyun\OSS\OSSClient';
        //由于JohnLuiOSS的ossClient不是公开属性，所以先实例化，加载文件
        $JohnLuiOSS::boot(
            $this->config['EndPoint'],
            $this->config['AccessKeyId'],
            $this->config['AccessKeySecret']
        );
        //再次实例化 OssClient
        $this->client = $clientClass::factory([
            self::ENDPOINT => $this->config['EndPoint'],
            self::ACCESS_KEY_ID       => $this->config['AccessKeyId'],
            self::ACCESS_KEY_SECRET   => $this->config['AccessKeySecret'],
        ]);

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
                $result = $this->client->initiateMultipartUpload([
                    self::BUCKET =>$this->bucket,
                    self::KEY => $key
                ]);
                if ($result) {
                    $this->extraData[self::UPLOAD_ID] = $result->getUploadId();
                }
            }
            $handle = fopen($this->file->tempName, 'r');
            $objResult = $this->client->uploadPart([
                    self::BUCKET => $this->bucket,
                    self::KEY => $key,
                    self::UPLOAD_ID => $this->extraData[self::UPLOAD_ID],
                    self::CONTENT => $handle,
                    self::PART_NUMBER => $partNumber,
                    self::PART_SIZE => $this->chunkFileSize
            ]);
            fclose($handle);
            if ($objResult && $objResult->getETag()){

                $this->extraData[self::PART_ETAGS][] = [
                    self::PART_NUMBER=>$partNumber,
                    self::PART_ETAG => $objResult->getETag()
                ];

                if ($partNumber == $this->chunks) {
                    $this->client->completeMultipartUpload([
                        self::BUCKET => $this->bucket,
                        self::KEY => $key,
                        self::UPLOAD_ID => $this->extraData[self::UPLOAD_ID],
                        self::PART_ETAGS => $this->extraData[self::PART_ETAGS]
                    ]);
                }
                return $key;
            }

        } else {
            $handle = fopen($this->file->tempName, 'r');
            $objResult = $this->client->putObject([
                self::BUCKET => $this->bucket,
                self::KEY => $key,
                self::CONTENT => $handle,
                'ContentLength' => $this->chunkFileSize,
            ]);
            fclose($handle);

            if ($objResult && $objResult->getETag()){
                return $key;
            }
        }
        return false;
    }

    public function deleteFile($file)
    {
        $key = BaseFileHelper::normalizePath(ltrim($file,'/\\'),'/');
        $this->client->deleteObject([
            self::BUCKET => $this->bucket,
            self::KEY => $key,
        ]);
        return true;
    }
}