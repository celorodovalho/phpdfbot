<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Notification
 * @package App\Models
 * @property int $telegram_id
 * @property string $body
 */
class Notification extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'telegram_id',
        'body'
    ];

    protected $guarded = ['id'];
}
