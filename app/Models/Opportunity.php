<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

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
 * @property Collection $files
 */
class Opportunity extends Model
{

    use SoftDeletes;

    protected $fillable = [
        'title',
        'position',
        'description',
        'salary',
        'company',
        'location',
        'files',
        'telegram_id',
    ];

    protected $guarded = ['id'];

    /**
     * @return Collection
     */
    public function getFiles(): Collection
    {
        return collect(json_decode($this->files));
    }

    public function addFile(string $file)
    {
        $this->files->add($file);
    }

    public function hasFile(): bool
    {
        return $this->files ? $this->files->isNotEmpty() : false;
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function(Opportunity $opportunity)
        {
            $opportunity->files = $opportunity->files->toJson();
        });

        static::updating(function(Opportunity $opportunity)
        {
            $opportunity->files = $opportunity->files->toJson();
        });
    }
}
