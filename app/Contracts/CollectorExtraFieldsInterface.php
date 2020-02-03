<?php
declare(strict_types=1);

namespace App\Contracts;

/**
 * Interface CollectorExtraFieldsInterface
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
interface CollectorExtraFieldsInterface
{
    /**
     * Parse the company
     *
     * @param $message
     *
     * @return string
     */
    public function extractCompany($message): string;

    /**
     * Parse the position
     *
     * @param $message
     *
     * @return string
     */
    public function extractPosition($message): string;

    /**
     * Parse the salary
     *
     * @param $message
     *
     * @return string
     */
    public function extractSalary($message): string;
}
