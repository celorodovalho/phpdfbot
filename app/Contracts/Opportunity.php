<?php

namespace App\Contracts;

use App\Contracts\Interfaces\OpportunityInterface;

class Opportunity implements OpportunityInterface
{
    private $title;
    private $position;
    private $description;
    private $salary;
    private $company;
    private $location;

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return string
     */
    public function getPosition(): string
    {
        return $this->position;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return string
     */
    public function getSalary(): string
    {
        return $this->salary;
    }

    /**
     * @return string
     */
    public function getCompany(): string
    {
        return $this->company;
    }

    /**
     * @return string
     */
    public function getLocation(): string
    {
        return $this->location;
    }

    public function setTitle(string $title): OpportunityInterface
    {
        $this->title = $title;
        return $this;
    }

    public function setDescription(string $description): OpportunityInterface
    {
        $this->description = $description;
        return $this;
    }

    public function setSalary(string $salary): OpportunityInterface
    {
        $this->salary = $salary;
        return $this;
    }

    public function setCompany(string $company): OpportunityInterface
    {
        $this->company = $company;
        return $this;
    }

    public function setLocation(string $location): OpportunityInterface
    {
        $this->location = $location;
        return $this;
    }

    public function setPosition(string $position): OpportunityInterface
    {
        $this->position = $position;
        return $this;
    }
}