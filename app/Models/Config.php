<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Config
 *
 * @package App\Models
 * @property string $key
 * @property string $value
 */
class Config extends Model
{
    /**
     * @var array
     */
    protected $fillable = [
        'key',
        'value'
    ];

    /**
     * @var array
     */
    protected $guarded = ['id'];
}
