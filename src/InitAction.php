<?php
namespace dungang\storage;

use dungang\storage\StorageAction;
use yii\helpers\Json;

class InitAction extends StorageAction
{
    public function run(){
        $res = $this->driver->initChunkUpload();
        return Json::encode($res);
    }
}

