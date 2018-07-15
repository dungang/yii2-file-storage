<?php
namespace dungang\storage;

use dungang\storage\StorageAction;

class ChunkUploadAction extends StorageAction
{
    public function run(){
        $res = $this->driver->chunkUpload();
        return Json::encode($res);
    }
}

