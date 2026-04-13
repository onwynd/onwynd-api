<?php

namespace Database\Seeders;

use App\Models\Content\Testimonial;
use Illuminate\Database\Seeder;

class TestimonialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Remove placeholder / stale testimonials before seeding
        Testimonial::whereNotIn('name', [
            'Chidinma Okafor',
            'Ibrahim Yusuf',
            'Funke Adeyemi',
            'Emeka Nwankwo',
        ])->delete();

        $testimonials = [
            [
                'name' => 'Chidinma Okafor',
                'role' => 'Business Owner, Lagos',
                'avatar_url' => '/storage/testimonials/chidinma-okafor.jpg',
                'stats_text' => '502 Total Conversations',
                'quote' => "As a Nigerian entrepreneur, stress was affecting my business decisions. Onwynd helped me find balance. The support is truly life-changing!",
                'conversation_history' => [
                    [
                        'sender' => 'user',
                        'text' => "Hey Doc, Why is my life so meaningless? I don't think I want to be alive anymore in this world. I wanna die and stop existing.",
                    ],
                    [
                        'sender' => 'bot',
                        'text' => "I understand that you may be feeling down and questioning the value of your life.\n\n👆 Remember, everyone goes through challenging times, and it's important to be kind to yourself!! 💪💪\n\nLet's explore ways to rediscover purpose, find meaning, and cultivate self-worth.",
                    ],
                    [
                        'sender' => 'user',
                        'text' => "Thanks a lot doc, You're truly the best mental health AI out there.",
                    ],
                ],
                'rating' => 5,
                'is_active' => true,
                'order' => 1,
            ],
            [
                'name' => 'Ibrahim Yusuf',
                'role' => 'Software Developer, Abuja',
                'avatar_url' => '/storage/testimonials/ibrahim-yusuf.jpg',
                'stats_text' => '318 Total Conversations',
                'quote' => "I was skeptical about mental health apps, but Onwynd changed my perspective. The AI chatbot understands our Nigerian context better than I expected.",
                'conversation_history' => null,
                'rating' => 5,
                'is_active' => true,
                'order' => 2,
            ],
            [
                'name' => 'Funke Adeyemi',
                'role' => 'University Student, Ibadan',
                'avatar_url' => '/storage/testimonials/funke-adeyemi.jpg',
                'stats_text' => '241 Total Conversations',
                'quote' => "Dealing with exam pressure was overwhelming until I found Onwynd. The 24/7 support helped me during late-night study sessions. Affordable and effective!",
                'conversation_history' => null,
                'rating' => 5,
                'is_active' => true,
                'order' => 3,
            ],
            [
                'name' => 'Emeka Nwankwo',
                'role' => 'Teacher, Port Harcourt',
                'avatar_url' => '/storage/testimonials/emeka-nwankwo.jpg',
                'stats_text' => '189 Total Conversations',
                'quote' => "Managing work stress and family responsibilities was difficult. Onwynd gave me practical tools that fit into my busy Nigerian lifestyle. Highly recommended!",
                'conversation_history' => null,
                'rating' => 5,
                'is_active' => true,
                'order' => 4,
            ],
        ];

        foreach ($testimonials as $data) {
            Testimonial::updateOrCreate(
                ['name' => $data['name']],
                $data
            );
        }
    }
}
