<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
/**
 * Class Log
 * @package App\Models
 * @property $id
 * @property $c_user_id
 * @property $b_user_id
 * @property $context_id
 * @property $ip
 * @property $type
 * @property $result
 * @property $level
 * @property $action
 * @property $data
 * @property $user_agent
 * @property $created_at
 */
class Log extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $dates = ['created_at'];

    /**
     * Returns replacements array
     * @return array|null
     */
    public function getReplacementsArray(): ?array
    {
        $arr = json_decode($this->data, true);
        if (array_key_exists('replacements', $arr)) {
            if($arr['replacements']){
                if (array_key_exists('values', $arr['replacements'])) {
                    if (is_array($arr['replacements']['values'])){
                        foreach ($arr['replacements']['values'] as $key => $replacement) {
                            $arr['replacements'][$key] = $replacement[1];
                        }
                    }
                }
            }
        }
        unset($arr['replacements']['values']);
        if (isset($arr['replacements'])) {
            foreach ($arr['replacements'] as &$replacement) {
                if (is_array($replacement)) {
                    foreach ($replacement as &$data) {
                        if (is_array($data)) {
                            $data = @implode(', ',$data);
                        }
                    }
                    $replacement = @implode(', ',$replacement);
                }
            }
        }
        return $arr['replacements'] ?? [];
    }
}

