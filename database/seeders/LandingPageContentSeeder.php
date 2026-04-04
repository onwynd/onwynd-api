<?php

namespace Database\Seeders;

use App\Models\LandingPageContent;
use Illuminate\Database\Seeder;

class LandingPageContentSeeder extends Seeder
{
    public function run()
    {
        // Clear existing landing page content to avoid conflicts
        LandingPageContent::whereIn('section', ['hero', 'statistics', 'features', 'benefits_1', 'benefits_2', 'benefits_3', 'cta', 'blog'])->delete();

        $content = [
            // Hero Section
            [
                'section' => 'hero',
                'key' => 'label_text',
                'value' => 'free your mind',
                'metadata' => ['type' => 'badge', 'max_length' => 50],
                'is_active' => true,
            ],
            [
                'section' => 'hero',
                'key' => 'main_headline',
                'value' => 'Empathetic Mental Health AI Chatbot',
                'metadata' => ['type' => 'headline', 'max_length' => 100],
                'is_active' => true,
            ],
            [
                'section' => 'hero',
                'key' => 'sub_headline',
                'value' => 'Step into a world of cutting-edge technology and compassionate care, tailored to your unique needs.',
                'metadata' => ['type' => 'subheadline', 'max_length' => 200],
                'is_active' => true,
            ],
            [
                'section' => 'hero',
                'key' => 'primary_cta_text',
                'value' => 'Try Demo',
                'metadata' => ['type' => 'cta', 'style' => 'outline'],
                'is_active' => true,
            ],
            [
                'section' => 'hero',
                'key' => 'secondary_cta_text',
                'value' => 'Download The App',
                'metadata' => ['type' => 'cta', 'style' => 'primary'],
                'is_active' => true,
            ],

            // Statistics Section
            [
                'section' => 'statistics',
                'key' => 'section_label',
                'value' => 'our singular purpose',
                'metadata' => ['type' => 'badge', 'max_length' => 50],
                'is_active' => true,
            ],
            [
                'section' => 'statistics',
                'key' => 'section_title',
                'value' => 'We design empathetic AI Wellness chatbot platform for everyone.',
                'metadata' => ['type' => 'headline', 'max_length' => 100],
                'is_active' => true,
            ],
            [
                'section' => 'statistics',
                'key' => 'stat_1_number',
                'value' => '100,000',
                'metadata' => ['type' => 'statistic', 'suffix' => '+'],
                'is_active' => true,
            ],
            [
                'section' => 'statistics',
                'key' => 'stat_1_label',
                'value' => 'Lives Impacted',
                'metadata' => ['type' => 'statistic_label'],
                'is_active' => true,
            ],
            [
                'section' => 'statistics',
                'key' => 'stat_2_number',
                'value' => '78',
                'metadata' => ['type' => 'statistic', 'suffix' => 'K'],
                'is_active' => true,
            ],
            [
                'section' => 'statistics',
                'key' => 'stat_2_label',
                'value' => 'AI Models',
                'metadata' => ['type' => 'statistic_label'],
                'is_active' => true,
            ],
            [
                'section' => 'statistics',
                'key' => 'stat_3_number',
                'value' => '99.5',
                'metadata' => ['type' => 'statistic', 'suffix' => '%'],
                'is_active' => true,
            ],
            [
                'section' => 'statistics',
                'key' => 'stat_3_label',
                'value' => 'User Satisfaction',
                'metadata' => ['type' => 'statistic_label'],
                'is_active' => true,
            ],
            [
                'section' => 'statistics',
                'key' => 'stat_4_number',
                'value' => '550',
                'metadata' => ['type' => 'statistic', 'suffix' => 'M+'],
                'is_active' => true,
            ],
            [
                'section' => 'statistics',
                'key' => 'stat_4_label',
                'value' => 'Data LLMs Trained',
                'metadata' => ['type' => 'statistic_label'],
                'is_active' => true,
            ],

            // Features Section
            [
                'section' => 'features',
                'key' => 'section_label',
                'value' => 'our Features',
                'metadata' => ['type' => 'badge', 'max_length' => 50],
                'is_active' => true,
            ],
            [
                'section' => 'features',
                'key' => 'section_title',
                'value' => 'Onwynd App Features',
                'metadata' => ['type' => 'headline', 'max_length' => 100],
                'is_active' => true,
            ],
            [
                'section' => 'features',
                'key' => 'feature_1_title',
                'value' => 'Emotional Support',
                'metadata' => ['type' => 'feature_title'],
                'is_active' => true,
            ],
            [
                'section' => 'features',
                'key' => 'feature_1_description',
                'value' => 'Receive empathetic and compassionate guidance tailored to your unique mental health needs, helping you navigate challenges with confidence.',
                'metadata' => ['type' => 'feature_description'],
                'is_active' => true,
            ],
            [
                'section' => 'features',
                'key' => 'feature_1_icon',
                'value' => '/icons/heartbeat.svg',
                'metadata' => ['type' => 'feature_icon'],
                'is_active' => true,
            ],
            [
                'section' => 'features',
                'key' => 'feature_2_title',
                'value' => 'Personalized Insights',
                'metadata' => ['type' => 'feature_title'],
                'is_active' => true,
            ],
            [
                'section' => 'features',
                'key' => 'feature_2_description',
                'value' => 'Gain deep insights into your thoughts, emotions, and behaviors with our personalized AI-powered analysis.',
                'metadata' => ['type' => 'feature_description'],
                'is_active' => true,
            ],
            [
                'section' => 'features',
                'key' => 'feature_2_icon',
                'value' => '/icons/control.svg',
                'metadata' => ['type' => 'feature_icon'],
                'is_active' => true,
            ],
            [
                'section' => 'features',
                'key' => 'feature_3_title',
                'value' => 'Self-Discovery',
                'metadata' => ['type' => 'feature_title'],
                'is_active' => true,
            ],
            [
                'section' => 'features',
                'key' => 'feature_3_description',
                'value' => 'Unlock a deeper understanding of yourself through reflective exercises, empowering you to make positive changes and growth.',
                'metadata' => ['type' => 'feature_description'],
                'is_active' => true,
            ],
            [
                'section' => 'features',
                'key' => 'feature_3_icon',
                'value' => '/icons/settings.svg',
                'metadata' => ['type' => 'feature_icon'],
                'is_active' => true,
            ],
            [
                'section' => 'features',
                'key' => 'feature_4_title',
                'value' => 'Cognitive Enhancement',
                'metadata' => ['type' => 'feature_title'],
                'is_active' => true,
            ],
            [
                'section' => 'features',
                'key' => 'feature_4_description',
                'value' => 'Enhance your cognitive abilities and mental resilience with scientifically backed techniques and exercises.',
                'metadata' => ['type' => 'feature_description'],
                'is_active' => true,
            ],
            [
                'section' => 'features',
                'key' => 'feature_4_icon',
                'value' => '/icons/group.svg',
                'metadata' => ['type' => 'feature_icon'],
                'is_active' => true,
            ],
            [
                'section' => 'features',
                'key' => 'feature_5_title',
                'value' => '24/7 Accessibility',
                'metadata' => ['type' => 'feature_title'],
                'is_active' => true,
            ],
            [
                'section' => 'features',
                'key' => 'feature_5_description',
                'value' => 'Access support anytime, anywhere, allowing you to address your mental well-being at your own pace and convenience.',
                'metadata' => ['type' => 'feature_description'],
                'is_active' => true,
            ],
            [
                'section' => 'features',
                'key' => 'feature_5_icon',
                'value' => '/icons/phone.svg',
                'metadata' => ['type' => 'feature_icon'],
                'is_active' => true,
            ],
            [
                'section' => 'features',
                'key' => 'feature_6_title',
                'value' => 'Confidential and Secure',
                'metadata' => ['type' => 'feature_title'],
                'is_active' => true,
            ],
            [
                'section' => 'features',
                'key' => 'feature_6_description',
                'value' => 'Rest assured knowing that your privacy and data security are our top priorities, ensuring a safe space for your journey to healing.',
                'metadata' => ['type' => 'feature_description'],
                'is_active' => true,
            ],
            [
                'section' => 'features',
                'key' => 'feature_6_icon',
                'value' => '/icons/lock.svg',
                'metadata' => ['type' => 'feature_icon'],
                'is_active' => true,
            ],

            // Benefit 1 - AI-Powered Assessment
            [
                'section' => 'benefit_1',
                'key' => 'benefit_label',
                'value' => 'Benefit #1',
                'metadata' => ['type' => 'badge', 'max_length' => 50],
                'is_active' => true,
            ],
            [
                'section' => 'benefit_1',
                'key' => 'benefit_title',
                'value' => 'AI-Powered Assessment',
                'metadata' => ['type' => 'headline', 'max_length' => 100],
                'is_active' => true,
            ],
            [
                'section' => 'benefit_1',
                'key' => 'benefit_description_1',
                'value' => 'Gain valuable insights into your mental well-being through our advanced AI-powered assessments.',
                'metadata' => ['type' => 'description', 'max_length' => 200],
                'is_active' => true,
            ],
            [
                'section' => 'benefit_1',
                'key' => 'benefit_description_2',
                'value' => 'Our intelligent algorithms analyze your responses to provide personalized assessments and recommendations for improvement.',
                'metadata' => ['type' => 'description', 'max_length' => 200],
                'is_active' => true,
            ],
            [
                'section' => 'benefit_1',
                'key' => 'stat_1_number',
                'value' => '99.5',
                'metadata' => ['type' => 'statistic', 'suffix' => '%'],
                'is_active' => true,
            ],
            [
                'section' => 'benefit_1',
                'key' => 'stat_1_label',
                'value' => 'connect rate',
                'metadata' => ['type' => 'statistic_label'],
                'is_active' => true,
            ],
            [
                'section' => 'benefit_1',
                'key' => 'stat_2_number',
                'value' => '25K',
                'metadata' => ['type' => 'statistic'],
                'is_active' => true,
            ],
            [
                'section' => 'benefit_1',
                'key' => 'stat_2_label',
                'value' => 'ai healthcare models',
                'metadata' => ['type' => 'statistic_label'],
                'is_active' => true,
            ],
            [
                'section' => 'benefit_1',
                'key' => 'image_path',
                'value' => '/images/benefit-1-mockup.png',
                'metadata' => ['type' => 'image'],
                'is_active' => true,
            ],

            // Benefit 2 - Self-Care Tools
            [
                'section' => 'benefit_2',
                'key' => 'benefit_label',
                'value' => 'Benefit #2',
                'metadata' => ['type' => 'badge', 'max_length' => 50],
                'is_active' => true,
            ],
            [
                'section' => 'benefit_2',
                'key' => 'benefit_title',
                'value' => 'Self-Care Tools and Resources',
                'metadata' => ['type' => 'headline', 'max_length' => 100],
                'is_active' => true,
            ],
            [
                'section' => 'benefit_2',
                'key' => 'benefit_description_1',
                'value' => 'Access professional support anytime, anywhere with our virtual therapy sessions.',
                'metadata' => ['type' => 'description', 'max_length' => 200],
                'is_active' => true,
            ],
            [
                'section' => 'benefit_2',
                'key' => 'benefit_description_2',
                'value' => 'Connect with licensed therapists through secure video calls and receive personalized counseling tailored to your specific needs.',
                'metadata' => ['type' => 'description', 'max_length' => 200],
                'is_active' => true,
            ],
            [
                'section' => 'benefit_2',
                'key' => 'checklist_item_1',
                'value' => 'Access personalized mental health tools',
                'metadata' => ['type' => 'checklist_item'],
                'is_active' => true,
            ],
            [
                'section' => 'benefit_2',
                'key' => 'checklist_item_2',
                'value' => 'Connect with licensed therapists',
                'metadata' => ['type' => 'checklist_item'],
                'is_active' => true,
            ],
            [
                'section' => 'benefit_2',
                'key' => 'checklist_item_3',
                'value' => 'Track your progress and gain insights',
                'metadata' => ['type' => 'checklist_item'],
                'is_active' => true,
            ],
            [
                'section' => 'benefit_2',
                'key' => 'image_path',
                'value' => '/images/benefit-2-mockup.png',
                'metadata' => ['type' => 'image'],
                'is_active' => true,
            ],

            // Benefit 3 - Emotional Support Chatbot
            [
                'section' => 'benefit_3',
                'key' => 'benefit_label',
                'value' => 'Benefit #3',
                'metadata' => ['type' => 'badge', 'max_length' => 50],
                'is_active' => true,
            ],
            [
                'section' => 'benefit_3',
                'key' => 'benefit_title',
                'value' => 'Emotional Support Chatbot',
                'metadata' => ['type' => 'headline', 'max_length' => 100],
                'is_active' => true,
            ],
            [
                'section' => 'benefit_3',
                'key' => 'benefit_description_1',
                'value' => 'Empower yourself with a range of self-care tools and resources.',
                'metadata' => ['type' => 'description', 'max_length' => 200],
                'is_active' => true,
            ],
            [
                'section' => 'benefit_3',
                'key' => 'benefit_description_2',
                'value' => 'Access personalized self-help modules, educational materials, and interactive exercises to foster your emotional growth and well-being.',
                'metadata' => ['type' => 'description', 'max_length' => 200],
                'is_active' => true,
            ],
            [
                'section' => 'benefit_3',
                'key' => 'accuracy_stat_number',
                'value' => '99.987%',
                'metadata' => ['type' => 'statistic'],
                'is_active' => true,
            ],
            [
                'section' => 'benefit_3',
                'key' => 'accuracy_stat_label',
                'value' => 'mental health ai accuracy',
                'metadata' => ['type' => 'statistic_label'],
                'is_active' => true,
            ],
            [
                'section' => 'benefit_3',
                'key' => 'image_path',
                'value' => '/images/benefit-3-mockup.png',
                'metadata' => ['type' => 'image'],
                'is_active' => true,
            ],

            // CTA Section - Start Your Journey
            [
                'section' => 'cta',
                'key' => 'cta_title',
                'value' => 'Start Your Mental Health Journey!',
                'metadata' => ['type' => 'headline', 'max_length' => 100],
                'is_active' => true,
            ],
            [
                'section' => 'cta',
                'key' => 'cta_description',
                'value' => 'Get started on your mental health journey today! Download the Onwynd app and experience the benefits of our innovative solutions:',
                'metadata' => ['type' => 'description', 'max_length' => 300],
                'is_active' => true,
            ],
            [
                'section' => 'cta',
                'key' => 'apple_store_text',
                'value' => 'Apple Store',
                'metadata' => ['type' => 'store_button', 'platform' => 'ios'],
                'is_active' => true,
            ],
            [
                'section' => 'cta',
                'key' => 'google_play_text',
                'value' => 'Google Play',
                'metadata' => ['type' => 'store_button', 'platform' => 'android'],
                'is_active' => true,
            ],
            [
                'section' => 'cta',
                'key' => 'dashboard_image',
                'value' => '/images/dashboard-mockup.png',
                'metadata' => ['type' => 'image'],
                'is_active' => true,
            ],

            // Blog Section
            [
                'section' => 'blog',
                'key' => 'section_label',
                'value' => 'Blog Posts',
                'metadata' => ['type' => 'badge', 'max_length' => 50],
                'is_active' => true,
            ],
            [
                'section' => 'blog',
                'key' => 'section_title',
                'value' => 'Browse Our Latest News',
                'metadata' => ['type' => 'headline', 'max_length' => 100],
                'is_active' => true,
            ],
            [
                'section' => 'blog',
                'key' => 'see_all_button_text',
                'value' => 'See All',
                'metadata' => ['type' => 'cta', 'style' => 'primary'],
                'is_active' => true,
            ],
        ];

        foreach ($content as $item) {
            LandingPageContent::create($item);
        }
    }
}
