<?php

namespace App\Models;

use App\Helpers\SanitizerHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Prettus\Repository\Contracts\Transformable;
use Prettus\Repository\Traits\TransformableTrait;

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
 * @property string $emails
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
            $this->location,
            $this->description,
            $this->tags,
        ]);
    }

    public static function make(array $data)
    {
        return new self(
            [
                self::TITLE => SanitizerHelper::sanitizeSubject($data[self::TITLE]),
                self::DESCRIPTION => SanitizerHelper::sanitizeBody($data[self::DESCRIPTION]),
                self::FILES => new Collection($data[self::FILES]),
                self::POSITION => $data[self::POSITION],
                self::COMPANY => $data[self::COMPANY],
                self::LOCATION => $data[self::LOCATION],
                self::TAGS => $data[self::TAGS],
                self::SALARY => $data[self::SALARY],
                self::URL => $data[self::URL],
                self::ORIGIN => $data[self::ORIGIN],
                self::EMAILS => $data[self::EMAILS],
            ]
        );
    }
}
