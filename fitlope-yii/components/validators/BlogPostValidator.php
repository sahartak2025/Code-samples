<?php

namespace app\components\validators;

use app\models\BlogPost;

class BlogPostValidator extends BlogPost
{
    use ValidatorTrait;

    public $title_i18n;
    public $content_i18n;


    /**
     * @return array
     */
    public function rules()
    {
        return [
            [['timage_id'], 'default', 'value' => null],
            [['title_i18n', 'content_i18n'], 'required'],
            ['title_i18n', 'filter', 'filter' => 'strip_tags'],
            ['content_i18n', 'filter', 'filter' => '\yii\helpers\HtmlPurifier::process'],
            [['title_i18n', 'content_i18n', 'timage_id'], 'string'],
            ['is_public', 'default', 'value' => false],
            ['is_public', 'boolean'],
        ];
    }

    /**
     * Return array of model attributes
     * @return array
     */
    public function getFilteredAttributes(): array
    {
        return [
            'title_i18n' => $this->title_i18n,
            'content_i18n' => $this->content_i18n,
            'timage_id' => $this->timage_id,
            'is_public' => $this->is_public
        ];
    }
}
