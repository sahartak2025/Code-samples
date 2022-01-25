<?php


namespace App\DataObjects;


class BaseDataObject
{

    public function __construct(array $data)
    {
        foreach ($data as $attribute => $value) {
            $this->{$attribute} = $value;
        }
    }
}
