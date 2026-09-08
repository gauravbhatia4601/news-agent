<?php

namespace App\News\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StateDetectionService
{
    private const STATE_PATTERNS = [
        'andhra-pradesh' => ['andhra pradesh', 'amaravati', 'visakhapatnam', 'vijayawada', 'chandrababu naidu', 'telugu desam', 'ysr congress', 'jagan mohan reddy', 'tirupati', 'kurnool', 'nellore', 'rajahmundry', 'guntur', 'anantapur', 'kadapa'],
        'bihar' => ['bihar', 'patna', 'nitish kumar', 'tejashwi yadav', 'lalu prasad', 'gaya', 'muzaffarpur', 'bhagalpur', 'darbhanga', 'purnia', 'bihari'],
        'delhi-ncr' => ['delhi', 'new delhi', 'ncr', 'gurugram', 'gurgaon', 'noida', 'greater noida', 'faridabad', 'ghaziabad', 'delhi government', 'mcd', 'arvind kejriwal', 'atishi', 'delhi metro', 'dilli'],
        'gujarat' => ['gujarat', 'ahmedabad', 'surat', 'vadodara', 'rajkot', 'gandhinagar', 'bhupendra patel', 'gift city', 'gujarati', 'jamnagar', 'bhavnagar', 'morbi', 'kutch', 'somnath', 'dwarka'],
        'karnataka' => ['karnataka', 'bengaluru', 'bangalore', 'mysuru', 'mysore', 'mangaluru', 'mangalore', 'hubli', 'dharwad', 'belagavi', 'siddaramaiah', 'dk shivakumar', 'kannada', 'kannadiga'],
        'kerala' => ['kerala', 'thiruvananthapuram', 'trivandrum', 'kochi', 'cochin', 'kozhikode', 'calicut', 'malayalam', 'pinarayi vijayan', 'keralite', 'malappuram', 'thrissur', 'kollam', 'alappuzha', 'alleppey'],
        'madhya-pradesh' => ['madhya pradesh', 'bhopal', 'indore', 'gwalior', 'jabalpur', 'ujjain', 'mohan yadav', 'shivraj singh chouhan', 'mp government', 'malwa', 'bundelkhand'],
        'maharashtra' => ['maharashtra', 'mumbai', 'bombay', 'pune', 'nagpur', 'nashik', 'aurangabad', 'chhatrapati sambhajinagar', 'thane', 'navi mumbai', 'devendra fadnavis', 'eknath shinde', 'uddhav thackeray', 'shiv sena', 'marathi', 'mumbaikar'],
        'punjab-haryana' => ['punjab', 'haryana', 'chandigarh', 'ludhiana', 'amritsar', 'jalandhar', 'patiala', 'ambala', 'hisar', 'rohtak', 'karnal', 'panipat', 'bhagwant mann', 'nayab singh saini', 'punjabi', 'haryanvi'],
        'rajasthan' => ['rajasthan', 'jaipur', 'udaipur', 'jodhpur', 'bikaner', 'ajmer', 'kota', 'bhajan lal sharma', 'rajasthani', 'marwari', 'mewar', 'marwar', 'pushkar', 'jaisalmer'],
        'tamil-nadu' => ['tamil nadu', 'chennai', 'madras', 'coimbatore', 'madurai', 'tiruchirappalli', 'trichy', 'salem', 'mk stalin', 'dmk', 'aiadmk', 'edappadi palaniswami', 'tamil', 'tamizh'],
        'telangana' => ['telangana', 'hyderabad', 'warangal', 'nizamabad', 'karimnagar', 'revanth reddy', 'ktr', 'k chandrashekar rao', 'telugu', 'secunderabad', 'cyberabad'],
        'uttar-pradesh' => ['uttar pradesh', 'lucknow', 'kanpur', 'varanasi', 'banaras', 'prayagraj', 'allahabad', 'agra', 'noida', 'meerut', 'bareilly', 'yogi adityanath', 'akhilesh yadav', 'mayawati', 'up government'],
        'west-bengal' => ['west bengal', 'kolkata', 'calcutta', 'darjeeling', 'siliguri', 'durgapur', 'asansol', 'mamata banerjee', 'trinamool', 'tmc', 'bengali', 'bengal'],
        'northeast-india' => ['assam', 'guwahati', 'manipur', 'imphal', 'meghalaya', 'shillong', 'nagaland', 'kohima', 'tripura', 'agartala', 'mizoram', 'aizawl', 'arunachal pradesh', 'itanagar', 'sikkim', 'gangtok', 'northeast india', 'north east india', 'himanta biswa sarma'],
        'odisha-jharkhand' => ['odisha', 'orissa', 'jharkhand', 'bhubaneswar', 'cuttack', 'rancchi', 'jamshedpur', 'dhanbad', 'naveen patnaik', 'mohan majhi', 'hemant soren', 'puri', 'rourkela', 'sambalpur'],
        'chhattisgarh' => ['chhattisgarh', 'raipur', 'bhilai', 'bilaspur', 'durg', 'korba', 'bastar', 'naxal', 'vishnu deo sai', 'bhupesh baghel'],
        'himalayan-states' => ['uttarakhand', 'dehradun', 'rishikesh', 'haridwar', 'nainital', 'himachal pradesh', 'shimla', 'manali', 'dharamshala', 'pushkar singh dhami', 'sukhvinder singh sukhu', 'mussoorie'],
        'goa-coastal' => ['goa', 'panaji', 'panjim', 'puducherry', 'pondicherry', 'andaman', 'nicobar', 'lakshadweep', 'daman', 'diu', 'dadra', 'nagar haveli', 'port blair', 'kavaratti'],
        'central-uts' => ['jammu', 'kashmir', 'srinagar', 'ladakh', 'leh', 'kargil', 'jammu and kashmir', 'omar abdullah', 'article 370', 'kashmiri'],
    ];

    /**
     * Detect the best-matching state category for a topic.
     *
     * @param  array<int, array{headline: string, summary: string}>  $sources
     * @return array{id: int|null, name: string|null, slug: string|null}
     */
    public function detect(string $topicName, array $sources): array
    {
        $text = Str::lower($topicName);

        foreach ($sources as $source) {
            $text .= ' '.Str::lower($source['headline'] ?? '');
            $text .= ' '.Str::lower($source['summary'] ?? '');
        }

        $scores = [];

        foreach (self::STATE_PATTERNS as $slug => $patterns) {
            $score = 0;
            foreach ($patterns as $pattern) {
                $count = substr_count($text, $pattern);
                if ($count > 0) {
                    $score += $count * (strlen($pattern) > 8 ? 2 : 1);
                }
            }
            if ($score > 0) {
                $scores[$slug] = $score;
            }
        }

        if ($scores === []) {
            return ['id' => null, 'name' => null, 'slug' => null];
        }

        arsort($scores);
        $bestSlug = array_key_first($scores);

        $category = DB::table('categories')
            ->where('slug', $bestSlug)
            ->where('parent_id', DB::table('categories')->where('slug', 'india')->value('id'))
            ->first(['id', 'name', 'slug']);

        if (! $category) {
            return ['id' => null, 'name' => null, 'slug' => null];
        }

        return [
            'id' => (int) $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
        ];
    }
}
