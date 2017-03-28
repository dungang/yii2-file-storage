<?php
/**
 * Author: dungang
 * Date: 2017/3/25
 * Time: 11:47
 */

namespace dungang\storage;


class File
{
    /**
     * @var string 文件的原始名称
     */
    public $name='';

    /**
     * @var string 文件新的名称
     */
    public $newName='';

    /**
     * @var int 文件大小
     */
    public $size = 0;

    /**
     * @var  string 文件类型
     */
    public $extension='';

    /**
     * @var string 文件存放的driver 名称，比如 oss
     */
    public $provider='';

    /**
     * @var string 文件存储的path
     */
    public $object;

    /**
     * @var string md5
     */
    public $eTag='';

    /**
     * @var string url
     */
    public $url='';

}