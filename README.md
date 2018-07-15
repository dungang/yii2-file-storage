# file-storage
文件存储集合，本地存储，阿里云oss，七牛。

##  重构

阿里云和七牛在处理分片合并的时候是需要API的明确的告诉之前的分片信息的。
之前在处理阿里云和七牛的分片上传的时候，如果是文件不大，分片不多，可以正常。合并的参数是通过请求响应来合传递的。而且是强制约束按照时间顺序单线程执行，效率不高。

重构后，支持并发多线程请求上传分片数据。但是七牛还是单线程顺序执行（否则会导致合并参数丢失或覆盖）

> 安装

```shell
composer require dungang/yii2-file-storage

```

## 关于uploadId

* 全局唯一
* 最好每次打开上传的窗口重新生成
* 如果网站的上传图片并发不高，可以使用`UUID`来生成
* 如果并发高可以考虑面向服务的全局id生成器，比如`snowflake算法`

## 使用方法

> 1.配置控制器

- 本地存储

```php

Class LocalController extends Controller {
	public function actions(){
		return [
			'init-upload'=>[
				'class'=>'dungang\storage\InitAction'
				'
			],
			'chunk-upload'=>[
				'class'=>'dungang\storage\ChunkUploadAction'
			],
			'delete'=>[
				'class'=>'dungang\storage\DeleteAction'
			],
		];
	}
}
```

- 使用阿里云

```php
class OssController extends Controller {
	private $storageConfig = [
		'class'=>'dungang\storage\driver\AliyunOSS',
		'accessKey'=>'xxxx',
		'accessSecret'=>'xxxxxx',
		'endpoit'=>'http://hangzhou.oss.aliyun.com',
		'bucket'=>'bucketName',
		'imageBaseUrl'=>'http://bucket.hangzhou.oss.aliyun.com',
		'fileBaseUrl'=>'http://bucket.hangzhou.oss.aliyun.com',
		'dirSuffix'=> date('Y-m-d')
	];
	public function actions(){
		return [
			'init-upload'=>[
				'class'=>'dungang\storage\InitAction',
				'storageConfig'=>$this->storageConfig,
			],
			'chunk-upload'=>[
				'class'=>'dungang\storage\ChunkUploadAction',
				'storageConfig'=>$this->storageConfig
			],
			'delete'=>[
				'class'=>'dungang\storage\DeleteAction',
				'storageConfig'=>$this->storageConfig
			],
		];
	}
}
```

> 2. 获取文件上传的服务器参数


|参数	|required	|说明											|
|-------|:---------	|:----------------------------------------------|
|name	|必须		|原始文件名称,包括文件后缀							|
|type	|必须		|文件类型,image/jpeg								|
|timestamp|必须		|时间戳，同一个页面可以使用同一个时间戳			|

前端以fex-webuploader 为例

```javascript
//当前页面，生成时间戳
int timestamp = Math.round(new Date().getTime()/1000);
//每次发送文件的时候，获取上传的初始化参数
WebUploader.Uploader.register({'before-send-file':'initUpload'},{
	initUpload:function(file) {
		var deferred = $.ajax({
			method:'post',
			url:'/?r=oss/init-upload',
			dataType:'json',
			data: {
				name: file.name,
				type: file.type,
				timestamp: timestamp
			}
		}).then(function(res){
			console.log(res);
			file.uploadId = res.uploadId
			file.key = res.key
		});
		return deferred.promise();
	}
});
```

后端

```php
'init-upload'=>[
	'class'=>'dungang\storage\InitAction',
	'storageConfig'=>$this->storageConfig,
	'
],
```
结果类似
```json
{
	"uploadId":"370af9d2-c565-4b8e-9fa4-186d150affab",
	"key":"uploader\\test\\905cd7faa06cee41e0deb1a0502a868c.jpg"
}
```

> 3.处理上传的数据

|参数	|required	|说明											|
|-------|:---------	|:----------------------------------------------|
|uploadId|必须		|每次发起上传文件前获取一个服务器`全局唯一`id，每个文件的uploadId是不同的|
|key	|必须		|是在服务器分配uploadId的时候同步返回的，是文件最终存储的路径|
|name	|必须		|原始文件名称,包括文件后缀							|
|size	|必须		|原始文件的大小									|
|type	|必须		|文件类型,image/jpeg								|
|chunks	|分片时必须	|分片总数量										|
|chunk	|分片时必须	|本次请求的分片的序号，从0开始						|

前端以fex-webuploader 为例

```javascript
// 每个分片 发送之前
// 很重要, 配合最开始注册的 promise . 'before-send-file':'initUpload'
uploader.on('uploadBeforeSend',function(block,data,headers){
	data.uploadId = block.file.uploadId;
	data.key = block.file.key;
});
```

后端

```php
'chunk-upload'=>[
	'class'=>'dungang\storage\ChunkUploadAction',
	'storageConfig'=>array_merge([
		'behaviors'=>[
			'saveImageInfo'=>'可以配置自己实现的行为,比如保存图片的信息，通过event->payload获取ChunkResponse对象实例'
		],
	],$this->storageConfig)
],
```
结果，以实际获得的结果为准
```json
{
	"isOk":true,
	"error":"",
	"key":"uploader\\test\\905cd7faa06cee41e0deb1a0502a868c.jpg",
	"uploadId":"370af9d2-c565-4b8e-9fa4-186d150affab",
	"completed":false //表示还没完成此文件所有的分片上传
}


{
	"isOk":true,
	"error":"",
	"key":"uploader\\test\\905cd7faa06cee41e0deb1a0502a868c.jpg",
	"uploadId":"370af9d2-c565-4b8e-9fa4-186d150affab",
	"completed":true //表示已完成此文件所有的分片上传
}
```


## 七牛存储必须设置为4m 所以 至少是4m
upload_max_filesize = 4M  
post_max_size = 8M

```

## 注意事项

- 本模块对qiniu存储的分片上传功能做了重构。保留之前的类。
    * qiniu的分片上传的前提是文件必须上传到服务器之后，在分片传到qiniu服务器
    * 重构后的分片上传时在浏览器端（客户端）已经分片，通过服务器转发到qiniu的服务器
- 当使用qiniu对象存储的的分片上传的功能时，分片的大小必须是4m。


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
