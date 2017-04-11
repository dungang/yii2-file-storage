<?php
/**
 * Author: dungang
 * Date: 2017/4/11
 * Time: 8:40
 */

namespace dungang\storage;


use yii\filters\AccessControl;

trait ControllerTrait
{

    /**
     * @var ModuleTrait
     */
    public $module;

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access'=>[
                'class'=>AccessControl::className(),
                'rules'=>[
                    [
                        'allow' => true,
                        'roles' => $this->module->role,
                    ],
                ]
            ],
        ];
    }
}