<?php

namespace Database\Seeders;

use App\Models\Assessment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AssessmentSeeder extends Seeder
{
    public function run(): void
    {
        $assessments = [
            [
                'title' => 'PHQ-9 Depression Scale',
                'description' => 'Patient Health Questionnaire-9',
                'type' => 'depression',
                'questions' => [
                    'Little interest or pleasure in doing things',
                    'Feeling down, depressed, or hopeless',
                    'Trouble falling or staying asleep, or sleeping too much',
                    'Feeling tired or having little energy',
                    'Poor appetite or overeating',
                    'Feeling bad about yourself',
                    'Trouble concentrating on things',
                    'Moving or speaking so slowly that other people could have noticed',
                    'Thoughts that you would be better off dead',
                ],
            ],
            [
                'title' => 'GAD-7 Anxiety Scale',
                'description' => 'Generalized Anxiety Disorder-7',
                'type' => 'anxiety',
                'questions' => [
                    'Feeling nervous, anxious, or on edge',
                    'Not being able to stop or control worrying',
                    'Worrying too much about different things',
                    'Trouble relaxing',
                    'Being so restless that it is hard to sit still',
                    'Becoming easily annoyed or irritable',
                    'Feeling afraid as if something awful might happen',
                ],
            ],
            [
                'title' => 'PSS-10 Perceived Stress Scale',
                'description' => 'Perceived Stress Scale-10',
                'type' => 'stress',
                'questions' => [
                    'In the last month, how often have you been upset because of something that happened unexpectedly?',
                    'In the last month, how often have you felt that you were unable to control the important things in your life?',
                    'In the last month, how often have you felt nervous and stressed?',
                    'In the last month, how often have you felt confident about your ability to handle your personal problems?',
                    'In the last month, how often have you felt that things were going your way?',
                    'In the last month, how often have you found that you could not cope with all the things that you had to do?',
                    'In the last month, how often have you been able to control irritations in your life?',
                    'In the last month, how often have you felt that you were on top of things?',
                    'In the last month, how often have you been angered because of things that were outside of your control?',
                    'In the last month, how often have you felt difficulties were piling up so high that you could not overcome them?',
                ],
            ],
            [
                'title' => 'WHO-5 Well-Being Index',
                'description' => 'World Health Organization-Five Well-Being Index',
                'type' => 'general',
                'questions' => [
                    'I have felt cheerful and in good spirits',
                    'I have felt calm and relaxed',
                    'I have felt active and vigorous',
                    'I woke up feeling fresh and rested',
                    'My daily life has been filled with things that interest me',
                ],
            ],
        ];

        foreach ($assessments as $data) {
            $assessment = Assessment::create([
                'uuid' => Str::uuid(),
                'title' => $data['title'],
                'slug' => Str::slug($data['title']),
                'description' => $data['description'],
                'type' => $data['type'],
                'total_questions' => count($data['questions']),
                'is_active' => true,
                'scoring_method' => ['type' => 'sum'],
                'interpretation_guide' => match ($data['title']) {
                    'GAD-7 Anxiety Scale' => [
                        '0-4' => 'Minimal',
                        '5-9' => 'Mild',
                        '10-14' => 'Moderate',
                        '15-21' => 'Severe',
                    ],
                    'PSS-10 Perceived Stress Scale' => [
                        '0-13' => 'Low',
                        '14-26' => 'Moderate',
                        '27-40' => 'High',
                    ],
                    'WHO-5 Well-Being Index' => [
                        '0-9' => 'Low well-being',
                        '10-14' => 'Moderate well-being',
                        '15-25' => 'High well-being',
                    ],
                    default => [
                        '0-4' => 'None-minimal',
                        '5-9' => 'Mild',
                        '10-14' => 'Moderate',
                        '15-19' => 'Moderately Severe',
                        '20-27' => 'Severe',
                    ],
                },
            ]);

            foreach ($data['questions'] as $index => $qText) {
                $questionData = [
                    'question_text' => $qText,
                    'question_type' => 'scale',
                    'order_number' => $index + 1,
                    'is_required' => true,
                ];
                if ($data['title'] === 'PHQ-9 Depression Scale' || $data['title'] === 'GAD-7 Anxiety Scale') {
                    $questionData['scale_min'] = 0;
                    $questionData['scale_max'] = 3;
                    $questionData['scale_labels'] = ['0' => 'Not at all', '3' => 'Nearly every day'];
                } elseif ($data['title'] === 'PSS-10 Perceived Stress Scale') {
                    $questionData['scale_min'] = 0;
                    $questionData['scale_max'] = 4;
                    $questionData['scale_labels'] = ['0' => 'Never', '4' => 'Very often'];
                } elseif ($data['title'] === 'WHO-5 Well-Being Index') {
                    $questionData['scale_min'] = 0;
                    $questionData['scale_max'] = 5;
                    $questionData['scale_labels'] = ['0' => 'At no time', '5' => 'All of the time'];
                }
                $assessment->questions()->create($questionData);
            }
        }
    }
}
