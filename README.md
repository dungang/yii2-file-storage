# file-storage
文件存储集合，本地存储，阿里云oss，七牛

> 安装

```
composer require dungang/yii2-file-storage

```

> 使用方法

配置为一个对象的属性

```
'webuploader'=>[
    'class'=>'dungang\webuploader\Module',
//下面是默认配置    
//    [
        /**
         * @var array 访问角色 默认是登录用户才可以
         */
//        role => ['@'],
         
        /**
         * @var string 上传文件的驱动
         */
//        'driver' => 'dungang\storage\driver\Local',
//        'driver' => [
                'class'=>'dungang\storage\driver\AliYunOSS',
                //Yii::$app->params['oss']
                'paramKey'=>'oss'
            ],
    
        /**
         * @var string 上传文件保存的相对路径
         */
//        'saveDir' => '/upload/webuploader',
    
        /**
         * @var array 接受的文件类型
         */
//        'accept' => ['gif','jpg','png','bmp','docx','doc','ppt','xsl','rar','zip','7z']
//    ]
],
    
```

> 驱动扩展

所有的驱动必须继承 `dungang\storage\Driver` 类.

如：实现本地文件的驱动

```
<?php
/**
 * Created by PhpStorm.
 * User: dungang
 * Date: 2017/3/2
 * Time: 11:24
 */

namespace dungang\storage\components;

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
                if ($this->chunk === 0 ) {
                    $position = 0;
                    if (file_exists($targetFile)) {
                        @unlink($targetFile);
                    }
                } else {
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
        return \Yii::$app->basePath . '/' . ltrim($object,'/');
    }

    public function getBindUrl($object)
    {
        return $this->getSourceUrl($object);
    }


}
```

> 协议

MIT License

Copyright (c) 2017 dungang

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
