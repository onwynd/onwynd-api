<?php

namespace App\Repositories\Contracts;

interface SessionRepositoryInterface
{
    public function all();

    public function find($id);

    public function create(array $data);

    public function update($id, array $data);

    public function delete($id);

    public function getUpcomingForUser($userId);

    public function getPastForUser($userId);

    public function getForTherapist($therapistId);
}
