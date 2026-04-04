<?php

return [
    'booking' => [
        'send_to_patient' => env('BOOKING_EMAIL_TO_PATIENT', true),
        'send_to_therapist' => env('BOOKING_EMAIL_TO_THERAPIST', true),
    ],
];
