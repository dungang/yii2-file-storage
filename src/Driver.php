<?php
/**
 * Created by PhpStorm.
 * User: Lenovo
 * Date: 2017/3/2
 * Time: 11:19
 */
namespace dungang\storage;

use yii\base\Component;
use yii\helpers\BaseFileHelper;
use yii\web\UploadedFile;
use yii\base\Event;
use phpDocumentor\Reflection\Types\Mixed_;

class InitRequest
{

    /**
     *
     * @var string 文件类型 image/jpeg
     */
    public $type;

    /**
     *
     * @var string 文件名称， xxx.jpg
     */
    public $name;

    /**
     *
     * @var string 客户端生成的时间戳
     */
    public $timestamp;
}

class InitResponse
{

    /**
     * 是否成功
     *
     * @var boolean
     */
    public $isOk = true;

    public $error = '';

    /**
     *
     * @var string 本次上传文件的服务id
     */
    public $uploadId;

    /**
     *
     * @var string 最终要生成的文件路径和名称
     */
    public $key;
}

class ChunkRequest
{

    /**
     *
     * @var string 本次上传文件的服务id
     */
    public $uploadId = '';

    /**
     *
     * @var string 最终要生成的文件路径和名称
     */
    public $key = '';

    /**
     * 原始名称
     *
     * @var string
     */
    public $name = '';

    /**
     * 文件类型
     *
     * @var string
     */
    public $type = '';

    /**
     *
     * @var int chunk总数量
     */
    public $chunks = 1;

    /**
     *
     * @var int 当前chunk编号
     */
    public $chunk = 0;

    /**
     *
     * @var int chunk文件的大小
     */
    public $chunkSize = 0;

    /**
     *
     * @var int 当完整的前文件实际大小
     */
    public $size = 0;

    /**
     *
     * @var UploadedFile
     */
    public $uploadFile;

    /**
     * 额外回传的数据
     *
     * @var array
     */
    public $extraData;
}

class ChunkResponse
{

    /**
     * 是否成功
     *
     * @var boolean
     */
    public $isOk = true;

    public $error = '';

    /**
     *
     * @var string 本次上传文件的服务id
     */
    public $uploadId = '';

    /**
     *
     * @var string 最终要生成的文件路径和名称
     */
    public $key = '';

    /**
     *
     * @var boolean 是否完成
     */
    public $isCompleted = false;

    /**
     * 文件md5签名
     *
     * @var string
     */
    public $eTag;

    /**
     * 文件后缀
     *
     * @var string
     */
    public $extension;

    /**
     * 新名称
     *
     * @var string
     */
    public $name;

    /**
     * 原始名称
     *
     * @var string
     */
    public $originName;

    /**
     * 大小
     *
     * @var number
     */
    public $size;

    /**
     * 文件类型
     *
     * @var string
     */
    public $type;

    /**
     * 显示地址
     *
     * @var string
     */
    public $url;

    /**
     * 额外回传的数据
     *
     * @var array
     */
    public $extraData = null;
}

class ListResponse
{

    public $list = [];

    public $total;

    public $start;

    public $size;
}

interface IDriver
{

    /**
     *
     * @param InitRequest $initRequest
     * @return InitResponse
     */
    public function initUpload($initRequest);

    /**
     *
     * @param ChunkRequest $chunkRequest
     * @return ChunkResponse
     */
    public function write($chunkRequest);

    /**
     * file full relative path
     *
     * @param string $key
     * @return boolean
     */
    public function delete($key);

    /**
     *
     * @param mixed $start
     * @param int $size
     * @return ListResponse
     */
    public function listFiles($start = 0, $size = 10);
}

class StorageEvent extends Event
{

    public $payload;
}

abstract class Driver extends Component implements IDriver
{

    const EVENT_BEFORE_INIT_UPLOADER = 'beforeInitUploader';

    const EVENT_AFTER_INIT_UPLOADER = 'afterInitUploader';

    const EVENT_BEFORE_WRITE_FILE = 'beforeWriteFile';

    const EVENT_AFTER_WRITE_FILE = 'afterWriteFile';

    const EVENT_BEFORE_DELETE_FILE = 'beforeDeleteFile';

    const EVENT_AFTER_DELETE_FILE = 'afterDeleteFile';

    public $uploadDir = 'uploader';

    /**
     * 表单文件参数名称
     *
     * @var string
     */
    public $fileName = 'file';

    /**
     *
     * @var string 目录后缀 比如 2018-06-08
     */
    public $dirSuffix = '';

    public $maxFileSize = 4194304;

    public $accept = [
        'gif',
        'jpg',
        'png',
        'bmp',
        'docx',
        'doc',
        'ppt',
        'xsl',
        'rar',
        'zip',
        '7z'
    ];

    public $imageBaseUrl = '';

    public $fileBaseUrl = '';

    /**
     *
     * @param String $type
     * @param String $key
     */
    protected function getKeyUrl($type, $key)
    {
        if ($this->isImage($type)) {
            return rtrim($this->imageBaseUrl, '/') . '/' . ltrim($key, '/');
        } else {
            return rtrim($this->fileBaseUrl, '/') . '/' . ltrim($key, '/');
        }
    }

    protected function fileExtension($filename)
    {
        return strtolower(substr(strrchr($filename, '.'), 1));
    }

    protected function normalizeWebPath($path)
    {
        return BaseFileHelper::normalizePath($path, '/');
    }

    protected function isImage($type)
    {
        return strpos(strtolower($type), 'image') === false ? false : true;
    }

    /**
     *
     * @return \dungang\storage\InitResponse
     */
    public function initChunkUpload()
    {
        $httpReq = \Yii::$app->request;
        $req = new InitRequest();
        $req->name = $httpReq->post('name');
        $req->type = $httpReq->post('type');
        $req->timestamp = $httpReq->post('timestamp');
        $extension = $this->fileExtension($req->name);
        if (null == $this->accept || (null != $this->accept && ! in_array($extension, $this->accept))) {
            $this->beforeInit($req);
            $initResponse = $this->initUpload($req);
            $this->afterInit($initResponse);
        } else {
            $initResponse = new InitResponse();
            $initResponse->isOk = false;
            $chunkResponse->error = '不允许上传' . $req->type . '的文件';
        }
        return $initResponse;
    }

    /**
     *
     * @return \dungang\storage\ChunkResponse
     */
    public function chunkUpload()
    {
        $httpReq = \Yii::$app->request;
        $uploadFile = UploadedFile::getInstanceByName($this->fileName);
        $chunkRequest = new ChunkRequest();
        $chunkRequest->uploadId = $httpReq->post('uploadId');
        $chunkRequest->chunks = $httpReq->post('chunks');
        $chunkRequest->chunk = $httpReq->post('chunk');
        $chunkRequest->name = $httpReq->post('name');
        $chunkRequest->type = $httpReq->post('type');
        $chunkRequest->size = $httpReq->post('size');
        $chunkRequest->key = $httpReq->post('key');
        if ($httpReq->post('extrData')) {
            $chunkRequest->extraData = json_decode($httpReq->post('extrData'));
        } else {
            $chunkRequest->extraData = null;
        }
        $chunkRequest->chunkSize = $uploadFile->size;
        $chunkRequest->uploadFile = $uploadFile;
        $extension = $this->fileExtension($chunkRequest->name);
        if ($chunkRequest->chunkSize > $this->maxFileSize) {
            $chunkResponse = new ChunkResponse();
            $chunkResponse->uploadId = $chunkRequest->uploadId;
            $chunkResponse->key = $chunkRequest->key;
            $chunkResponse->isOk = false;
            $chunkResponse->error = 'file size is too large';
        } else if (null != $this->accept && ! in_array($extension, $this->accept)) {
            $chunkResponse = new ChunkResponse();
            $chunkResponse->uploadId = $chunkRequest->uploadId;
            $chunkResponse->key = $chunkRequest->key;
            $chunkResponse->isOk = false;
            $chunkResponse->error = '不允许上传' . $chunkResponse->type . '的文件';
        } else {
            $this->beforeWrite($chunkRequest);
            $chunkResponse = $this->write($chunkRequest);
            $this->afterWrite($chunkResponse);
        }
        return $chunkResponse;
    }

    /**
     *
     * @param string $key
     * @return boolean
     */
    public function deleteUpload($key)
    {
        $this->beforeDelete($key);
        $res = $this->delete($key);
        $this->afterDelete($key);
        return $res;
    }

    public function beforeInit($initRequest)
    {
        $this->trigger(self::EVENT_BEFORE_INIT_UPLOADER, new StorageEvent([
            'payload' => $initRequest
        ]));
    }

    public function afterInit($initResponse)
    {
        $this->trigger(self::EVENT_AFTER_INIT_UPLOADER, new E([
            'payload' => $initResponse
        ]));
    }

    public function beforeWrite($chunkRequest)
    {
        $this->trigger(self::EVENT_BEFORE_WRITE_FILE, new StorageEvent([
            'payload' => $chunkRequest
        ]));
    }

    public function afterWrite($chunkResponse)
    {
        $this->trigger(self::EVENT_AFTER_WRITE_FILE, new StorageEvent([
            'payload' => $chunkResponse
        ]));
    }

    public function beforeDelete($key)
    {
        $this->trigger(self::EVENT_BEFORE_DELETE_FILE, new StorageEvent([
            'payload' => $key
        ]));
    }

    public function afterDelete($key)
    {
        $this->trigger(self::EVENT_AFTER_DELETE_FILE, new StorageEvent([
            'payload' => $key
        ]));
    }
}