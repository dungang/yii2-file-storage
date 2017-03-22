<?php
/**
 * Author: dungang
 * Date: 2017/3/22
 * Time: 11:41
 */

namespace dungang\storage\driver\qiniu;


use Qiniu\Config;
use Qiniu\Http\Client;
use Qiniu\Http\Error;

/**
 * 重构qiniu的分片上传类
 * 适用的业务场景为，客户端已经分片，而不是在服务端分片
 *
 * ps：qiniu的分片上传类是在文件已经完整的上传到服务端之后，在分片上传到qiniu
 *
 * @link http://developer.qiniu.com/docs/v6/api/reference/up/mkblk.html
 * @link http://developer.qiniu.com/docs/v6/api/reference/up/mkfile.html
 */
final class ResumeUploader
{
    /**
     * @var string
     */
    private $upToken;

    /**
     * @var string
     */
    private $key;

    /**
     * @var int
     */
    private $size;

    /**
     * @var array
     */
    private $params;
    /**
     * @var string string
     */
    private $mime;

    /**
     * @var string
     */
    private $host;

    /**
     * @var string
     */
    private $currentUrl;
    /**
     * @var null|Config
     */
    private $config;

    /**
     * ResumeUploader constructor.

     * 上传二进制流到七牛
     *
     * @link http://developer.qiniu.com/docs/v6/api/overview/up/response/vars.html#xvar
     *
     * @param $upToken    string 上传凭证
     * @param $key        string  上传文件名
     * @param $size       int 上传流的大小
     * @param $params     array 自定义变量
     * @param $mime       string 上传数据的mimeType
     * @param $config     Config|null
     * @throws \Exception
     */
    public function __construct(
        $upToken,
        $key,
        $size,
        $mime,
        $params=[],
        $config=null
    ) {
        $this->upToken = $upToken;
        $this->key = $key;
        $this->size = $size;
        $this->params = $params;
        $this->mime = $mime;
        if ($config == null) {
            $this->config = new Config();
        } else {
            $this->config = $config;
        }

        list($upHost, $err) = $this->config->zone->getUpHostByToken($upToken);
        if ($err != null) {
            throw new \Exception($err, 1);
        }
        $this->host = $upHost;
    }

    /**
     * 块大小，每块均为4MB（1024*1024*4），最后一块大小不超过4MB。
     * @param $size
     * @return bool
     */
    public function checkBlockSize($size)
    {
        if ($size != Config::BLOCK_SIZE) {
            return false;
        }
        return true;
    }

    public function genPartId($data)
    {
        return \Qiniu\crc32_data($data);
    }

    public function uploadPart($partId,$data,$partSize)
    {
        $response = $this->makeBlock($data, $partSize);
        $ret = null;
        if ($response->ok() && $response->json() != null) {
            $ret = $response->json();
        }
        if ($response->statusCode < 0) {
            list($bakHost, $err) = $this->config->zone->getBackupUpHostByToken($this->upToken);
            if ($err != null) {
                return array('error'=>$err->message());
            }
            $this->host = $bakHost;
        }
        if ($response->needRetry() || !isset($ret['crc32']) || $partId != $ret['crc32']) {
            $response = $this->makeBlock($data, $partSize);
            $ret = $response->json();
        }

        if (! $response->ok() || !isset($ret['crc32'])|| $partId != $ret['crc32']) {
            return array('error'=> (new Error($this->currentUrl, $response))->message());
        }

        return $ret;
    }

    public function completePartUpload($contexts)
    {
        $url = $this->fileUrl();
        $body = implode(',', $contexts);
        $response = $this->post($url, $body);
        if ($response->needRetry()) {
            $response = $this->post($url, $body);
        }
        if (! $response->ok()) {
            return array('error'=>new Error($this->currentUrl, $response));
        }
        return $response->json();
    }

    /**
     * 创建块
     */
    private function makeBlock($block, $blockSize)
    {
        $url = $this->host . '/mkblk/' . $blockSize;
        return $this->post($url, $block);
    }

    public function fileUrl()
    {
        $url = $this->host . '/mkfile/' . $this->size;
        $url .= '/mimeType/' . \Qiniu\base64_urlSafeEncode($this->mime);
        if ($this->key != null) {
            $url .= '/key/' . \Qiniu\base64_urlSafeEncode($this->key);
        }
        if (!empty($this->params)) {
            foreach ($this->params as $key => $value) {
                $val =  \Qiniu\base64_urlSafeEncode($value);
                $url .= "/$key/$val";
            }
        }
        return $url;
    }

    private function post($url, $data)
    {
        $this->currentUrl = $url;
        $headers = array('Authorization' => 'UpToken ' . $this->upToken);
        return Client::post($url, $data, $headers);
    }
}
