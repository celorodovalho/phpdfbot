<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Prettus\Repository\Contracts\Transformable;
use Prettus\Repository\Traits\TransformableTrait;
use Telegram\Bot\Objects\PhotoSize;

/**
 * Class Opportunity
 * @package App\Models
 *
 * @property string $id
 * @property string $title
 * @property string $position
 * @property string $description
 * @property string $salary
 * @property string $company
 * @property string $location
 * @property int $telegram_id
 * @property int $status
 * @property int $telegram_user_id
 * @property Collection $files
 * @property string $url
 * @property string $origin
 * @property string $tags
 */
class Opportunity extends Model implements Transformable
{

    use SoftDeletes, TransformableTrait;

    public const STATUS_INACTIVE = 0;
    public const STATUS_ACTIVE = 1;
    public const CALLBACK_APPROVE = 'approve';
    public const CALLBACK_REMOVE = 'remove';

    public const COMPANY = 'company';
    public const LOCATION = 'location';
    public const FILES = 'files';
    public const DESCRIPTION = 'description';
    public const TITLE = 'title';
    public const URL = 'url';
    public const ORIGIN = 'origin';
    public const TAGS = 'tags';
    public const POSITION = 'position';
    public const SALARY = 'salary';
    public const EMAILS = 'emails';

    protected $fillable = [
        self::TITLE,
        self::POSITION,
        self::DESCRIPTION,
        self::SALARY,
        self::COMPANY,
        self::LOCATION,
        self::FILES,
        'telegram_id',
        'status',
        'telegram_user_id',
        self::URL,
        self::ORIGIN,
        self::TAGS,
        self::EMAILS,
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'files' => 'collection',
    ];

    /**
     * @param $file
     */
    public function addFile($file): void
    {
        $this->files = $this->files->concat([$file]);
    }

    public function getText()
    {
        return implode(', ', [
            $this->title,
            $this->position,
            $this->description,
            $this->tags,
        ]);
    }
}
