<?php

namespace App\Events;

use App\Models\Assessment;
use App\Models\UserAssessmentResult;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AssessmentCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public UserAssessmentResult $result,
        public Assessment $assessment,
    ) {}
}
