<?php
/**
 * Author: dungang
 * Date: 2017/3/25
 * Time: 11:12
 */

namespace dungang\storage;


trait ActionTrait
{

    /**
     * @var \dungang\storage\Driver
     */
    protected $driverInstance;

    /**
     * @var array 接受的文件类型
     */
    public $accept = ['gif','jpg','png','bmp','docx','doc','ppt','xsl','rar','zip','7z'];

    /**
     * @param $post array
     */
    public function instanceDriver($post)
    {
        $this->driverInstance = Driver::createDefaultDriver();
        $this->driverInstance->loadPostData($post);
        $this->driverInstance->accept = $this->accept;
    }

}