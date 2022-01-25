<?php


namespace App\Services;


abstract class CoreNotification
{
    private $model;

    public function __construct()
    {
        $this->model = app($this->getModelClass());
    }

    abstract protected function getModelClass();

    protected function getModel()
    {
        return clone $this->model;
    }

}
