<?php

namespace App\Contracts\Collector;

use Illuminate\Database\Eloquent\Collection;

/**
 * Interface CollectorInterface
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
interface CollectorInterface
{
    /**
     * Collect the messages
     *
     * @return Collection
     */
    public function collectOpportunities(): Collection;

    /**
     * @return iterable
     */
    public function fetchMessages(): iterable;

    /**
     * Create a Opportunity based on previous definition
     *
     * @param $message
     *
     * @return mixed
     */
    public function createOpportunity($message);

    /**
     * Parse the title
     *
     * @param $message
     *
     * @return string
     */
    public function extractTitle($message): string;

    /**
     * Parse the description
     *
     * @param $message
     *
     * @return string
     */
    public function extractDescription($message): string;

    /**
     * Parse the files
     *
     * @param $message
     *
     * @return array
     */
    public function extractFiles($message): array;

    /**
     * Parse the origin
     *
     * @param $message
     *
     * @return array
     */
    public function extractOrigin($message): array;

    /**
     * Parse the location
     *
     * @param $message
     *
     * @return string
     */
    public function extractLocation($message): string;

    /**
     * Parse the tags
     *
     * @param $message
     *
     * @return array
     */
    public function extractTags($message): array;

    /**
     * Parse the URL
     *
     * @param $message
     *
     * @return array
     */
    public function extractUrls($message): array;

    /**
     * Parse the emails
     *
     * @param $message
     *
     * @return array
     */
    public function extractEmails($message): array;
}
