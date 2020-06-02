<?php

namespace App\Repositories;

use App\Contracts\Repositories\OpportunityRepository;
use App\Helpers\ExtractorHelper;
use App\Helpers\Helper;
use App\Helpers\SanitizerHelper;
use App\Models\Opportunity;
use App\Validators\CollectedOpportunityValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Exceptions\RepositoryException;

/**
 * Class OpportunityRepositoryEloquent
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class OpportunityRepositoryEloquent extends BaseRepository implements OpportunityRepository
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model(): string
    {
        return Opportunity::class;
    }

    /**
     * Specify Validator class name
     *
     * @return mixed
     */
    public function validator(): string
    {
        return CollectedOpportunityValidator::class;
    }

    /**
     * Boot up the repository, pushing criteria
     *
     * @throws RepositoryException
     */
    public function boot()
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }

    /**
     * @param array $data
     *
     * @return mixed
     */
    public function make(array $data): Opportunity
    {
        $opportunity = $this->model->newInstance([
            Opportunity::TITLE => $data[Opportunity::TITLE],
            Opportunity::ORIGINAL => $data[Opportunity::ORIGINAL],
        ]);

        $opportunity->{Opportunity::DESCRIPTION} = $data[Opportunity::DESCRIPTION];
        $opportunity->{Opportunity::FILES} = new Collection($data[Opportunity::FILES]);
        $opportunity->{Opportunity::POSITION} = $data[Opportunity::POSITION];
        $opportunity->{Opportunity::COMPANY} = $data[Opportunity::COMPANY];
        $opportunity->{Opportunity::LOCATION} = mb_strtoupper($data[Opportunity::LOCATION]);
        $opportunity->{Opportunity::TAGS} = new Collection($data[Opportunity::TAGS]);
        $opportunity->{Opportunity::SALARY} = $data[Opportunity::SALARY];
        $opportunity->{Opportunity::URLS} = new Collection($data[Opportunity::URLS]);
        $opportunity->{Opportunity::ORIGIN} = new Collection($data[Opportunity::ORIGIN]);
        $opportunity->{Opportunity::EMAILS} = new Collection($data[Opportunity::EMAILS]);
        $opportunity->{Opportunity::STATUS} = Opportunity::STATUS_INACTIVE;

        $opportunity->save();

        return $opportunity;
    }

    /**
     * @param array $data
     * @return Opportunity|mixed
     */
    public function createOpportunity(array $data): Opportunity
    {
        $files = [];
        /** @var UploadedFile $file */
        $file = $data[Opportunity::FILES];
        if (isset($data[Opportunity::FILES])
            && $data[Opportunity::FILES] instanceof UploadedFile
            && !($file->getSize() < 50000 && strpos($file->getMimeType(), 'image') !== false)
        ) {
            $fileName = Helper::base64UrlEncode($file->getClientOriginalName())
                . '.' . $file->getClientOriginalExtension();
            $filePath = $file->move(
                'uploads',
                $fileName
            );
            $files[$filePath->getPathname()] = Helper::cloudinaryUpload($filePath);
            File::delete($filePath->getPathname());
        }

        $opportunity = [
            Opportunity::TITLE => SanitizerHelper::sanitizeSubject(Str::limit($data[Opportunity::TITLE], 50)),
            Opportunity::DESCRIPTION => SanitizerHelper::sanitizeBody($data[Opportunity::DESCRIPTION]),
            Opportunity::ORIGINAL => $data[Opportunity::DESCRIPTION],
            Opportunity::FILES => $files,
            Opportunity::URLS => ExtractorHelper::extractUrls($data[Opportunity::URLS] . ' ' . $data[Opportunity::DESCRIPTION]),
            Opportunity::ORIGIN => [
                'type' => 'web',
                'ip' => $data[Opportunity::ORIGIN],
            ],
            Opportunity::LOCATION => implode(' / ', ExtractorHelper::extractLocation($data[Opportunity::LOCATION])),
            Opportunity::TAGS => ExtractorHelper::extractTags(implode(', ', $data)),
            Opportunity::EMAILS => SanitizerHelper::replaceMarkdown($data[Opportunity::EMAILS]),
            Opportunity::POSITION => $data[Opportunity::POSITION],
            Opportunity::SALARY => $data[Opportunity::SALARY],
            Opportunity::COMPANY => $data[Opportunity::COMPANY],
        ];

        return $this->make($opportunity);
    }
}
