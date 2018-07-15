<?php
namespace dungang\storage;

class DeleteAction extends StorageAction
{
    public function run($key){
        $res = $this->driver->deleteUpload($key);
        return Json::encode($res);
    }
}

