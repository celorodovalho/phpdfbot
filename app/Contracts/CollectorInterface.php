<?php

namespace App\Contracts;

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
     * @return string
     */
    public function extractOrigin($message): string;

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
     * @return string
     */
    public function extractUrl($message): string;

    /**
     * Parse the emails
     *
     * @param $message
     *
     * @return string
     */
    public function extractEmails($message): string;
}
