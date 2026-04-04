<?php

namespace App\Console\Commands;

use App\Models\TherapySession;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CreateTestSession extends Command
{
    protected $signature = 'session:create-test {patient_id} {therapist_id} {--type=video}';

    protected $description = 'Create a test therapy session and output its uuid in JSON';

    public function handle(): int
    {
        $patientId = (int) $this->argument('patient_id');
        $therapistId = (int) $this->argument('therapist_id');
        $type = (string) $this->option('type');

        $session = TherapySession::create([
            'patient_id' => $patientId,
            'therapist_id' => $therapistId,
            'session_type' => in_array($type, ['video', 'audio', 'chat']) ? $type : 'video',
            'status' => 'scheduled',
            'scheduled_at' => Carbon::now()->addHour(),
            'duration_minutes' => 60,
            'session_rate' => 100.00,
            'payment_status' => 'pending',
        ]);

        $this->line(json_encode([
            'id' => $session->id,
            'uuid' => $session->uuid,
        ]));

        return self::SUCCESS;
    }
}
