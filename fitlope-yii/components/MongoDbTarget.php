<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace app\components;

use app\components\utils\DateUtils;
use yii\helpers\VarDumper;

class MongoDbTarget extends \yii\mongodb\log\MongoDbTarget
{

    /**
     * Stores log messages to MongoDB collection.
     */
    public function export()
    {
        $rows = [];
        foreach ($this->messages as $message) {
            list($text, $level, $category, $timestamp) = $message;
            if (!is_string($text)) {
                // exceptions may not be serializable if in the call stack somewhere is a Closure
                if ($text instanceof \Throwable || $text instanceof \Exception) {
                    $text = (string) $text;
                } else {
                    $text = VarDumper::export($text);
                }
            }
            $rows[] = [
                'level' => $level,
                'category' => $category,
                'created_at' => DateUtils::getMongoTimeFromTS($timestamp),
                'prefix' => $this->getMessagePrefix($message),
                'message' => $text,
            ];
        }

        $this->db->getCollection($this->logCollection)->batchInsert($rows);
    }
}
