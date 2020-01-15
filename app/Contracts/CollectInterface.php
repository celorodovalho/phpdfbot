<?php

namespace App\Contracts;

interface CollectInterface
{
    public function collectMessages(): array;

    public function createMessageFormat($message);

    public function extractTitle($message): string;

    public function extractDescription($message): string;

    public function extractFiles($message): array;

    public function extractOrigin($message): string;

    public function extractCompany($message): string;

    public function extractLocation($message): string;

    public function extractTags($message): array;

    public function extractPosition($message): string;

    public function extractSalary($message): string;

    public function extractUrl($message): string;

    public function extractEmails($message): string;
}
