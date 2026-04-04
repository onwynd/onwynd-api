<?php

namespace Database\Seeders;

use App\Models\KnowledgeBaseArticle;
use App\Models\KnowledgeBaseCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class KnowledgeBaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure we have a user to be the author
        $author = User::first() ?? User::factory()->create();

        // 1. FAQ Categories & Questions
        $faqCategories = [
            [
                'title' => 'Getting Started',
                'icon' => '/icons/heartbeat.svg',
                'questions' => [
                    [
                        'question' => 'What is Onwynd and how does it work?',
                        'answer' => 'Onwynd is an AI-powered mental health platform that provides personalized support through conversational AI. Our system uses advanced machine learning models trained on mental health best practices to offer empathetic guidance, assessments, and coping strategies 24/7.',
                    ],
                    [
                        'question' => 'Is Onwynd a replacement for therapy or medication?',
                        'answer' => 'No, Onwynd is not a replacement for professional medical advice, diagnosis, or treatment. We provide supplementary support and resources that can complement traditional therapy. If you\'re experiencing a mental health crisis, please contact emergency services or a mental health professional immediately.',
                    ],
                    [
                        'question' => 'How do I create an account?',
                        'answer' => 'Creating an account is simple! Click the "Sign Up" button, provide your email address, create a secure password, and complete a brief initial assessment. You\'ll be chatting with our AI in minutes.',
                    ],
                    [
                        'question' => 'Is there a free trial available?',
                        'answer' => 'Yes! We offer a 14-day free trial that gives you full access to all features. No credit card required to start. After the trial, you can choose from our flexible subscription plans.',
                    ],
                ],
            ],
            [
                'title' => 'Privacy & Security',
                'icon' => '/icons/lock.svg',
                'questions' => [
                    [
                        'question' => 'How secure is my information?',
                        'answer' => 'We take your privacy extremely seriously. All conversations are encrypted end-to-end, and we\'re fully HIPAA compliant. Your data is stored on secure servers, and we never sell your personal information to third parties. Read our Privacy Policy for complete details.',
                    ],
                    [
                        'question' => 'Who can see my conversations with the AI?',
                        'answer' => 'Your conversations are completely private and confidential. Only you have access to your chat history. Our AI processes your messages to provide support, but no human staff members review your conversations unless you explicitly request human oversight or in emergency situations.',
                    ],
                    [
                        'question' => 'Can I delete my data?',
                        'answer' => 'Yes, you have complete control over your data. You can download a copy of your information at any time, and you can request complete deletion of your account and all associated data from your account settings.',
                    ],
                ],
            ],
            [
                'title' => 'Features & Functionality',
                'icon' => '/icons/control.svg',
                'questions' => [
                    [
                        'question' => 'What types of mental health issues can Onwynd help with?',
                        'answer' => 'Our AI is trained to provide support for a wide range of concerns including anxiety, depression, stress management, relationship issues, self-esteem, and general emotional wellbeing. However, we are not equipped to handle severe mental health crises or emergencies.',
                    ],
                    [
                        'question' => 'How accurate is the AI assessment?',
                        'answer' => 'Our assessments use clinically validated questionnaires and achieve 99.5% accuracy in identifying mental health patterns. However, these assessments are screening tools, not diagnostic instruments. We always recommend consulting with a licensed professional for official diagnosis.',
                    ],
                    [
                        'question' => 'Can I use Onwynd on multiple devices?',
                        'answer' => 'Absolutely! Your account syncs across all devices. Start a conversation on your phone and continue it on your laptop seamlessly. We have apps for iOS and Android, plus a fully-featured web platform.',
                    ],
                    [
                        'question' => 'What languages does Onwynd support?',
                        'answer' => 'Currently, Onwynd is available in English, Spanish, French, German, Portuguese, and Mandarin Chinese. We\'re continuously working to add more languages to make mental health support accessible globally.',
                    ],
                ],
            ],
            [
                'title' => 'Subscription & Billing',
                'icon' => '/icons/check.svg',
                'questions' => [
                    [
                        'question' => 'What subscription plans are available?',
                        'answer' => 'We offer three plans: Basic ($9.99/month) for AI chat support, Plus ($19.99/month) with assessments and progress tracking, and Premium ($39.99/month) which includes virtual therapy sessions with licensed professionals. Annual plans save you 20%.',
                    ],
                    [
                        'question' => 'Can I cancel my subscription anytime?',
                        'answer' => 'Yes, you can cancel your subscription at any time from your account settings. Your access will continue until the end of your current billing period. We don\'t believe in trapping you in contracts.',
                    ],
                    [
                        'question' => 'Do you offer student or financial assistance discounts?',
                        'answer' => 'Yes! We offer 50% off for verified students and have a financial assistance program for those who qualify. We believe everyone deserves access to mental health support. Contact our support team to learn more.',
                    ],
                    [
                        'question' => 'What payment methods do you accept?',
                        'answer' => 'We accept all major credit cards (Visa, Mastercard, American Express, Discover), PayPal, and Apple Pay. All transactions are processed securely through industry-leading payment processors.',
                    ],
                ],
            ],
            [
                'title' => 'Technical Support',
                'icon' => '/icons/settings.svg',
                'questions' => [
                    [
                        'question' => 'The app isn\'t working properly. What should I do?',
                        'answer' => 'First, try refreshing the page or restarting the app. Make sure you\'re using the latest version. If problems persist, clear your cache and cookies. Still having issues? Contact our support team at support@onwynd.com with details about your device and the problem you\'re experiencing.',
                    ],
                    [
                        'question' => 'What are the system requirements?',
                        'answer' => 'For web: Any modern browser (Chrome, Firefox, Safari, Edge) updated to the latest version. For mobile: iOS 14+ or Android 8+. We recommend a stable internet connection for the best experience.',
                    ],
                    [
                        'question' => 'Can I use Onwynd offline?',
                        'answer' => 'While you can access previously loaded content offline, real-time AI conversations require an internet connection. We\'re working on offline capabilities for basic features in future updates.',
                    ],
                ],
            ],
        ];

        foreach ($faqCategories as $catData) {
            $category = KnowledgeBaseCategory::firstOrCreate(
                ['slug' => Str::slug($catData['title'])],
                [
                    'name' => $catData['title'],
                    'icon' => $catData['icon'],
                    'description' => "FAQs about {$catData['title']}",
                    'order' => 0, // Prioritize FAQs or put them at the end?
                    'type' => 'public',
                ]
            );

            foreach ($catData['questions'] as $qData) {
                KnowledgeBaseArticle::firstOrCreate(
                    ['slug' => Str::slug($qData['question'])],
                    [
                        'title' => $qData['question'],
                        'content' => $qData['answer'],
                        'category_id' => $category->id,
                        'author_id' => $author->id,
                        'status' => 'published',
                        'visibility' => 'public',
                        'published_at' => now(),
                    ]
                );
            }
        }

        // 2. Knowledge Base Articles (from Web App)
        $articleCategories = [
            'basics' => ['label' => 'Mental Health Basics', 'icon' => 'GraduationCapIcon'],
            'techniques' => ['label' => 'Coping Techniques', 'icon' => 'LightbulbIcon'],
            'conditions' => ['label' => 'Understanding Conditions', 'icon' => 'BrainIcon'],
            'relationships' => ['label' => 'Relationships', 'icon' => 'UsersIcon'],
            'wellness' => ['label' => 'Wellness & Self-Care', 'icon' => 'HeartIcon'],
        ];

        $articles = [
            [
                'title' => 'Understanding Anxiety: A Complete Guide',
                'description' => 'Learn about the different types of anxiety disorders, their symptoms, and evidence-based strategies for managing anxiety in daily life.',
                'category' => 'conditions',
                'readTime' => '8 min',
                'tags' => ['Anxiety', 'Stress', 'Mental Health'],
                'slug' => 'understanding-anxiety-complete-guide',
                'content' => 'Full content for Understanding Anxiety...', // Placeholder or real content
            ],
            [
                'title' => 'Mindfulness Meditation for Beginners',
                'description' => 'A step-by-step guide to starting your mindfulness practice, including breathing exercises and daily meditation routines.',
                'category' => 'techniques',
                'readTime' => '6 min',
                'tags' => ['Mindfulness', 'Meditation', 'Wellness'],
                'slug' => 'mindfulness-meditation-beginners',
                'content' => 'Full content for Mindfulness Meditation...',
            ],
            [
                'title' => 'Building Healthy Relationships',
                'description' => 'Discover the keys to maintaining strong, supportive relationships and effective communication strategies.',
                'category' => 'relationships',
                'readTime' => '10 min',
                'tags' => ['Relationships', 'Communication', 'Support'],
                'slug' => 'building-healthy-relationships',
                'content' => 'Full content for Building Healthy Relationships...',
            ],
            [
                'title' => 'Recognizing Depression Symptoms',
                'description' => 'Understanding the signs of depression and when to seek professional help. Includes self-assessment tools.',
                'category' => 'conditions',
                'readTime' => '7 min',
                'tags' => ['Depression', 'Mental Health', 'Awareness'],
                'slug' => 'recognizing-depression-symptoms',
                'content' => 'Full content for Recognizing Depression Symptoms...',
            ],
            [
                'title' => 'The Power of Self-Care',
                'description' => 'Why self-care is not selfish and practical ways to incorporate it into your daily routine.',
                'category' => 'wellness',
                'readTime' => '5 min',
                'tags' => ['Self-Care', 'Wellness', 'Lifestyle'],
                'slug' => 'power-of-self-care',
                'content' => 'Full content for The Power of Self-Care...',
            ],
        ];

        $order = 1;
        foreach ($articleCategories as $key => $data) {
            $cat = KnowledgeBaseCategory::firstOrCreate(
                ['slug' => $key],
                [
                    'name' => $data['label'],
                    'icon' => $data['icon'],
                    'description' => "Articles about {$data['label']}",
                    'order' => $order++,
                    'type' => 'public',
                ]
            );

            // Seed 5 random articles for each category if not specific ones
            // Also seed specific ones
            foreach ($articles as $article) {
                if ($article['category'] === $key) {
                    KnowledgeBaseArticle::firstOrCreate(
                        ['slug' => $article['slug']],
                        [
                            'title' => $article['title'],
                            'summary' => $article['description'],
                            'content' => $article['content'],
                            'category_id' => $cat->id,
                            'author_id' => $author->id,
                            'status' => 'published',
                            'visibility' => 'public',
                            'tags' => $article['tags'],
                            'metadata' => ['read_time' => $article['readTime']],
                            'published_at' => now(),
                        ]
                    );
                }
            }
        }

        // 3. Corporate / Modus Operandi (Internal)
        $corporateCategories = [
            'company-policy' => ['label' => 'Company Policy', 'icon' => 'BriefcaseIcon'],
            'standard-operating-procedures' => ['label' => 'SOPs', 'icon' => 'ClipboardListIcon'],
            'employee-handbook' => ['label' => 'Employee Handbook', 'icon' => 'BookOpenIcon'],
        ];

        $corporateArticles = [
            [
                'title' => 'Code of Conduct',
                'description' => 'Our expectations for professional behavior and ethics.',
                'category' => 'company-policy',
                'readTime' => '15 min',
                'tags' => ['Policy', 'Ethics', 'Compliance'],
                'slug' => 'code-of-conduct',
                'content' => '<h1>Code of Conduct</h1><p>Our company values integrity, respect, and innovation...</p>',
            ],
            [
                'title' => 'Remote Work Guidelines',
                'description' => 'Policies and best practices for working remotely.',
                'category' => 'company-policy',
                'readTime' => '10 min',
                'tags' => ['Remote Work', 'Policy', 'HR'],
                'slug' => 'remote-work-guidelines',
                'content' => '<h1>Remote Work Guidelines</h1><p>We support a flexible work environment...</p>',
            ],
            [
                'title' => 'Client Onboarding SOP',
                'description' => 'Step-by-step process for onboarding new enterprise clients.',
                'category' => 'standard-operating-procedures',
                'readTime' => '20 min',
                'tags' => ['SOP', 'Sales', 'Onboarding'],
                'slug' => 'client-onboarding-sop',
                'content' => '<h1>Client Onboarding SOP</h1><ol><li>Initial contact</li><li>Contract signing</li>...</ol>',
            ],
            [
                'title' => 'Incident Response Plan',
                'description' => 'Procedures for handling security incidents and data breaches.',
                'category' => 'standard-operating-procedures',
                'readTime' => '25 min',
                'tags' => ['SOP', 'Security', 'Emergency'],
                'slug' => 'incident-response-plan',
                'content' => '<h1>Incident Response Plan</h1><p>In the event of a security breach...</p>',
            ],
            [
                'title' => 'Benefits Overview',
                'description' => 'Detailed explanation of health insurance, PTO, and other benefits.',
                'category' => 'employee-handbook',
                'readTime' => '12 min',
                'tags' => ['HR', 'Benefits', 'Employee'],
                'slug' => 'benefits-overview',
                'content' => '<h1>Benefits Overview</h1><p>We offer comprehensive benefits including...</p>',
            ],
        ];

        foreach ($corporateCategories as $key => $data) {
            $cat = KnowledgeBaseCategory::firstOrCreate(
                ['slug' => $key],
                [
                    'name' => $data['label'],
                    'icon' => $data['icon'],
                    'description' => "Corporate documents about {$data['label']}",
                    'order' => $order++, // Continue ordering
                    'type' => 'corporate',
                ]
            );

            foreach ($corporateArticles as $article) {
                if ($article['category'] === $key) {
                    KnowledgeBaseArticle::firstOrCreate(
                        ['slug' => $article['slug']],
                        [
                            'title' => $article['title'],
                            'summary' => $article['description'],
                            'content' => $article['content'],
                            'category_id' => $cat->id,
                            'author_id' => $author->id,
                            'status' => 'published',
                            'visibility' => 'internal', // Or corporate
                            'tags' => $article['tags'],
                            'metadata' => ['read_time' => $article['readTime']],
                            'published_at' => now(),
                        ]
                    );
                }
            }
        }
    }
}
