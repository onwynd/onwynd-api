<?php

namespace App\Repositories\Contracts;

interface TherapyRepositoryInterface extends BaseRepositoryInterface
{
    public function findUpcomingSessions(string $userId, int $limit = 5): mixed;

    public function getSessionHistory(string $userId, ?int $limit = null): mixed;

    public function checkAvailability(int $therapistId, string $date, string $time, int $duration = 60, ?int $excludeSessionId = null): bool;

    public function getTherapistSessions(int $therapistId, array $filters = []): mixed;

    public function getPatientSessions(int $patientId, array $filters = []): mixed;

    public function getAllSessions(array $filters = []): mixed;

    public function getTherapistStats(int $therapistId): array;

    public function getPatientStats(int $patientId): array;

    public function getAdminStats(): array;

    public function hasRelationship(int $therapistId, int $patientId): bool;

    public function getSharedSessions(int $therapistId, int $patientId): mixed;

    public function getPatientIds(int $therapistId): array;

    public function updateSessionNote(int $sessionId, array $data): mixed;

    public function getTherapistPatients(int $therapistId): mixed;

    public function getTherapistEarnings(int $therapistId): array;

    public function getAvailableTherapists(array $filters): mixed;

    public function findTherapist(int $id): ?object;
}
