<?php

namespace App\Repositories\Contracts;

interface AssessmentRepositoryInterface
{
    public function all();

    public function find($id);

    public function create(array $data);

    public function getUserResults($userId);

    public function assignToUser($assessmentId, $userId);
}
