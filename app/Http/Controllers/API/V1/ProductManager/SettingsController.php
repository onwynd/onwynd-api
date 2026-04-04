<?php

namespace App\Http\Controllers\API\V1\ProductManager;

use App\Http\Controllers\API\BaseController;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends BaseController
{
    /**
     * Get feature toggles.
     */
    public function features()
    {
        $features = Setting::where('group', 'features')->get()->pluck('value', 'key');

        // Ensure standard features exist if not in DB
        $defaults = [
            'dark_mode' => true,
            'beta_features' => false,
            'maintenance_mode' => false,
            'user_registration' => true,
        ];

        foreach ($defaults as $key => $value) {
            if (! isset($features[$key])) {
                $features[$key] = $value;
            }
        }

        return $this->sendResponse($features, 'Feature toggles retrieved.');
    }

    /**
     * Update feature toggles.
     */
    public function updateFeatures(Request $request)
    {
        $data = $request->all();

        foreach ($data as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key, 'group' => 'features'],
                ['value' => $value]
            );
        }

        return $this->sendResponse($data, 'Feature toggles updated.');
    }
}
