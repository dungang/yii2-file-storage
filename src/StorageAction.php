<?php
namespace dungang\storage;

use yii\base\Action;
use dungang\storage\Driver;
use dungang\storage\driver\Local;

abstract class StorageAction extends Action
{
    /**
     * 初始化驱动的配置
     * <code>
     * [
     *          'class'=>'',
     *          'saveDir'=>'',
     *          'behaviors'=>'',
     *      ]
     * </code>
     * @var array|string|null
     */
    public $driverConfig=null;
    
    /**
     * 存储驱动
     * @var Driver
     */
    public $driver;
    
    public function init() {
        
        if (null == $this->driverConfig) {
            $this->driver = \Yii::createObject(Local::className());
        } else {
            $this->driver = \Yii::createObject($this->driverConfig);
        }
    }
  
}

