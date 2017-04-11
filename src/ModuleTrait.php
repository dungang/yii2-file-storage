<?php
/**
 * Author: dungang
 * Date: 2017/4/11
 * Time: 8:42
 */

namespace dungang\storage;


trait ModuleTrait
{
    /**
     * @var array 访问角色 默认是登录用户才可以
     */
    public $role = ['@'];

    /**
     * @var array 接受的文件类型
     */
    public $accept = ['gif','jpg','png','bmp','docx','doc','ppt','xsl','rar','zip','7z'];
}