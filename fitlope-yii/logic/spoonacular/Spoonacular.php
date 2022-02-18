<?php


namespace app\logic\spoonacular;


use app\components\constants\UserAgentsConstants;
use GuzzleHttp\Client;
use Yii;
/**
 * Spoonacular class for getting info from spoonacular api
 */
class Spoonacular
{
    
    private array $api_keys = [];
    private string $api_key = '';
    private int $used_points = 0;
    private int $points_limit = 0;
    private array $paid_keys_limits = [];
    
    const API_HOST = 'https://api.spoonacular.com';
    
    /**
     * Spoonacular class constructor
     * @param array $api_keys
     * @param int $points_limit
     * @param array $paid_keys_limits
     */
    public function __construct(array $api_keys, int $points_limit, array $paid_keys_limits = [])
    {
        if (!$api_keys) {
            Yii::warning($api_keys, ['SpoonacularEmptyApiKeys']);
        }
        $this->points_limit = $points_limit;
        $this->api_keys = $api_keys;
        $this->paid_keys_limits = $paid_keys_limits;
    }
    
    /**
     * Search recipes using advanced filtering.
     * @param array $combination
     * @param int $offset
     * @param int $number
     * @param bool $full
     * @return array|null
     */
    public function recipesComplexSearch(array $combination, int $offset = 0, int $number = 100, bool $full = true): ?array
    {
        $endpoint = '/recipes/complexSearch';
        $params = [
            'number' => $number,
            'offset' => $offset,
        ];
        
        if ($full) {
            $params['fillIngredients'] = 'true';
            $params['addRecipeInformation'] = 'true';
            $params['addRecipeNutrition'] = 'true';
        }
        
        $filters = ['cuisine', 'type', 'diet', 'excludeCuisine', 'intolerances', 'includeIngredients', 'excludeIngredients'];
        foreach ($filters as $filter) {
            if (!empty($combination[$filter])) {
                $params[$filter] = $combination[$filter];
            }
        }
        
        $result = $this->sendRequest($endpoint, $params);
        return $result;
    }
    
    /**
     * Convert ingredient unit to gram
     * @param string $name
     * @param string $unit
     * @return array|null
     */
    public function convertUnits(string $name, string $unit): ?array
    {
        $endpoint = '/recipes/convert';
        $params = [
            'ingredientName' => $name,
            'sourceUnit' => $unit,
            'sourceAmount' => 1,
            'targetUnit' => 'grams',
        ];
        $convert_result = $this->sendRequest($endpoint, $params);
        return $convert_result;
    }
    
    /**
     * Get ingredient information by id
     * @param int $id
     * @param array $units
     * @return array|null
     */
    public function getIngredientInformation(int $id, array $units = []): ?array
    {
        $endpoint = "/food/ingredients/{$id}/information";
        $params = [
            'amount' => 100,
            'unit' => 'grams'
        ];
        $result = $this->sendRequest($endpoint, $params);
        $result['units_grams'] = [];
        $result['possibleUnits'] = array_filter(array_unique(array_merge($units, $result['possibleUnits'])));
        foreach ($result['possibleUnits'] as $unit) {
            if ($unit != 'g') {
                $convert_result = $this->convertUnits($result['name'], $unit);
                if (empty($convert_result['targetAmount'])) {
                    Yii::warning([$id, $params], 'SpoonacularUnitMissing');
                } else {
                    $unit = str_replace(['.', ' '], ['', '_'], $unit);
                    $result['units_grams'][$unit] = $convert_result['targetAmount'];
                }
            }
        }
        return $result;
    }
    
    /**
     * Returns similar recipes data for given recipe id
     * @param int $recipe_id
     * @param int $limit
     * @return array|null
     */
    public function getSimilarRecipes(int $recipe_id, int $limit = 1): ?array
    {
        $endpoint = "/recipes/{$recipe_id}/similar";
        $params = [
            'number' => $limit,
        ];
        $result = $this->sendRequest($endpoint, $params);
        return $result;
    }
    
    /**
     * Autocomplete the entry of an ingredient
     * @param string $query
     * @param int $number
     * @return array|null
     */
    public function searchIngredients(string $query, int $number = 1): ?array
    {
        $endpoint = '/food/ingredients/autocomplete';
        $params = [
            'query' => $query,
            'number' => $number,
            'metaInformation' => 'true'
        ];
        $result = $this->sendRequest($endpoint, $params);
        return $result;
    }
    
    
    /**
     * Returns wines for given type
     * @param string $type
     * @param int $number
     * @return array|null
     */
    public function getWines(string $type, int $number = 1): ?array
    {
        $endpoint = '/food/wine/recommendation';
        $params = [
            'wine' => $type,
            'number' => $number,
            'metaInformation' => 'true'
        ];
        $result = $this->sendRequest($endpoint, $params);
        return $result;
    }
    
    /**
     * Returns dishes for given type
     * @param string $type
     * @return array
     */
    public function getDishes(string $type): ?array
    {
        $endpoint = '/food/wine/dishes';
        $params = [
            'wine' => $type,
        ];
        $result = $this->sendRequest($endpoint, $params);
        $dishes = $result['pairings'] ?? [];
      
        return $dishes;
    }
    
    /**
     * Returns current api key, if active key not set then get it from api_keys array
     * @return string
     */
    public function getApiKey(): string
    {
        if (!$this->api_key && $this->api_keys) {
            $this->api_key = array_shift($this->api_keys);
        }
        return $this->api_key;
    }
    
    /**
     * Send GET api request to spoonacular api by given endpoint and array of params
     * @param string $endpoint
     * @param array $params
     * @return array|null
     */
    public function sendRequest(string $endpoint, array $params): ?array
    {
        $params['apiKey'] = $this->getApiKey();
        $url = static::API_HOST . $endpoint . '?' . http_build_query($params);
        $agents = UserAgentsConstants::$user_gents;
        $agent = $agents[array_rand($agents)];
    
        $client = new Client([
            'headers' => [
                'User-Agent' => $agent,
            ]
        ]);
        $response = $client->get($url);
        $used_points = $response->getHeader('X-API-Quota-Used');
        $this->used_points = intval(array_shift($used_points));
        $result = json_decode($response->getBody()->getContents(), true);
        $this->switchKeyIfExpired();
        return $result ? $result : null;
    }
    
    /**
     * Check if current apy key expire available daily points
     * @return bool
     */
    public function isKeyExpired(): bool
    {
        $key = $this->api_key;
        if (!$key) {
            return true;
        }
        $points_limit = $this->paid_keys_limits[$this->api_key] ?? $this->points_limit;
        $is_expired = $this->used_points >= $points_limit;
        return $is_expired;
    }
    
    /**
     * Check if there is left available api key
     * @return bool
     */
    public function hasApiKey(): bool
    {
        return $this->api_key || $this->api_keys;
    }
    
    /**
     * set active api key using next available api key
     * @return bool
     */
    public function switchKey(): bool
    {
        if (!$this->api_keys) {
            $this->api_key = '';
            return false;
        }
        
        $this->api_key = array_shift($this->api_keys);
        $this->used_points = 0;
        return true;
    }
    
    /**
     * Check if current key is expired then switch to the next available key
     * @return bool
     */
    public function switchKeyIfExpired(): bool
    {
        if ($this->isKeyExpired()) {
            return $this->switchKey();
        }
        return false;
    }
    
    /**
     * Returns today's count of used point of current api key
     * @return int
     */
    public function getUsedPoints(): int
    {
        return $this->used_points;
    }
    
  
    
}