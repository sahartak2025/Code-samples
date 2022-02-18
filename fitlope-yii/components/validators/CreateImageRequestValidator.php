<?php

namespace app\components\validators;

use app\models\Image;

/**
 * Class CreateImageRequestValidator
 * @package app\components\validators
 *
 * @property string $mime_type Image mimeType
 * @property int $size Image size in bytes
 * @property string $category Image category
 */
class CreateImageRequestValidator extends RequestValidator
{
    public ?string $mime_type = null;
    public ?int $size = null;
    public ?string $category = null;

    /**
     * Validation rules
     * @return array
     */
    public function rules()
    {
        return [
            [['mime_type', 'size', 'category'], 'required'],
            ['mime_type', 'in', 'range' => Image::MIME_TYPES],
            ['category', 'in', 'range' => array_keys(Image::$categories)],
            ['size', 'integer', 'min' => Image::MIN_SIZE, 'max' => Image::MAX_SIZE],
        ];
    }
}