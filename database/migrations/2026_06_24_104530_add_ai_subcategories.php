<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $ai = Category::where('slug', 'artificial-intelligence')->first();
        if (! $ai) {
            return;
        }

        $subs = [
            ['name' => 'AI Global', 'slug' => 'ai-global', 'description' => 'Global AI news, research breakthroughs, and industry trends'],
            ['name' => 'AI United States', 'slug' => 'ai-us', 'description' => 'US AI companies, regulation, and policy'],
            ['name' => 'AI China', 'slug' => 'ai-china', 'description' => 'Chinese AI models, companies, and regulation'],
            ['name' => 'AI Europe', 'slug' => 'ai-europe', 'description' => 'EU AI Act, European AI startups, and regulation'],
            ['name' => 'AI Japan', 'slug' => 'ai-japan', 'description' => 'Japanese AI research, robotics, and policy'],
        ];

        $order = Category::where('parent_id', $ai->id)->max('display_order') ?? 0;

        foreach ($subs as $i => $sub) {
            Category::firstOrCreate(
                ['slug' => $sub['slug']],
                [
                    'name' => $sub['name'],
                    'parent_id' => $ai->id,
                    'description' => $sub['description'],
                    'display_order' => $order + $i + 1,
                ]
            );
        }
    }

    public function down(): void
    {
        Category::whereIn('slug', ['ai-global', 'ai-us', 'ai-china', 'ai-europe', 'ai-japan'])->delete();
    }
};
