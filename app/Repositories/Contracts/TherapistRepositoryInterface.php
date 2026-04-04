<?php

namespace App\Repositories\Contracts;

interface TherapistRepositoryInterface
{
    public function all();

    public function find($id);

    public function getAvailableTherapists($date = null);

    public function getProfile($userId);

    public function updateAvailability($therapistId, array $availability);
}
