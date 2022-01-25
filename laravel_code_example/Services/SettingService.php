<?php
namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Str;

class SettingService
{
    public function createSetting($data)
    {
        Setting::create([
            'id' => Str::uuid()->toString(),
            'key' => $data['key'],
            'content' => $data['content'],
        ]);
    }

    public function findById($id)
    {
        return Setting::find($id);
    }

    public function getSettingByKey($key)
    {
        return Setting::where('key', $key)->first();
    }

    public function getSettingContentByKey($key)
    {
        $setting = Setting::where('key', $key)->first();
        if ($setting) {
            return $setting->content;
        }
        return false;
    }

    public function getSettingsPaginate()
    {
        return Setting::orderBy('created_at', 'desc')->paginate(config('cratos.pagination.settings'));
    }

    public function updateSetting($data)
    {
        $setting = $this->getSettingByKey($data['key']);
        if ($setting) {
            $setting->update(['content' => $data['content']]);
            return true;
        }
        return false;
    }
}
