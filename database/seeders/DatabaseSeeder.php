<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            AdminUserSeeder::class,
            ExecutiveUserSeeder::class,
            UserSeeder::class,
            AssessmentSeeder::class,
            SubscriptionPlanSeeder::class,
            EditorialCategorySeeder::class,
            EditorialPostSeeder::class,
            BadgeSeeder::class,
            WeeklyChallengeSeeder::class,
            KnowledgeBaseSeeder::class,
            TestimonialSeeder::class,
            PhysicalCenterSeeder::class,
            AmbassadorSettingsSeeder::class,
            ChatCategorySeeder::class,
            LandingPageContentSeeder::class,
            AudioLibrarySeeder::class,
            JobPostingSeeder::class,
            TherapistSeeder::class,
            GroupTherapistSeeder::class,
            LocationSeeder::class,
            NigerianInstitutionSeeder::class,
            CorporateDemoSeeder::class,
            VapidSettingsSeeder::class,
        ]);
    }
}
