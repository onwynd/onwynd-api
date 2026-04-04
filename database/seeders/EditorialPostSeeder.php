<?php

namespace Database\Seeders;

use App\Models\EditorialCategory;
use App\Models\EditorialPost;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EditorialPostSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@onwynd.com')->first() ?? User::first();

        $mentalHealth = EditorialCategory::where('slug', 'mental-health')->first();
        $wellness = EditorialCategory::where('slug', 'wellness')->first();
        $selfCare = EditorialCategory::where('slug', 'self-care')->first();
        $mindfulness = EditorialCategory::where('slug', 'mindfulness')->first();
        $therapy = EditorialCategory::where('slug', 'therapy')->first();

        $posts = [
            [
                'data' => [
                    'uuid' => (string) Str::uuid(),
                    'title' => 'Understanding Anxiety: A Comprehensive Guide',
                    'slug' => 'understanding-anxiety-comprehensive-guide',
                    'excerpt' => 'Learn about anxiety disorders, their symptoms, causes, and effective coping strategies for managing daily challenges.',
                    'content' => $this->getAnxietyContent(),
                    'author_id' => $admin?->id,
                    'featured_image' => '/storage/editorial/anxiety-guide.jpg',
                    'status' => 'published',
                    'published_at' => now()->subDays(2),
                    'read_time_minutes' => 8,
                    'seo_meta' => ['title' => 'Understanding Anxiety: A Comprehensive Guide | Onwynd', 'description' => 'Discover everything you need to know about anxiety disorders, symptoms, and evidence-based coping strategies.'],
                ],
                'categories' => [$mentalHealth?->id],
            ],
            [
                'data' => [
                    'uuid' => (string) Str::uuid(),
                    'title' => '10 Daily Habits for Better Mental Health',
                    'slug' => '10-daily-habits-better-mental-health',
                    'excerpt' => 'Discover simple yet powerful daily habits that can significantly improve your mental wellbeing and overall quality of life.',
                    'content' => $this->getDailyHabitsContent(),
                    'author_id' => $admin?->id,
                    'featured_image' => '/storage/editorial/daily-habits.jpg',
                    'status' => 'published',
                    'published_at' => now()->subDays(5),
                    'read_time_minutes' => 6,
                    'seo_meta' => ['title' => '10 Daily Habits for Better Mental Health | Onwynd', 'description' => 'Transform your mental health with these 10 simple daily habits backed by science.'],
                ],
                'categories' => [$wellness?->id],
            ],
            [
                'data' => [
                    'uuid' => (string) Str::uuid(),
                    'title' => 'The Science of Mindfulness Meditation',
                    'slug' => 'science-of-mindfulness-meditation',
                    'excerpt' => 'Explore the neuroscience behind mindfulness meditation and how it can rewire your brain for better mental health.',
                    'content' => $this->getMindfulnessContent(),
                    'author_id' => $admin?->id,
                    'featured_image' => '/storage/editorial/mindfulness-science.jpg',
                    'status' => 'published',
                    'published_at' => now()->subDays(7),
                    'read_time_minutes' => 10,
                    'seo_meta' => ['title' => 'The Science of Mindfulness Meditation | Onwynd', 'description' => 'Discover how mindfulness meditation changes your brain and improves mental health.'],
                ],
                'categories' => [$mindfulness?->id],
            ],
            [
                'data' => [
                    'uuid' => (string) Str::uuid(),
                    'title' => 'Breaking the Stigma: Why Mental Health Matters',
                    'slug' => 'breaking-stigma-mental-health-matters',
                    'excerpt' => 'Join the conversation about mental health stigma and learn how we can create a more supportive society together.',
                    'content' => $this->getStigmaContent(),
                    'author_id' => $admin?->id,
                    'featured_image' => '/storage/editorial/breaking-stigma.jpg',
                    'status' => 'published',
                    'published_at' => now()->subDays(12),
                    'read_time_minutes' => 9,
                    'seo_meta' => ['title' => 'Breaking the Stigma: Why Mental Health Matters | Onwynd', 'description' => 'Let\'s break the stigma around mental health and create a supportive community.'],
                ],
                'categories' => [$mentalHealth?->id],
            ],
            [
                'data' => [
                    'uuid' => (string) Str::uuid(),
                    'title' => 'Self-Care Isn\'t Selfish: A Guide for Busy Professionals',
                    'slug' => 'self-care-isnt-selfish-guide-busy-professionals',
                    'excerpt' => 'Learn why self-care is essential for productivity and how busy professionals can incorporate it into their routines.',
                    'content' => $this->getSelfCareContent(),
                    'author_id' => $admin?->id,
                    'featured_image' => '/storage/editorial/self-care-professionals.jpg',
                    'status' => 'published',
                    'published_at' => now()->subDays(15),
                    'read_time_minutes' => 5,
                    'seo_meta' => ['title' => 'Self-Care for Busy Professionals | Onwynd', 'description' => 'Practical self-care strategies for busy professionals to maintain mental wellness.'],
                ],
                'categories' => [$selfCare?->id],
            ],
            [
                'data' => [
                    'uuid' => (string) Str::uuid(),
                    'title' => 'How AI is Transforming Mental Health Support',
                    'slug' => 'ai-transforming-mental-health-support',
                    'excerpt' => 'Discover how artificial intelligence is making mental health support more accessible, personalized, and effective.',
                    'content' => $this->getAIContent(),
                    'author_id' => $admin?->id,
                    'featured_image' => '/storage/editorial/ai-mental-health.jpg',
                    'status' => 'published',
                    'published_at' => now()->subDays(18),
                    'read_time_minutes' => 11,
                    'seo_meta' => ['title' => 'How AI is Transforming Mental Health Support | Onwynd', 'description' => 'Explore the role of AI in revolutionizing mental health care and support.'],
                ],
                'categories' => [$mentalHealth?->id, $therapy?->id],
            ],
            [
                'data' => [
                    'uuid' => (string) Str::uuid(),
                    'title' => 'Managing Depression: Expert Tips and Resources',
                    'slug' => 'managing-depression-expert-tips-resources',
                    'excerpt' => 'Comprehensive guide to understanding and managing depression with expert advice and helpful resources.',
                    'content' => $this->getDepressionContent(),
                    'author_id' => $admin?->id,
                    'featured_image' => '/storage/editorial/managing-depression.jpg',
                    'status' => 'published',
                    'published_at' => now()->subDays(20),
                    'read_time_minutes' => 12,
                    'seo_meta' => ['title' => 'Managing Depression: Expert Tips and Resources | Onwynd', 'description' => 'Evidence-based strategies for managing depression and improving mental health.'],
                ],
                'categories' => [$mentalHealth?->id, $therapy?->id],
            ],
            [
                'data' => [
                    'uuid' => (string) Str::uuid(),
                    'title' => 'The Power of Gratitude: A Mental Health Practice',
                    'slug' => 'power-of-gratitude-mental-health-practice',
                    'excerpt' => 'Learn how practicing gratitude can transform your mental health and bring more joy into your daily life.',
                    'content' => $this->getGratitudeContent(),
                    'author_id' => $admin?->id,
                    'featured_image' => '/storage/editorial/gratitude-practice.jpg',
                    'status' => 'published',
                    'published_at' => now()->subDays(23),
                    'read_time_minutes' => 6,
                    'seo_meta' => ['title' => 'The Power of Gratitude for Mental Health | Onwynd', 'description' => 'Discover how gratitude practice can improve your mental wellbeing and happiness.'],
                ],
                'categories' => [$wellness?->id, $mindfulness?->id],
            ],
        ];

        foreach ($posts as $item) {
            $post = EditorialPost::firstOrCreate(
                ['slug' => $item['data']['slug']],
                $item['data']
            );

            $categoryIds = array_values(array_filter($item['categories'] ?? []));
            if (! empty($categoryIds)) {
                $post->categories()->syncWithoutDetaching($categoryIds);
            }
        }

        $this->command->info('Editorial posts seeded successfully.');
    }

    private function getAnxietyContent(): string
    {
        return '<h2>What is Anxiety?</h2>
<p>Anxiety is a normal and often healthy emotion. However, when a person regularly feels disproportionate levels of anxiety, it might become a medical disorder. Anxiety disorders form a category of mental health diagnoses that lead to excessive nervousness, fear, apprehension, and worry.</p>

<h2>Common Symptoms</h2>
<ul>
<li>Feeling nervous, restless, or tense</li>
<li>Having a sense of impending danger, panic, or doom</li>
<li>Increased heart rate</li>
<li>Rapid breathing (hyperventilation)</li>
<li>Sweating and trembling</li>
<li>Feeling weak or tired</li>
<li>Difficulty concentrating</li>
<li>Trouble sleeping</li>
</ul>

<h2>Effective Coping Strategies</h2>
<p>Managing anxiety requires a multi-faceted approach. Here are some evidence-based strategies:</p>

<h3>1. Practice Deep Breathing</h3>
<p>Deep breathing exercises can activate your body\'s relaxation response and help reduce anxiety symptoms.</p>

<h3>2. Regular Exercise</h3>
<p>Physical activity releases endorphins and can significantly reduce anxiety levels.</p>

<h3>3. Mindfulness and Meditation</h3>
<p>These practices can help you stay present and reduce anxious thoughts about the future.</p>

<h3>4. Professional Support</h3>
<p>Don\'t hesitate to seek help from a mental health professional. Therapy and, in some cases, medication can be very effective.</p>

<h2>When to Seek Help</h2>
<p>If anxiety is interfering with your daily life, relationships, or work, it\'s time to reach out for professional help. Remember, seeking help is a sign of strength, not weakness.</p>';
    }

    private function getDailyHabitsContent(): string
    {
        return '<h2>Transform Your Mental Health with Daily Habits</h2>
<p>Small, consistent actions compound over time to create significant improvements in mental wellbeing. Here are 10 habits you can start today:</p>

<h3>1. Morning Mindfulness (5 minutes)</h3>
<p>Start your day with 5 minutes of meditation or deep breathing to set a calm tone for the day ahead.</p>

<h3>2. Gratitude Journaling</h3>
<p>Write down three things you\'re grateful for each morning. This simple practice can shift your mindset toward positivity.</p>

<h3>3. Regular Exercise</h3>
<p>Aim for at least 30 minutes of physical activity. It doesn\'t have to be intense—a walk in nature counts!</p>

<h3>4. Healthy Sleep Routine</h3>
<p>Go to bed and wake up at the same time every day. Quality sleep is foundational for mental health.</p>

<h3>5. Limit Social Media</h3>
<p>Set boundaries around social media use to reduce comparison and information overload.</p>

<h3>6. Connect with Loved Ones</h3>
<p>Make time for meaningful connections, even if it\'s just a quick call or text.</p>

<h3>7. Take Breaks</h3>
<p>Regular breaks throughout the day help prevent burnout and maintain focus.</p>

<h3>8. Practice Self-Compassion</h3>
<p>Treat yourself with the same kindness you\'d offer a good friend.</p>

<h3>9. Engage in a Hobby</h3>
<p>Dedicate time to activities you enjoy just for the pleasure of doing them.</p>

<h3>10. Evening Wind-Down</h3>
<p>Create a relaxing evening routine to signal to your body it\'s time to rest.</p>

<h2>Start Small</h2>
<p>Don\'t try to implement all these habits at once. Choose one or two to start, and build from there. Consistency is more important than perfection.</p>';
    }

    private function getMindfulnessContent(): string
    {
        return '<h2>The Neuroscience Behind Mindfulness</h2>
<p>Mindfulness meditation isn\'t just a trend—it\'s a practice backed by robust scientific research showing real changes in brain structure and function.</p>

<h2>How Mindfulness Changes Your Brain</h2>

<h3>1. Increased Gray Matter Density</h3>
<p>Studies using MRI scans have shown that regular mindfulness practice increases gray matter density in areas of the brain associated with learning, memory, and emotional regulation.</p>

<h3>2. Reduced Amygdala Activity</h3>
<p>The amygdala, your brain\'s "alarm system," shows decreased activity with regular mindfulness practice, leading to reduced stress and anxiety responses.</p>

<h3>3. Strengthened Prefrontal Cortex</h3>
<p>This area, responsible for executive functions like decision-making and emotional regulation, becomes more active and better connected with other brain regions.</p>

<h2>Mental Health Benefits</h2>
<ul>
<li>Reduced symptoms of anxiety and depression</li>
<li>Improved emotional regulation</li>
<li>Enhanced focus and concentration</li>
<li>Better stress management</li>
<li>Increased self-awareness</li>
<li>Improved sleep quality</li>
</ul>

<h2>Getting Started with Mindfulness</h2>
<p>You don\'t need special equipment or hours of free time. Start with just 5 minutes a day:</p>
<ol>
<li>Find a quiet space</li>
<li>Sit comfortably</li>
<li>Focus on your breath</li>
<li>When your mind wanders (and it will), gently bring attention back to your breath</li>
<li>Be patient and compassionate with yourself</li>
</ol>

<h2>The Bottom Line</h2>
<p>Mindfulness meditation is a powerful tool for mental health that literally changes your brain for the better. The key is consistency—even a few minutes daily can make a significant difference.</p>';
    }

    private function getStigmaContent(): string
    {
        return '<h2>The Mental Health Stigma Problem</h2>
<p>Despite growing awareness, stigma surrounding mental health remains one of the biggest barriers to people seeking help. This stigma can be internal (self-stigma) or external (public stigma), and both can be equally damaging.</p>

<h2>Why Stigma Exists</h2>
<ul>
<li>Lack of education and understanding</li>
<li>Media misrepresentation</li>
<li>Cultural beliefs and traditions</li>
<li>Fear of the unknown</li>
<li>Historical misconceptions about mental illness</li>
</ul>

<h2>The Real Impact</h2>

<h3>Delayed Treatment</h3>
<p>Many people wait years before seeking help due to shame or fear of judgment. Early intervention is crucial for better outcomes.</p>

<h3>Workplace Discrimination</h3>
<p>People often hide mental health struggles at work, fearing it will impact their careers or how colleagues perceive them.</p>

<h3>Social Isolation</h3>
<p>Stigma can lead people to withdraw from social connections, which ironically worsens mental health conditions.</p>

<h2>Breaking the Stigma: What We Can Do</h2>

<h3>1. Talk Openly</h3>
<p>Share your own experiences when appropriate. Normalize the conversation around mental health.</p>

<h3>2. Educate Ourselves and Others</h3>
<p>Learn about mental health conditions and challenge misconceptions when you encounter them.</p>

<h3>3. Watch Our Language</h3>
<p>Words matter. Avoid using mental health conditions as adjectives or jokes.</p>

<h3>4. Support, Don\'t Judge</h3>
<p>If someone confides in you about their mental health, listen without judgment and offer support.</p>

<h3>5. Advocate for Change</h3>
<p>Support policies and initiatives that promote mental health awareness and access to care.</p>

<h2>The Truth About Mental Health</h2>
<ul>
<li>Mental health conditions are medical conditions, not character flaws</li>
<li>They can affect anyone, regardless of age, gender, race, or socioeconomic status</li>
<li>Treatment is effective, and recovery is possible</li>
<li>Seeking help is a sign of strength, not weakness</li>
</ul>

<h2>Moving Forward</h2>
<p>Breaking stigma starts with each of us. By changing how we think and talk about mental health, we create a world where everyone feels safe seeking the help they need and deserve.</p>

<p>Remember: There is no health without mental health.</p>';
    }

    private function getSelfCareContent(): string
    {
        return '<h2>The Self-Care Misconception</h2>
<p>Many busy professionals view self-care as selfish or indulgent. This couldn\'t be further from the truth. Self-care is essential maintenance that enables you to perform at your best and sustain your success long-term.</p>

<h2>Why Self-Care Matters for Professionals</h2>

<h3>1. Prevents Burnout</h3>
<p>Regular self-care practices help prevent the physical and emotional exhaustion that leads to burnout.</p>

<h3>2. Improves Decision-Making</h3>
<p>A well-rested, balanced mind makes better decisions than an exhausted one.</p>

<h3>3. Enhances Creativity</h3>
<p>Taking breaks and caring for yourself creates space for creative thinking and innovation.</p>

<h3>4. Boosts Productivity</h3>
<p>Counterintuitively, taking time for self-care actually increases overall productivity.</p>

<h2>Practical Self-Care for Busy Schedules</h2>

<h3>Micro-Breaks (2-5 minutes)</h3>
<ul>
<li>Deep breathing exercises between meetings</li>
<li>Stretch at your desk</li>
<li>Step outside for fresh air</li>
<li>Quick meditation using an app</li>
</ul>

<h3>Boundary Setting</h3>
<ul>
<li>Turn off work notifications after hours</li>
<li>Block "focus time" on your calendar</li>
<li>Learn to say no to non-essential commitments</li>
<li>Communicate your availability clearly</li>
</ul>

<h3>Daily Non-Negotiables</h3>
<ul>
<li>7-8 hours of sleep</li>
<li>Three balanced meals</li>
<li>Movement (even a 10-minute walk)</li>
<li>Connection with someone you care about</li>
</ul>

<h3>Weekly Essentials</h3>
<ul>
<li>One complete day off from work</li>
<li>Engaging in a hobby or interest</li>
<li>Social connection with friends or family</li>
<li>Time in nature if possible</li>
</ul>

<h2>The Bottom Line</h2>
<p>Self-care isn\'t selfish—it\'s necessary. Taking care of yourself enables you to show up better for your work, your loved ones, and your goals. You deserve to feel well, not just to be productive.</p>';
    }

    private function getAIContent(): string
    {
        return '<h2>The Mental Health Care Crisis</h2>
<p>Mental health services face unprecedented challenges: long waitlists, high costs, geographical barriers, and a shortage of mental health professionals. Enter artificial intelligence—a technology that\'s beginning to transform how we approach mental health support.</p>

<h2>How AI is Making a Difference</h2>

<h3>1. 24/7 Accessibility</h3>
<p>AI-powered chatbots like Onwynd provide immediate support anytime, anywhere. No appointments needed, no waitlists, no geographical barriers.</p>

<h3>2. Personalized Support</h3>
<p>Machine learning algorithms can analyze patterns in your communication and provide increasingly personalized responses and recommendations.</p>

<h3>3. Reduced Stigma</h3>
<p>For many, talking to an AI feels less intimidating than opening up to a human therapist for the first time. It can be a comfortable first step.</p>

<h3>4. Affordable Care</h3>
<p>AI significantly reduces the cost of mental health support, making it accessible to more people.</p>

<h3>5. Early Intervention</h3>
<p>AI can detect early warning signs of mental health issues and encourage users to seek professional help when needed.</p>

<h2>What AI Can Do</h2>
<ul>
<li>Provide empathetic, non-judgmental conversation</li>
<li>Offer evidence-based coping strategies</li>
<li>Help track moods and identify patterns</li>
<li>Guide meditation and breathing exercises</li>
<li>Provide psychoeducation about mental health</li>
<li>Connect users with crisis resources when needed</li>
</ul>

<h2>What AI Cannot Do</h2>
<p>It\'s important to be clear about limitations:</p>
<ul>
<li>AI cannot diagnose mental health conditions</li>
<li>It cannot prescribe medication</li>
<li>It shouldn\'t replace professional therapy for serious mental health issues</li>
<li>It cannot provide the human connection of a therapist</li>
</ul>

<h2>The Best of Both Worlds</h2>
<p>The future isn\'t AI replacing human therapists—it\'s AI and human professionals working together. AI for immediate support and daily check-ins, human therapists for diagnosis, treatment planning, and complex cases.</p>

<h2>Looking Ahead</h2>
<p>As AI technology continues to evolve, we can expect even more sophisticated support, better personalization, and improved integration with traditional mental health services. The goal is to ensure everyone has access to support when they need it.</p>';
    }

    private function getDepressionContent(): string
    {
        return '<h2>Understanding Depression</h2>
<p>Depression is more than just feeling sad or going through a rough patch. It\'s a serious mental health condition that affects how you feel, think, and handle daily activities.</p>

<h2>Common Symptoms</h2>
<ul>
<li>Persistent sad, anxious, or "empty" mood</li>
<li>Feelings of hopelessness or pessimism</li>
<li>Irritability, frustration, or restlessness</li>
<li>Loss of interest in activities once enjoyed</li>
<li>Fatigue and decreased energy</li>
<li>Difficulty concentrating or making decisions</li>
<li>Changes in sleep patterns</li>
<li>Changes in appetite or weight</li>
<li>Thoughts of death or suicide</li>
</ul>

<h2>Evidence-Based Treatment Approaches</h2>

<h3>1. Psychotherapy</h3>
<p><strong>Cognitive Behavioral Therapy (CBT):</strong> Helps identify and change negative thought patterns and behaviors.</p>
<p><strong>Interpersonal Therapy:</strong> Focuses on improving relationships and communication skills.</p>

<h3>2. Medication</h3>
<p>Antidepressants can be effective, especially for moderate to severe depression. Always work with a healthcare provider to find the right medication and dosage.</p>

<h3>3. Lifestyle Changes</h3>
<ul>
<li>Regular exercise (as effective as medication for mild to moderate depression)</li>
<li>Consistent sleep schedule</li>
<li>Balanced nutrition</li>
<li>Reduced alcohol consumption</li>
<li>Stress management techniques</li>
</ul>

<h2>When to Seek Immediate Help</h2>
<p>If you\'re experiencing thoughts of suicide or self-harm, please reach out immediately:</p>
<ul>
<li>National Suicide Prevention Lifeline: 988</li>
<li>Crisis Text Line: Text HOME to 741741</li>
<li>Go to your nearest emergency room</li>
</ul>

<h2>Hope and Recovery</h2>
<p>Depression is treatable, and most people who seek help see significant improvement. Remember: Asking for help is a sign of strength, not weakness. You don\'t have to face depression alone.</p>';
    }

    private function getGratitudeContent(): string
    {
        return '<h2>The Science of Gratitude</h2>
<p>Gratitude isn\'t just a nice feeling—it\'s a powerful mental health practice backed by extensive research. Studies show that regularly practicing gratitude can lead to significant improvements in mental wellbeing.</p>

<h2>Mental Health Benefits</h2>

<h3>Improved Mood</h3>
<p>Regular gratitude practice increases positive emotions and reduces symptoms of depression.</p>

<h3>Better Sleep</h3>
<p>People who practice gratitude fall asleep faster and sleep more soundly.</p>

<h3>Reduced Stress</h3>
<p>Gratitude helps reframe stressful situations and reduces cortisol levels.</p>

<h3>Enhanced Relationships</h3>
<p>Expressing gratitude strengthens social bonds and increases relationship satisfaction.</p>

<h2>How to Practice Gratitude</h2>

<h3>1. Gratitude Journaling</h3>
<p>Write down 3-5 things you\'re grateful for each day. Be specific rather than generic.</p>

<h3>2. Gratitude Letters</h3>
<p>Write a letter to someone you\'re grateful for. Whether you send it or not, the act of writing it has mental health benefits.</p>

<h3>3. Gratitude Meditation</h3>
<p>Spend a few minutes focusing on things you\'re grateful for, savoring the positive feelings.</p>

<h3>4. Gratitude Walk</h3>
<p>During your walk, notice things around you to be grateful for—nature, architecture, kind strangers.</p>

<h2>Start Today</h2>
<p>You don\'t need anything special to begin. Just pause right now and think of three things you\'re grateful for. Notice how you feel. That\'s the power of gratitude—simple, free, and transformative.</p>';
    }
}
