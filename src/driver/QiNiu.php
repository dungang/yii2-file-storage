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
use dungang\storage\ListResponse;
use Qiniu\Config;
use dungang\storage\ChunkRequest;
use dungang\storage\ChunkResponse;

/**
 * 上传指定块的一片数据，具体数据量可根据现场环境调整。同一块的每片数据必须串行上传
 *
 * @author dungang
 *        
 */
class QiNiu extends Driver
{

    public $_driverName = 'qiniu';

    /**
     *
     * @var string OSS Bucket
     */
    public $bucket;

    public $accessKey;

    public $secretKey;

    public $extraData;

    /**
     *
     * @var Auth
     */
    protected $auth;

    /**
     * 可以添加行为来初始化config,如果config为空则通过 如：params[storage][oss]获取
     */
    public function init()
    {
        parent::init();
        $this->auth = new Auth($this->accessKey, $this->secretKey);
    }

    /**
     *
     * {@inheritdoc}
     * @see \dungang\storage\IDriver::initUpload()
     */
    public function initUpload($initRequest)
    {
        $extension = $this->fileExtension($initRequest->name);
        $res = new InitResponse();
        $res->key = $this->normalizeWebPath($this->uploadDir . DIRECTORY_SEPARATOR . $this->dirSuffix . DIRECTORY_SEPARATOR . md5($res->uploadId . $initRequest->timestamp) . '.' . $extension);
        $res->uploadId = $this->auth->uploadToken($this->bucket);
        ;
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
        $chunkResponse->extraData = $chunkRequest->extraData;

        $chunkResponse->extension = $this->fileExtension($chunkRequest->name);
        if (empty($chunkRequest->key)) {
            $chunkResponse->key = $this->normalizeWebPath($this->uploadDir . DIRECTORY_SEPARATOR . $this->dirSuffix . DIRECTORY_SEPARATOR . md5(self::guid()) . '.' . $chunkResponse->extension);
        } else {
            $chunkResponse->key = $chunkRequest->key;
        }

        // 没有分片
        if (null == $chunkRequest->chunks || $chunkRequest->chunks == 0) {
            $manager = new UploadManager();
            $rst = $manager->putFile($chunkRequest->uploadId, $chunkRequest->key, $chunkRequest->uploadFile->tempName, null, $chunkRequest->type);
            if (empty($rst['error'])) {
                $chunkResponse->eTag = $rst[0]['hash'];
                $chunkResponse->isCompleted = true;
                $chunkResponse->url = $this->getKeyUrl($chunkResponse->type, $chunkResponse->key);
            }
        } else if ($chunkRequest->uploadFile->size <= Config::BLOCK_SIZE) {

            $resumeUploader = new ResumeUploader($chunkRequest->uploadId, $chunkRequest->key, $chunkRequest->uploadFile->size, $chunkRequest->type);

            if ($partIndex < $this->chunks && ! $resumeUploader->checkBlockSize($this->chunkFileSize)) {
                $chunkResponse->isOk = false;
                $chunkResponse->error = '分片块的大小必须是4M，除最后一块';
            } else {
                $part = file_get_contents($chunkRequest->uploadFile->tempName);
                $partId = $resumeUploader->genPartId($part);

                $rst = $resumeUploader->uploadPart($partId, $part, strlen($part));

                if (empty($chunkResponse->extraData['contexts'])) {
                    $chunkResponse->extraData['contexts'] = [];
                }
                if (! isset($rst['error'])) {

                    $chunkResponse->extraData['contexts'][$this->chunk] = $rst['ctx'];

                    if (($chunkRequest->chunk + 1) == intval($chunkRequest->chunks)) {
                        $rst = $resumeUploader->completePartUpload($chunkResponse->extraData['contexts']);
                        if (! empty($rst['error'])) {
                            $chunkResponse->isOk = false;
                            $chunkResponse->error = $rst['error'];
                        } else {
                            $chunkResponse->eTag = $rst['hash'];
                            $chunkResponse->isCompleted = true;
                            $chunkResponse->url = $this->getKeyUrl($chunkResponse->type, $chunkResponse->key);
                        }
                    }
                } else {
                    $chunkResponse->isOk = false;
                    $chunkResponse->error = $rst['error'];
                }
            }
        } else {
            $chunkResponse->isOk = false;
            $chunkResponse->error = '除了最后一块Part，其他Part的大小不能大于4M';
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
        $bucketManager = new BucketManager($this->auth);
        if ($bucketManager->delete($this->bucket, $key)) {
            return false;
        }
        return true;
    }

    /**
     *
     * @param
     *            mix
     * @param int $size
     * @return ListResponse
     */
    public function listFiles($start = null, $size = 10)
    {
        if ($start === 0)
            $start = null;
        $response = new ListResponse();
        $response->size = $size;

        $bucketManager = new BucketManager($this->auth);
        list ($items, $maker, $error) = $bucketManager->listFiles($this->bucket, $this->uploadDir, $start, $size);

        $response->list = $this->formatListObject($items);
        $response->start = $maker;

        return $response;
    }
}