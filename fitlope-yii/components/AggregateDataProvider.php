<?php

namespace app\components;

use yii\data\BaseDataProvider;
use Yii;

/**
 * AggregateDataProvider implements a data provider based on mongodb aggregation params.
 *
 * ActiveDataProvider provides data by performing mongodb aggregation using Yii::$app->mongodb->getCollection($collection_name)->aggregate()
 *
 * The following is an example of using AggregateDataProvider to provide aggregation result items
 *
 * ```php
 * $provider = new AggregateDataProvider([
 *     'collection_name' => Recipe::collectionName(),
 *     'aggregate_params' => $params
 *     'pagination' => [
 *         'pageSize' => 20,
 *     ],
 *     'sort' => [
 *          'attributes' => ['_id'],
 *          'defaultOrder' => ['_id' => SORT_DESC]
 *     ]
 * ]);
 *
 * // get the items in the current page
 * $items = $provider->getModels();
 */
class AggregateDataProvider extends BaseDataProvider
{
    /**
     * @var array aggregation params for performing mongodb aggregation
     * @see https://docs.mongodb.com/manual/reference/method/db.collection.aggregate/
     */
    public array $aggregate_params = [];
    
    /**
     * @var string collection name where aggregation will be performed
     */
    public string $collection_name = '';
    
    /**
     * @var string|callable the column that is used as the key of the data models.
     * This can be either a column name, or a callable that returns the key value of a given data model.
     * If this is not set, the index of the [[models]] array will be used.
     * @see getKeys()
     */
    public $key;
    
    /**
     * {@inheritdoc}
     */
    protected function prepareModels()
    {
        $aggregate_params = $this->aggregate_params;
        $pagination = $this->getPagination();
        if ($pagination !== false) {
            $pagination->totalCount = $this->getTotalCount();
            if ($pagination->totalCount === 0) {
                return [];
            }
            $aggregate_params[] = $this->getPaginationArray($pagination->getOffset(), $pagination->getLimit());
        }
        $sort = $this->getSort();
        if ($sort !== false) {
            $columns = $sort->getOrders();
            if ($columns) {
                $sort_params = [];
                foreach ($columns as $field => $type) {
                    $sort_params[$field] = $type === SORT_DESC ? -1 : 1;
                }
    
                $aggregate_params = array_merge([
                    ['$sort' => $sort_params]
                ], $aggregate_params);
            }
        }
        
        $collection = $this->getCollection();
        $result = $collection->aggregate($aggregate_params);
        
        return $result[0]['data'] ?? [];
    }
    
    /**
     * {@inheritdoc}
     */
    protected function prepareKeys($models)
    {
        if ($this->key !== null) {
            $keys = [];
            foreach ($models as $model) {
                if (is_string($this->key)) {
                    $keys[] = $model[$this->key];
                } else {
                    $keys[] = call_user_func($this->key, $model);
                }
            }
            
            return $keys;
        }
        
        return array_keys($models);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function prepareTotalCount()
    {
        $aggregate_params = $this->aggregate_params;
        $aggregate_params[] = $this->getPaginationArray(0, 1);
        $collection = $this->getCollection();
        $result = $collection->aggregate($aggregate_params);
        $total = $result['0']['metadata'][0]['total'] ?? 0;
        return $total;
    }
    
    /**
     * Returns array which should be appended to aggregate params for pagination
     * @param int $offset
     * @param int $limit
     * @return array
     */
    private function getPaginationArray(int $offset, int $limit): array
    {
        return [
            '$facet' => [
                'metadata' => [
                    ['$count' => 'total'],
                ],
                'data' => [
                    ['$skip' => $offset], ['$limit' => $limit]
                ]
            ]
        ];
    }
    
    /**
     * Return collection object for the current collection
     * @return Yii\mongodb\Collection
     */
    private function getCollection(): yii\mongodb\Collection
    {
        $collection = Yii::$app->mongodb->getCollection($this->collection_name);
        return $collection;
    }
    
}