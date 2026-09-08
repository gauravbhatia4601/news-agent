<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $national = Category::where('slug', 'national')->whereNull('parent_id')->first();

        if (! $national) {
            return;
        }

        Category::firstOrCreate(
            ['slug' => 'accidents-disasters'],
            [
                'name' => 'Accidents & Disasters',
                'parent_id' => $national->id,
                'description' => 'Road accidents, natural disasters, fires, industrial mishaps, animal attacks, casualties.',
                'display_order' => 100,
            ]
        );
    }

    public function down(): void
    {
        Category::where('slug', 'accidents-disasters')->delete();
    }
};
