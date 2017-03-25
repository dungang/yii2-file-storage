<?php
/**
 * Created by PhpStorm.
 * User: Lenovo
 * Date: 2017/3/4
 * Time: 9:00
 */

namespace dungang\storage;


use yii\base\Event;

class StorageEvent extends Event
{
    /**
     * @var File
     */
    public $file;

}