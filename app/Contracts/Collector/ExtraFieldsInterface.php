<?php
declare(strict_types=1);

namespace App\Contracts\Collector;

/**
 * Interface ExtraFieldsInterface
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
interface ExtraFieldsInterface
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
