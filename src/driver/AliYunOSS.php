<?php
/**
 * Created by PhpStorm.
 * User: dungang
 * Date: 2017/3/21
 * Time: 16:46
 */

namespace dungang\storage\driver;


use OSS\OssClient;
use yii\helpers\BaseFileHelper;
use dungang\storage\Driver;

/**
 * Class AliYunOSS
 * @package dungang\storage\components
 */
class AliYunOSS extends Driver
{

    const PART_ETAGS = 'PartETags';

    public $_driverName = 'oss';

    /**
     * @var \OSS\OSSClient
     */
    protected $client;

    /**
     * @var string OSS Bucket
     */
    public $bucket;

    /**
     *  可以添加行为来初始化config,如果config为空则通过 如：params[storage][oss]获取
     */
    public function initUploader()
    {
        $this->client = new OssClient(
            $this->config['AccessKeyId'],
            $this->config['AccessKeySecret'],
            $this->config['EndPoint']
        );
        $this->bucket = $this->config['Bucket'];
        $this->saveDir = $this->_driverName;
    }

    /**
     * @return bool|string
     */
    public function writeFile()
    {
        $file = $this->guid . '.' . $this->file->extension;

        $dir = $this->saveDir . DIRECTORY_SEPARATOR . date('Y-m-d');

        $partNumber = 1 + $this->chunk;
        //aliyun oss 路径分隔符用'/'
        $object = BaseFileHelper::normalizePath($dir . DIRECTORY_SEPARATOR . $file, '/');

        //除了最后一块Part，其他Part的大小不能小于100KB，否则会导致在调用CompleteMultipartUpload接口的时候失败
        $minChunkSize = 100 * 1024;
        if (intval($this->size) >= $minChunkSize && $this->chunked) {
            $this->triggerEvent = false;
            if ($this->chunk == 0) {
                $uploadId = $this->client->initiateMultipartUpload(
                    $this->bucket, $object);
                if ($uploadId) {
                    $this->extraData[OssClient::OSS_UPLOAD_ID] = $uploadId;
                }
            }
            $this->_eTag = $this->client->uploadPart(
                $this->bucket,
                $object,
                $this->extraData[OssClient::OSS_UPLOAD_ID],
                [
                    OssClient::OSS_FILE_UPLOAD => $this->file->tempName,
                    OssClient::OSS_PART_NUM => $partNumber,
                    OssClient::OSS_LENGTH => $this->chunkFileSize
                ]);
            if ($this->_eTag) {

                $this->extraData[self::PART_ETAGS][] = [
                    'PartNumber' => $partNumber,
                    'ETag' => $this->_eTag
                ];

                if ($partNumber == $this->chunks) {
                    $this->_eTag = $this->client->completeMultipartUpload(
                        $this->bucket,
                        $object,
                        $this->extraData[OssClient::OSS_UPLOAD_ID],
                        $this->extraData[self::PART_ETAGS]
                    );
                    if ($this->_eTag) {
                        $this->triggerEvent = true;
                        return $this->response($object);
                    } else {
                        return $this->response(null,500,'Server error');
                    }
                }
                return $this->response($object);
            } else {
                return $this->response(null,500,'Server error');
            }

        } else {
            $content = file_get_contents($this->file->tempName);
            $this->_eTag = $this->client->putObject(
                $this->bucket,
                $object,
                $content,
                [OssClient::OSS_LENGTH => $this->chunkFileSize]
            );

            if ($this->_eTag) {
                return $this->response($object);
            } else {
                return $this->response(null,500,'Server error');
            }
        }
    }

    public function deleteFile($object)
    {
        $this->client->deleteObject($this->bucket, $object);
        return true;
    }

    public function getSourceUrl($object)
    {
        return $this->config['sourceBaseUrl'] . '/' . ltrim($object, '/');
    }

    public function getBindUrl($object)
    {
        return $this->config['bindBaseUrl'] . '/' . ltrim($object, '/');
    }


}