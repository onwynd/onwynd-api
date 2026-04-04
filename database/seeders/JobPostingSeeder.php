<?php

namespace Database\Seeders;

use App\Models\JobPosting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class JobPostingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jobs = [
            [
                'title' => 'Senior AI Research Scientist',
                'department' => 'AI & Machine Learning',
                'location' => 'San Francisco, CA / Remote',
                'type' => 'Full-time',
                'salary_range' => '$180,000 - $250,000',
                'experience_level' => '5+ years',
                'description' => 'Lead research initiatives in conversational AI and mental health applications. You will be at the forefront of developing empathetic AI systems that can provide meaningful mental health support.',
                'responsibilities' => [
                    'Design and implement novel AI algorithms for mental health chatbot conversations',
                    'Research and develop emotion detection and response generation systems',
                    'Collaborate with clinical psychologists to ensure therapeutic accuracy',
                    'Publish research findings in top-tier AI and healthcare conferences',
                    'Mentor junior researchers and contribute to the research roadmap',
                    'Stay current with latest developments in NLP, LLMs, and mental health AI',
                ],
                'qualifications' => [
                    'PhD in Computer Science, AI, Machine Learning, or related field',
                    'Strong publication record in NLP, dialogue systems, or related areas',
                    'Experience with large language models (GPT, BERT, etc.)',
                    'Proficiency in Python and deep learning frameworks (PyTorch, TensorFlow)',
                    'Understanding of mental health domain and ethical AI principles',
                    'Excellent communication and collaboration skills',
                ],
                'benefits' => [
                    'Competitive salary and equity package',
                    'Comprehensive health, dental, and vision insurance',
                    'Unlimited PTO and flexible work arrangements',
                    'Professional development budget ($5,000/year)',
                    'Latest hardware and tools',
                    'Mental health support (naturally!)',
                    'Team retreats and company events',
                ],
            ],
            [
                'title' => 'Clinical Psychologist',
                'department' => 'Clinical Team',
                'location' => 'San Francisco, CA',
                'type' => 'Full-time',
                'salary_range' => '$140,000 - $180,000',
                'experience_level' => '7+ years',
                'description' => 'Oversee clinical protocols and validate therapeutic approaches in our AI system. You will ensure our platform maintains the highest standards of psychological care.',
                'responsibilities' => [
                    'Review and validate AI-generated therapeutic responses',
                    'Develop clinical frameworks and treatment protocols',
                    'Conduct research on AI-assisted therapy outcomes',
                    'Provide direct patient care through the platform',
                    'Train and supervise other therapists on the platform',
                    'Ensure compliance with ethical and legal standards',
                ],
                'qualifications' => [
                    'Licensed Clinical Psychologist (PhD or PsyD)',
                    'Active state licensure (California preferred)',
                    '7+ years of clinical experience',
                    'Experience with digital mental health platforms',
                    'Strong understanding of evidence-based therapies (CBT, DBT, etc.)',
                    'Passion for innovation in mental healthcare',
                ],
                'benefits' => [
                    'Competitive salary with performance bonuses',
                    'Comprehensive health benefits',
                    'Flexible scheduling options',
                    'CEU reimbursement',
                    'Malpractice insurance covered',
                    'Research opportunities',
                    'Collaborative, mission-driven culture',
                ],
            ],
            [
                'title' => 'Product Designer',
                'department' => 'Product & Design',
                'location' => 'Remote',
                'type' => 'Full-time',
                'salary_range' => '$120,000 - $160,000',
                'experience_level' => '4+ years',
                'description' => 'Design beautiful, empathetic user experiences for mental health support. Create interfaces that make therapy accessible and comforting.',
                'responsibilities' => [
                    'Design user flows and interfaces for web and mobile applications',
                    'Create prototypes and conduct user testing',
                    'Develop and maintain design system',
                    'Collaborate with product managers and engineers',
                    'Conduct user research and usability studies',
                    'Ensure accessibility standards are met',
                ],
                'qualifications' => [
                    'Bachelor\'s degree in Design or related field',
                    '4+ years of product design experience',
                    'Strong portfolio showcasing UX/UI work',
                    'Proficiency in Figma, Sketch, or similar tools',
                    'Understanding of mental health and empathetic design',
                    'Experience with design systems',
                ],
            ],
            [
                'title' => 'Full Stack Engineer',
                'department' => 'Engineering',
                'location' => 'San Francisco, CA / Remote',
                'type' => 'Full-time',
                'salary_range' => '$150,000 - $200,000',
                'experience_level' => '3+ years',
                'description' => 'Build scalable infrastructure for our mental health platform. Work across the stack to deliver reliable and secure features.',
                'responsibilities' => [
                    'Develop and maintain RESTful APIs and microservices',
                    'Build responsive frontend applications using React/Next.js',
                    'Optimize database queries and system performance',
                    'Ensure security and compliance (HIPAA) in all code',
                ],
                'qualifications' => [
                    'BS/MS in Computer Science or equivalent experience',
                    '3+ years of full-stack development experience',
                    'Proficiency in Node.js, Python, or Go',
                    'Experience with React, TypeScript, and modern CSS',
                    'Knowledge of cloud platforms (AWS/GCP)',
                ],
            ],
            [
                'title' => 'Data Scientist',
                'department' => 'AI & Machine Learning',
                'location' => 'San Francisco, CA / Remote',
                'type' => 'Full-time',
                'salary_range' => '$160,000 - $220,000',
                'experience_level' => '4+ years',
                'description' => 'Analyze user data to improve AI models and therapeutic outcomes. Turn complex data into actionable insights.',
                'responsibilities' => [
                    'Analyze user interaction data to identify patterns',
                    'Build predictive models for user engagement and outcomes',
                    'Design and analyze A/B tests',
                    'Create dashboards and reports for stakeholders',
                ],
                'qualifications' => [
                    'MS/PhD in Statistics, Mathematics, or Computer Science',
                    'Strong SQL and Python skills',
                    'Experience with data visualization tools',
                    'Background in behavioral science or healthcare is a plus',
                ],
            ],
            [
                'title' => 'Content Strategist',
                'department' => 'Marketing',
                'location' => 'Remote',
                'type' => 'Full-time',
                'salary_range' => '$90,000 - $130,000',
                'experience_level' => '3+ years',
                'description' => 'Create compelling content that educates and engages our community. Shape the voice of Onwynd.',
                'responsibilities' => [
                    'Develop content strategy across blog, social, and email',
                    'Write and edit high-quality articles on mental health',
                    'Manage content calendar and freelance writers',
                    'Analyze content performance and optimize SEO',
                ],
                'qualifications' => [
                    'Bachelor\'s degree in Communications, English, or related field',
                    '3+ years of content marketing experience',
                    'Excellent writing and editing skills',
                    'Understanding of SEO best practices',
                ],
            ],

            // ─── FIRST FIVE — IMMEDIATE PRIORITY HIRES ───────────────────────

            [
                'title' => 'Partnership & Relationship Manager',
                'department' => 'Business Development',
                'location' => 'Lagos, Nigeria',
                'type' => 'Full-time',
                'salary_range' => '₦250,000 - ₦400,000/month',
                'experience_level' => '3+ years',
                'description' => 'This is one of our first five hires and one of the most critical roles at Onwynd. You will identify, pitch, and close institutional partners — corporates, churches, schools, HMOs, and NGOs — and own the full relationship lifecycle from first contact through ongoing success. If you are a natural connector who thrives on building real relationships and closing meaningful deals, this role is for you.',
                'responsibilities' => [
                    'Identify and prospect institutional partners (corporations, religious organisations, universities, hospitals, HMOs)',
                    'Own the full sales cycle — outreach, pitch, negotiation, close, onboarding',
                    'Sustain long-term partner relationships through regular check-ins, QBRs, and value demonstrations',
                    'Collaborate with the founding team to shape partnership pricing, proposals, and contracts',
                    'Represent Onwynd at industry events, HR conferences, and wellness summits',
                    'Feed partner feedback directly into product and content roadmaps',
                    'Track pipeline, conversion metrics, and partner health in CRM',
                ],
                'qualifications' => [
                    '3+ years in B2B sales, partnerships, or business development (HR tech, wellness, health, SaaS preferred)',
                    'Demonstrable track record closing institutional or corporate deals in Nigeria',
                    'Exceptional relationship-building and negotiation skills',
                    'Self-starter comfortable working in an early-stage, fast-moving environment',
                    'Strong written and verbal communication; ability to present at C-suite level',
                    'Existing network in corporate HR, insurance, or healthcare is a strong advantage',
                ],
                'benefits' => [
                    'Competitive base salary + performance commission on closed deals',
                    'Equity participation (early employee)',
                    'Hybrid / flexible working arrangement',
                    'HMO health coverage',
                    'Direct access to founders and genuine influence over company direction',
                    'Onwynd platform subscription (for yourself and your immediate family)',
                ],
            ],

            [
                'title' => 'Sales & Growth Marketer',
                'department' => 'Growth',
                'location' => 'Lagos, Nigeria / Remote',
                'type' => 'Full-time',
                'salary_range' => '₦200,000 - ₦350,000/month',
                'experience_level' => '2+ years',
                'description' => 'One of our first five hires. You will own demand generation and user acquisition — driving awareness, leads, and sign-ups through digital channels (Instagram, LinkedIn, TikTok), paid campaigns, and direct outreach to organisations and communities. You are part marketer, part salesperson, with a bias for action and a hunger for growth.',
                'responsibilities' => [
                    'Run performance marketing campaigns across Meta, Google, TikTok, and LinkedIn',
                    'Generate and qualify leads from individuals, organisations, and institutions',
                    'Develop and execute go-to-market strategies for new features and product launches',
                    'Manage referral and ambassador programmes to grow organic acquisition',
                    'Partner with the Social Media / Design role to produce conversion-focused creative assets',
                    'Analyse funnel metrics (CAC, CTR, activation rate) and iterate rapidly',
                    'Build and maintain outreach to community leaders, HR professionals, and wellness influencers',
                ],
                'qualifications' => [
                    '2+ years in growth marketing, digital marketing, or sales (consumer app or SaaS preferred)',
                    'Hands-on experience running paid campaigns on Meta Ads and/or Google Ads',
                    'Strong analytical mindset — comfortable with data, A/B testing, and attribution',
                    'Understanding of the Nigerian digital landscape (WhatsApp communities, local influencers, etc.)',
                    'Excellent copywriting skills for ads, emails, and landing pages',
                    'Experience with CRM or marketing automation tools is a plus',
                ],
                'benefits' => [
                    'Competitive base salary + performance bonus tied to acquisition targets',
                    'Equity participation (early employee)',
                    'Flexible / remote-friendly working arrangement',
                    'HMO health coverage',
                    'Marketing budget and tools provided',
                    'Onwynd platform subscription',
                ],
            ],

            [
                'title' => 'Customer Support & Success Manager',
                'department' => 'Operations',
                'location' => 'Lagos, Nigeria / Remote',
                'type' => 'Full-time',
                'salary_range' => '₦150,000 - ₦250,000/month',
                'experience_level' => '2+ years',
                'description' => 'One of our first five hires. You will be the frontline voice of Onwynd — handling user issues, onboarding, retention, and satisfaction across chat, email, and social DMs. In our early stage this role is intentionally broad: you will also assist with social media community management and operational communications. As we grow, the role will split into dedicated support and success functions.',
                'responsibilities' => [
                    'Respond to user enquiries across email, in-app chat, WhatsApp, and social media DMs',
                    'Onboard new users and institutional partners — guide them to their first meaningful outcome',
                    'Proactively reach out to at-risk users (low engagement, cancelled subscriptions) with retention touchpoints',
                    'Triage, escalate, and track bugs and feedback to the product and engineering team',
                    'Manage community engagement on social channels (in collaboration with Social Media Manager)',
                    'Write and maintain help centre articles, FAQs, and onboarding documentation',
                    'Monitor CSAT, response time, and churn metrics; report weekly to founders',
                ],
                'qualifications' => [
                    '2+ years in customer support, customer success, or community management',
                    'Empathetic communicator with excellent written English',
                    'Comfortable managing high-volume conversations across multiple channels simultaneously',
                    'Basic familiarity with support tools (Intercom, Freshdesk, Crisp, or similar)',
                    'Genuine interest in mental health, wellness, or healthcare',
                    'Experience with a consumer app or subscription product is a strong advantage',
                ],
                'benefits' => [
                    'Competitive salary',
                    'Equity participation (early employee)',
                    'Flexible / remote-friendly working arrangement',
                    'HMO health coverage',
                    'Onwynd platform subscription (you are the first power user)',
                    'Clear growth path as support and success functions scale',
                ],
            ],

            [
                'title' => 'Social Media Manager & Graphic Designer',
                'department' => 'Marketing',
                'location' => 'Lagos, Nigeria / Remote',
                'type' => 'Full-time',
                'salary_range' => '₦150,000 - ₦250,000/month',
                'experience_level' => '2+ years',
                'description' => 'One of our first five hires. This is a combined creative role for a multi-talented individual who can plan and publish engaging social content AND produce the visual assets to support it — without needing a separate team. You will own Onwynd\'s brand presence on Instagram, TikTok, LinkedIn, X, and WhatsApp, building community and driving organic growth. As the company grows, this role will split into dedicated social media and design positions.',
                'responsibilities' => [
                    'Plan and manage the social media content calendar across Instagram, TikTok, LinkedIn, X, and WhatsApp',
                    'Create original, on-brand graphics, carousels, short-form videos, reels, and stories',
                    'Write compelling captions, hooks, and copy tailored to each platform\'s audience',
                    'Engage with followers, respond to comments, and grow community presence',
                    'Collaborate with the Growth Marketer to produce creative assets for paid campaigns',
                    'Design marketing collateral: pitch decks, one-pagers, email headers, event banners',
                    'Track follower growth, reach, engagement rate, and share weekly performance reports',
                ],
                'qualifications' => [
                    '2+ years managing social media accounts for a brand or agency',
                    'Strong graphic design skills — proficient in Canva, Figma, Adobe Creative Suite, or equivalent',
                    'Video editing capability for short-form content (CapCut, Adobe Premiere, DaVinci Resolve)',
                    'Excellent understanding of Instagram and TikTok algorithms and content trends',
                    'Good copywriting skills — can write in a warm, relatable, destigmatising tone about mental health',
                    'Portfolio of previous social media and design work (required)',
                ],
                'benefits' => [
                    'Competitive salary',
                    'Equity participation (early employee)',
                    'Flexible / remote-friendly working arrangement',
                    'HMO health coverage',
                    'Creative freedom to shape the Onwynd brand voice and visual identity',
                    'Onwynd platform subscription',
                ],
            ],

            [
                'title' => 'Legal Advisor',
                'department' => 'Legal & Compliance',
                'location' => 'Lagos, Nigeria',
                'type' => 'Contract / Part-time',
                'salary_range' => 'Negotiable (retainer)',
                'experience_level' => '5+ years',
                'description' => 'Onwynd operates at the intersection of mental health, AI, and financial services — an environment with meaningful legal and regulatory complexity. We are looking for a sharp, pragmatic Legal Advisor (initially on a retainer/contract basis) to guide the company on corporate governance, data protection (NDPR/GDPR), healthcare regulations, commercial contracts, employment, and IP. The role may convert to a full-time General Counsel as the company scales.',
                'responsibilities' => [
                    'Review, draft, and negotiate commercial contracts (partner agreements, SaaS agreements, NDAs, employment contracts)',
                    'Advise on Nigeria Data Protection Regulation (NDPR) compliance and privacy policy requirements',
                    'Provide guidance on CAC filings, corporate governance, and shareholder agreements',
                    'Advise on healthcare and telemedicine regulatory requirements in Nigeria',
                    'Support IP protection: trademarks, copyright, and technology licensing',
                    'Review terms of service, privacy policies, and consent frameworks',
                    'Advise on fundraising, investor agreements, and due diligence processes',
                ],
                'qualifications' => [
                    'LLB and BL; active membership of the Nigerian Bar Association',
                    '5+ years of post-call experience (technology, healthcare, or financial services focus preferred)',
                    'Strong knowledge of NDPR, FCCPA, and Nigerian corporate law',
                    'Experience advising startups or tech companies (Series A or earlier)',
                    'Practical, commercially minded approach — able to assess risk without blocking progress',
                    'Prior exposure to healthcare regulation, fintech regulation, or data privacy law is a strong advantage',
                ],
                'benefits' => [
                    'Competitive retainer fee',
                    'Opportunity to grow into a full-time General Counsel role',
                    'Equity participation available for the right candidate',
                    'Direct collaboration with founders on high-impact decisions',
                    'Onwynd platform subscription',
                ],
            ],
        ];

        foreach ($jobs as $data) {
            JobPosting::firstOrCreate(
                ['title' => $data['title']],
                array_merge($data, [
                    // supply a uuid explicitly; the model boot helper normally generates one,
                    // but firstOrCreate sometimes bypasses the creating event in edge cases
                    // (and earlier runs showed the column was left empty), so we guarantee it here.
                    'uuid' => (string) Str::uuid(),
                    'slug' => Str::slug($data['title']),
                    'is_active' => true,
                    'status' => 'open',
                    'posted_at' => now(),
                ])
            );
        }
    }
}
