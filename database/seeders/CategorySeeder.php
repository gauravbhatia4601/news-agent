<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * India-focused, WSJ-inspired category hierarchy.
     * Parent categories (8) → Subcategories (40 total).
     */
    public function run(): void
    {
        $tree = [
            'National' => [
                'description' => 'Politics, governance, and current affairs from across India.',
                'children' => [
                    'Politics & Governance' => 'Indian Parliament, political parties, elections, government policy.',
                    'Defence & Security' => 'Armed forces, border security, terrorism, cyber threats.',
                    'States & Regions' => 'State governments, regional politics, development across states.',
                    'Law & Judiciary' => 'Supreme Court, High Courts, legal reforms, landmark judgments.',
                    'Social Justice' => 'Caste, gender equality, minority rights, affirmative action.',
                    'Accidents & Disasters' => 'Road accidents, natural disasters, fires, industrial mishaps, animal attacks, casualties.',
                ],
            ],
            'World' => [
                'description' => 'International news, diplomacy, and global affairs with an Indian perspective.',
                'children' => [
                    'South Asia' => 'Pakistan, Bangladesh, Sri Lanka, Nepal, Maldives, Bhutan, Afghanistan.',
                    'Asia-Pacific' => 'China, Japan, Southeast Asia, Australia, regional tensions.',
                    'Americas' => 'United States, Canada, Latin America, trade relations with India.',
                    'Europe & UK' => 'European Union, United Kingdom, Russia, FTA negotiations.',
                    'Middle East & Africa' => 'Gulf nations, Israel-Palestine, energy diplomacy, African partnerships.',
                ],
            ],
            'Business & Economy' => [
                'description' => 'Markets, finance, industry, and economic policy shaping India\'s growth.',
                'children' => [
                    'Markets & Finance' => 'Stock markets, banking, NBFCs, insurance, personal finance.',
                    'Startups & Tech Biz' => 'Startup ecosystem, unicorns, funding, IPOs, venture capital.',
                    'Industry & Manufacturing' => 'Make in India, PLI schemes, automotive, steel, pharmaceuticals.',
                    'Economy & Policy' => 'GDP, inflation, RBI policy, budget, trade, GST, taxation.',
                    'Real Estate & Infrastructure' => 'Property markets, smart cities, highways, railways, airports.',
                ],
            ],
            'Technology' => [
                'description' => 'Innovation, digital transformation, and the tech industry in India.',
                'children' => [
                    'Artificial Intelligence' => 'AI research, LLMs, machine learning, AI policy in India.',
                    'IT & Services' => 'TCS, Infosys, Wipro, IT exports, outsourcing trends.',
                    'Startups & Innovation' => 'Bengaluru ecosystem, SaaS companies, deep tech, incubators.',
                    'Cybersecurity' => 'Data breaches, cybercrime, CERT-In, digital safety regulations.',
                    'Consumer Tech & Gadgets' => 'Smartphones, wearables, EVs, 5G rollout, telecom sector.',
                ],
            ],
            'Sports' => [
                'description' => 'Indian and international sports, tournaments, and athletes.',
                'children' => [
                    'Cricket' => 'Team India, IPL, BCCI, domestic cricket, ICC tournaments.',
                    'Football' => 'ISL, Indian national team, European leagues, FIFA World Cup.',
                    'Olympic Sports' => 'Olympics, Asian Games, athletics, wrestling, shooting, boxing.',
                    'Racquet Sports' => 'Badminton, tennis, table tennis — PV Sindhu, Sumit Nagal, and more.',
                    'Other Sports' => 'Hockey, kabaddi, chess, motorsports, golf, emerging leagues.',
                ],
            ],
            'Entertainment' => [
                'description' => 'Bollywood, regional cinema, OTT, music, and celebrity culture.',
                'children' => [
                    'Bollywood & Hindi Cinema' => 'Movie releases, box office, celebrity news, award shows.',
                    'Regional Cinema' => 'Tamil, Telugu, Malayalam, Kannada, Bengali, Marathi film industries.',
                    'OTT & Streaming' => 'Netflix India, Prime Video, Hotstar, JioCinema, web series.',
                    'Music & Arts' => 'Indian classical, indie music, festivals, theater, visual arts.',
                    'Gaming & Esports' => 'Mobile gaming, BGMI, Valorant, esports tournaments in India.',
                ],
            ],
            'Lifestyle' => [
                'description' => 'Health, travel, food, fashion, and modern Indian living.',
                'children' => [
                    'Health & Wellness' => 'Public health, AYUSH, fitness, mental health, medical research.',
                    'Food & Drink' => 'Indian cuisine, restaurants, food trends, culinary heritage.',
                    'Travel & Destinations' => 'Indian tourism, heritage sites, adventure travel, visa policies.',
                    'Style & Fashion' => 'Indian designers, ethnic wear, sustainable fashion, beauty industry.',
                    'Home & Design' => 'Interior design, architecture, smart homes, urban living.',
                ],
            ],
            'Science & Education' => [
                'description' => 'Research, space exploration, climate, and India\'s knowledge economy.',
                'children' => [
                    'Space & ISRO' => 'ISRO missions, Chandrayaan, Gaganyaan, satellite launches.',
                    'Climate & Environment' => 'Climate change policy, renewable energy, conservation, pollution.',
                    'Scientific Research' => 'Indian research institutions, breakthroughs, STEM education.',
                    'Higher Education' => 'IITs, IIMs, universities, study abroad, edtech trends.',
                    'Agriculture & Rural' => 'Farming, agritech, MSP, rural development, food security.',
                ],
            ],
        ];

        $order = 0;

        foreach ($tree as $parentName => $data) {
            $parent = Category::create([
                'name' => $parentName,
                'slug' => Str::slug($parentName),
                'parent_id' => null,
                'description' => $data['description'],
                'display_order' => $order++,
            ]);

            $childOrder = 0;
            foreach ($data['children'] as $childName => $childDesc) {
                Category::create([
                    'name' => $childName,
                    'slug' => Str::slug($childName),
                    'parent_id' => $parent->id,
                    'description' => $childDesc,
                    'display_order' => $childOrder++,
                ]);
            }
        }
    }
}
