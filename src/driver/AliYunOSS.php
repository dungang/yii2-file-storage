<?php
namespace dungang\storage\driver;

use dungang\storage\Driver;
use dungang\storage\ChunkRequest;
use dungang\storage\InitRequest;
use dungang\storage\ChunkResponse;
use dungang\storage\ListResponse;
use OSS\OssClient;
use dungang\storage\InitResponse;

class AliyunOSS extends Driver
{

    /**
     * \OSS\OSSClient
     *
     * @var \OSS\OSSClient
     */
    protected $ossClient;

    public $bucket;

    public $accessKey;

    public $accessSecret;

    public $endpoint;

    public $ossChunkMinSize = 100 * 1024;

    public function init()
    {
        parent::init();
        $this->ossClient = new OssClient($this->accessKey, $this->accessSecret, $this->endpoint);
    }

    public function initUpload($initRequest)
    {
        $extension = $this->fileExtension($initRequest->name);
        $res = new InitResponse();
        $res->key = $this->normalizeWebPath($this->uploadDir . DIRECTORY_SEPARATOR . $this->dirSuffix . DIRECTORY_SEPARATOR . md5($res->uploadId . $initRequest->timestamp) . '.' . $extension);
        $res->uploadId = $this->ossClient->initiateMultipartUpload($this->bucket, $res->key);
        return $res;
    }

    /**
     *
     * @param ChunkRequest $chunkRequest
     * @return ChunkResponse
     * {@inheritdoc}
     * @see \dungang\storage\IDriver::write()
     */
    public function write($chunkRequest)
    {
        $chunkResponse = new ChunkResponse();
        $chunkResponse->uploadId = $chunkRequest->uploadId;
        $chunkResponse->originName = $chunkRequest->name;
        $chunkResponse->type = $chunkRequest->type;
        $chunkResponse->size = $chunkRequest->size;

        $chunkResponse->extension = $this->fileExtension($chunkRequest->name);
        if (empty($chunkRequest->key)) {
            $chunkResponse->key = $this->normalizeWebPath($this->uploadDir . DIRECTORY_SEPARATOR . $this->dirSuffix . DIRECTORY_SEPARATOR . md5(self::guid()) . '.' . $chunkResponse->extension);
        } else {
            $chunkResponse->key = $chunkRequest->key;
        }

        // 没有分片
        if (null == $chunkRequest->chunks || $chunkRequest->chunks == 0) {

            $chunkResponse->eTag = $this->ossClient->putObject($this->bucket, $chunkResponse->key, file_get_contents($chunkRequest->uploadFile->tempName), [
                OssClient::OSS_LENGTH => $this->chunkFileSize
            ]);

            if (null != $chunkResponse->eTag) {
                $chunkResponse->isCompleted = true;
                $chunkResponse->url = $this->getKeyUrl($chunkResponse->type, $chunkResponse->key);
            }
        } else if ($chunkRequest->uploadFile->size >= $this->ossChunkMinSize) {

            $chunkResponse->eTag = $this->ossClient->uploadPart($this->bucket, $chunkResponse->key, $chunkResponse->uploadId, [
                OssClient::OSS_FILE_UPLOAD => $chunkRequest->uploadFile->tempName,
                OssClient::OSS_PART_NUM => $chunkRequest->chunk + 1,
                OssClient::OSS_LENGTH => $chunkRequest->chunkSize
            ]);

            $listPartsInfo = $this->ossClient->listParts($this->bucket, $chunkResponse->key, $chunkResponse->uploadId);

            if ($listPartsInfo && $partInfos = $listPartsInfo->getListPart()) {
                
                if(count($partInfos) == $chunkRequest->chunks ){
                    $parts = [];
                    foreach ($partInfos as $partInfo) {
                        $parts[] = [
                            'PartNumber' => $partInfo->getPartNumber(),
                            'ETag' => $partInfo->getETag(),
                        ];
                    }
                    $chunkResponse->eTag = $this->ossClient->completeMultipartUpload($this->bucket, $chunkResponse->key, $chunkResponse->uploadId, $parts);
                    if (null != $chunkResponse->eTag) {
                        $chunkResponse->isCompleted = true;
                        $chunkResponse->url = $this->getKeyUrl($chunkResponse->type, $chunkResponse->key);
                    }
                }
            }
        } else {
            $chunkResponse->isOk = false;
            $chunkResponse->error = '除了最后一块Part，其他Part的大小不能小于100KB';
        }
        return $chunkResponse;
    }

    public function delete($key)
    {
        $this->ossClient->deleteObject($this->bucket, $key);
        return true;
    }

    public function listFiles($start = 0, $size = 10)
    {
        if ($start === 0)
            $start = null;
        $response = new ListResponse();
        $response->size = $size;
        try {
            /* @var $listInfo \OSS\Model\ObjectListInfo*/
            $listInfo = $this->ossClient->listObjects($this->bucket, [
                'max-keys' => $size,
                'prefix' => $this->uploadDir,
                'marker' => $start
            ]);

            $response->list = $listInfo->getObjectList();
            $response->start = $listInfo->getNextMarker();
        } catch (\Exception $e) {}
        return $result;
    }
}

