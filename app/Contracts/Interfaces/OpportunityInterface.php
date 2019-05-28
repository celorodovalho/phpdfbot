<?php

namespace App\Contracts\Interfaces;

use Illuminate\Support\Collection;

interface OpportunityInterface
{
    public function getTitle(): string;

    public function getDescription(): string;

    public function getSalary(): string;

    public function getCompany(): string;

    public function getLocation(): string;

    public function getPosition(): string;

    public function getFiles(): Collection;

    public function setTitle(string $title): self;

    public function setDescription(string $description): self;

    public function setSalary(string $salary): self;

    public function setCompany(string $company): self;

    public function setLocation(string $location): self;

    public function setPosition(string $position): self;

    public function addFile(string $file): self;
}