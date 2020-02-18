<?php

namespace App\Models;

use App\Traits\RoutesNotifications;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\HasDatabaseNotifications;
use Illuminate\Support\Collection;
use Prettus\Repository\Contracts\Transformable;
use Prettus\Repository\Traits\TransformableTrait;

/**
 * Class Opportunity
 *
 * @package App\Models
 *
 * @property string     $id
 * @property string     $title
 * @property string     $position
 * @property string     $description
 * @property string     $salary
 * @property string     $company
 * @property string     $location
 * @property int        $telegram_id
 * @property int        $status
 * @property int        $telegram_user_id
 * @property Collection $files
 * @property string     $url
 * @property string     $origin
 * @property Collection $tags
 * @property string     $emails
 * @property Carbon     created_at
 * @property Carbon     updated_at
 */
class Opportunity extends Model implements Transformable
{

    use SoftDeletes, TransformableTrait, HasDatabaseNotifications, RoutesNotifications;

    public const STATUS_INACTIVE = 0;
    public const STATUS_ACTIVE = 1;

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
    public const TELEGRAM_ID = 'telegram_id';
    public const STATUS = 'status';
    public const TELEGRAM_USER_ID = 'telegram_user_id';

    /**
     * @var array
     */
    protected $fillable = [
        self::TITLE,
        self::POSITION,
        self::DESCRIPTION,
        self::SALARY,
        self::COMPANY,
        self::LOCATION,
        self::FILES,
        self::TELEGRAM_ID,
        self::STATUS,
        self::TELEGRAM_USER_ID,
        self::URL,
        self::ORIGIN,
        self::TAGS,
        self::EMAILS,
    ];

    /**
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * @var array
     */
    protected $casts = [
        'files' => 'collection',
        'tags' => 'collection',
    ];

    /**
     * @param $file
     */
    public function addFile($file): void
    {
        $this->files = $this->files->concat([$file]);
    }

    /**
     * @return string
     */
    public function getText(): string
    {
        return implode(', ', [
            $this->title,
            $this->position,
            $this->location,
            $this->description,
            $this->tags,
        ]);
    }
}
