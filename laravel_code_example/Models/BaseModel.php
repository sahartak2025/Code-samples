<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Class BaseModel
 * @package App\Models
 * @method static self findOrFail(string $id)
 * @method static self find(string $id)
 */
abstract class BaseModel extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'id' => 'string',
    ];
    
    public function generateId()
    {
        return Str::uuid()->toString();
    }
    
    /**
     * BaseModel constructor.
     * {@inheritDoc}
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        if (!$this->id) {
            $this->id = $this->generateId();
        }
    }
    
}
