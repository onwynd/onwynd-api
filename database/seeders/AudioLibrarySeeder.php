<?php

namespace Database\Seeders;

use App\Models\MindfulResource;
use App\Models\ResourceCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AudioLibrarySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting audio library seeding...');

        $basePath = storage_path('app/public/sounds');
        $categories = [
            'affirmation' => 'Affirmations',
            'meditation' => 'Guided Meditation',
            'mindfulness' => 'Mindfulness Practices',
            'sleep' => 'Sleep Sounds',
        ];

        $createdCount = 0;

        foreach ($categories as $folder => $categoryName) {
            $this->command->info("Processing category: {$categoryName}");

            // Get or create category
            $category = ResourceCategory::firstOrCreate(
                ['name' => $categoryName],
                ['slug' => Str::slug($categoryName), 'description' => "{$categoryName} audio resources for mental wellness"]
            );

            $categoryPath = $basePath.'/'.$folder;

            if (! File::exists($categoryPath)) {
                $this->command->warn("Directory not found: {$categoryPath}");

                continue;
            }

            // Scan directory recursively for audio files
            $audioFiles = File::allFiles($categoryPath);

            foreach ($audioFiles as $file) {
                if (! $this->isAudioFile($file->getExtension())) {
                    continue;
                }

                $relativePath = 'sounds/'.$folder.'/'.$file->getRelativePathname();
                $title = $this->generateTitle($file->getFilenameWithoutExtension());
                $duration = $this->estimateDuration($file->getPathname());

                // Check if already exists
                $existing = MindfulResource::where('media_url', $relativePath)->first();
                if ($existing) {
                    $this->command->info("Skipping existing: {$title}");

                    continue;
                }

                // Create mindful resource
                $resource = MindfulResource::create([
                    'resource_category_id' => $category->id,
                    'title' => $title,
                    'slug' => Str::slug($title.'-'.uniqid()),
                    'type' => 'audio',
                    'media_url' => $relativePath,
                    'thumbnail_url' => $this->getCategoryThumbnail($folder),
                    'duration_seconds' => $duration,
                    'is_premium' => $this->determineIfPremium($folder, $file->getFilename()),
                    'status' => 'published',
                    'views_count' => 0,
                    'submitted_by' => null,
                ]);

                $createdCount++;
                $this->command->info("Created: {$title} ({$duration}s)");
            }

            // Process subfolders for mindfulness category
            if ($folder === 'mindfulness') {
                $subfolders = File::directories($categoryPath);
                foreach ($subfolders as $subfolder) {
                    $subfolderName = basename($subfolder);
                    $this->command->info("Processing mindfulness subfolder: {$subfolderName}");

                    $subfolderFiles = File::allFiles($subfolder);
                    foreach ($subfolderFiles as $file) {
                        if (! $this->isAudioFile($file->getExtension())) {
                            continue;
                        }

                        $relativePath = 'sounds/'.$folder.'/'.$subfolderName.'/'.$file->getRelativePathname();
                        $title = $this->generateTitle($file->getFilenameWithoutExtension());
                        $duration = $this->estimateDuration($file->getPathname());

                        // Check if already exists
                        $existing = MindfulResource::where('media_url', $relativePath)->first();
                        if ($existing) {
                            $this->command->info("Skipping existing: {$title}");

                            continue;
                        }

                        $resource = MindfulResource::create([
                            'resource_category_id' => $category->id,
                            'title' => $title,
                            'slug' => Str::slug($title.'-'.uniqid()),
                            'type' => 'audio',
                            'media_url' => $relativePath,
                            'thumbnail_url' => $this->getCategoryThumbnail($folder),
                            'duration_seconds' => $duration,
                            'is_premium' => $this->determineIfPremium($folder, $file->getFilename()),
                            'status' => 'published',
                            'views_count' => 0,
                            'submitted_by' => null,
                        ]);

                        $createdCount++;
                        $this->command->info("Created: {$title} ({$duration}s)");
                    }
                }
            }
        }

        $this->command->info("Audio library seeding completed! Created {$createdCount} new resources.");
    }

    /**
     * Check if file extension is an audio format
     */
    private function isAudioFile(string $extension): bool
    {
        return in_array(strtolower($extension), ['mp3', 'm4a', 'wav', 'ogg', 'flac']);
    }

    /**
     * Generate a human-readable title from filename
     */
    private function generateTitle(string $filename): string
    {
        // Remove file extension and common prefixes
        $title = preg_replace('/^\d+[_\s-]/', '', $filename); // Remove leading numbers
        $title = str_replace(['_', '-'], ' ', $title); // Replace underscores and hyphens with spaces
        $title = preg_replace('/\s+/', ' ', $title); // Normalize whitespace
        $title = ucwords(strtolower($title)); // Capitalize words

        return trim($title);
    }

    /**
     * Estimate audio duration based on file size (rough approximation)
     */
    private function estimateDuration(string $filepath): int
    {
        $fileSize = filesize($filepath);
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);

        // Rough bitrate estimates (bytes per second)
        $bitrates = [
            'mp3' => 16000,   // 128kbps
            'm4a' => 16000,   // 128kbps
            'wav' => 176400,  // 1411kbps (16-bit, 44.1kHz)
            'ogg' => 16000,   // 128kbps
            'flac' => 80000,  // 640kbps (compressed)
        ];

        $bitrate = $bitrates[strtolower($extension)] ?? 16000;
        $duration = intval($fileSize / $bitrate);

        // Sanity checks
        if ($duration < 60) {
            $duration = 300;
        }    // Minimum 5 minutes
        if ($duration > 3600) {
            $duration = 1800;
        } // Maximum 30 minutes

        return $duration;
    }

    /**
     * Determine if audio should be premium based on category and filename
     */
    private function determineIfPremium(string $category, string $filename): bool
    {
        // Free content markers
        $freeMarkers = ['FreeMindfulness', 'free', 'demo', 'sample'];

        foreach ($freeMarkers as $marker) {
            if (stripos($filename, $marker) !== false) {
                return false;
            }
        }

        // Sleep sounds are premium
        if ($category === 'sleep') {
            return true;
        }

        // Longer mindfulness sessions are premium (determined by filename analysis)
        if ($category === 'mindfulness' && stripos($filename, 'minute') !== false) {
            if (preg_match('/(\d+)\s*minute/i', $filename, $matches)) {
                $minutes = intval($matches[1]);

                return $minutes > 20; // Sessions longer than 20 minutes are premium
            }
        }

        // Default: most content is premium for the freemium model
        return true;
    }

    /**
     * Get appropriate thumbnail for category
     */
    private function getCategoryThumbnail(string $category): string
    {
        $thumbnails = [
            'affirmation' => '/images/affirmations-thumb.jpg',
            'meditation' => '/images/meditation-thumb.jpg',
            'mindfulness' => '/images/mindfulness-thumb.jpg',
            'sleep' => '/images/sleep-thumb.jpg',
        ];

        return $thumbnails[$category] ?? '/images/default-audio-thumb.jpg';
    }
}
