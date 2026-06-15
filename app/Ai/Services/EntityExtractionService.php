<?php

namespace App\Ai\Services;

/**
 * Extracts named entities and search-intent signals from news source data
 * for injection into article generation prompts.
 */
class EntityExtractionService
{
    /**
     * @param  array<int, array{headline: string, summary: string}>  $sources
     */
    public function extract(array $sources): EntityExtractionResult
    {
        $allText = '';
        $allHeadlines = '';
        foreach ($sources as $source) {
            $allHeadlines .= ($source['headline'] ?? '') . ' ';
            $allText .= ($source['headline'] ?? '') . ' ' . ($source['summary'] ?? '') . ' ';
        }

        $allText = mb_strtolower(trim($allText));
        $allHeadlines = mb_strtolower(trim($allHeadlines));

        return new EntityExtractionResult(
            people: $this->extractPeople($allText),
            organizations: $this->extractOrganizations($allText),
            locations: $this->extractLocations($allText),
            dates: $this->extractDates($allText),
            numbers: $this->extractNumbers($allText),
            primaryTopics: $this->extractPrimaryTopics($allHeadlines),
            searchQuestions: $this->generateSearchQuestions($allHeadlines),
            topicTerm: $this->findDominantTopicTerm($allHeadlines),
        );
    }

    private function extractPeople(string $text): array
    {
        $people = [];

        $patterns = [
            '/\b(Narendra\s*Modi|Amit\s*Shah|Rahul\s*Gandhi|Mamata\s*Banerjee|Arvind\s*Kejriwal|Nirmala\s*Sitharaman|Yogi\s*Adityanath|Pinarayi\s*Vijayan|M\.\s*K\.\s*Stalin|Uddhav\s*Thackeray|Sharad\s*Pawar|Nitish\s*Kumar|Chandrababu\s*Naidu|K\.\s*Chandrashekar\s*Rao|Bhagwant\s*Mann|Himanta\s*Biswa\s*Sarma|Neeraj\s*Chopra|Virat\s*Kohli|Rohit\s*Sharma|PV\s*Sindhu|Saina\s*Nehwal|Sunil\s*Chhetri|Anand\s*Mahindra|Mukesh\s*Ambani|Gautam\s*Adani|Ratan\s*Tata|Sundar\s*Pichai|Satya\s*Nadella)\b/i',
            '/\b(Mr\.\s+[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*|Ms\.\s+[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*|Dr\.\s+[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*|Lt\.?\s*Gen\.?\s+[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*|Cmdr\.\s+[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*|Maj\.?\s+[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*|Col\.\s+[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*|Prof\.\s+[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\b/',
            '/\b([A-Z][a-z]+(?:\s+[A-Z][a-z]+){1,3})\b/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $match) {
                    $normalized = trim($match);
                    if (mb_strlen($normalized) > 2 && ! in_array(mb_strtolower($normalized), ['the', 'and', 'for', 'from', 'with', 'said', 'has', 'was', 'are', 'not', 'but', 'that', 'this', 'have', 'been', 'will', 'would', 'could', 'should', 'about', 'after', 'also', 'into', 'like', 'just', 'over', 'than', 'then', 'when', 'were', 'made', 'make', 'more', 'some', 'such', 'they', 'very', 'what', 'year', 'india', 'today'])) {
                        $people[] = $normalized;
                    }
                }
            }
        }

        return array_slice(array_values(array_unique($people)), 0, 10);
    }

    private function extractOrganizations(string $text): array
    {
        $orgs = [];
        $patterns = [
            '/\b(ISRO|DRDO|BCCI|RBI|SEBI|NITI\s*Aayog|Supreme\s*Court|High\s*Court|Lok\s*Sabha|Rajya\s*Sabha|Election\s*Commission|CBI|ED|NIA|IB|RAW|CERT-In|IndiaAI|DPIIT|MeitY|MNRE|CSIR|ICMR|WHO|UN|IMF|World\s*Bank|NASA|SpaceX|Google|Microsoft|Apple|Meta|Amazon|Netflix|TCS|Infosys|Wipro|HCL|IIT|IIM|AIIMS|JNU|DU|BHU)\b/i',
            '/\b([A-Z][a-z]*(?:\s+(?:and|&)\s+[A-Z][a-z]*)?(?:\s+(?:Bank|Corp|Corporation|Limited|Ltd|Inc|Group|Company|Technologies|Systems|Solutions|Services|Institute|University|College|Academy|Foundation|Trust|Authority|Board|Council|Commission|Ministry|Department|Bureau|Agency|Party|Congress|Alliance|Front|Federation|Association|Union|Federation))\b)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $match) {
                    $normalized = trim($match);
                    if (mb_strlen($normalized) > 2) {
                        $orgs[] = $normalized;
                    }
                }
            }
        }

        return array_slice(array_values(array_unique($orgs)), 0, 10);
    }

    private function extractLocations(string $text): array
    {
        $locations = [];

        $patterns = [
            '/\b(India|New\s*Delhi|Mumbai|Bengaluru|Bangalore|Chennai|Hyderabad|Kolkata|Ahmedabad|Pune|Jaipur|Lucknow|Chandigarh|Guwahati|Bhubaneswar|Patna|Ranchi|Raipur|Bhopal|Thiruvananthapuram|Kochi|Goa|Shimla|Dehradun|Srinagar|Jammu|Imphal|Agartala|Aizawl|Kohima|Shillong|Itanagar|Gangtok|Port\s*Blair|Daman|Diu|Silvassa|Kavaratti|Leh|Ladakh|J\s*&\s*K)\b/i',
            '/\b(Uttar\s*Pradesh|Maharashtra|Bihar|West\s*Bengal|Madhya\s*Pradesh|Tamil\s*Nadu|Rajasthan|Karnataka|Gujarat|Andhra\s*Pradesh|Odisha|Telangana|Kerala|Jharkhand|Assam|Punjab|Chhattisgarh|Haryana|Delhi|Jammu\s*and\s*Kashmir|Uttarakhand|Himachal\s*Pradesh|Tripura|Meghalaya|Manipur|Nagaland|Goa|Arunachal\s*Pradesh|Mizoram|Sikkim)\b/i',
            '/\b(USA|United\s*States|UK|United\s*Kingdom|China|Japan|Russia|France|Germany|Canada|Australia|Brazil|UAE|Saudi\s*Arabia|Israel|Pakistan|Bangladesh|Sri\s*Lanka|Nepal|Bhutan|Myanmar|Maldives|Afghanistan|Singapore|Malaysia|Indonesia|South\s*Korea|North\s*Korea)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $match) {
                    $locations[] = trim($match);
                }
            }
        }

        return array_slice(array_values(array_unique($locations)), 0, 8);
    }

    private function extractDates(string $text): array
    {
        $dates = [];
        $patterns = [
            '/\b(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},?\s+\d{4}\b/i',
            '/\b\d{1,2}\s+(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{4}\b/i',
            '/\b\d{4}\b/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[0] as $match) {
                    $dates[] = trim($match);
                }
            }
        }

        return array_slice(array_values(array_unique($dates)), 0, 5);
    }

    private function extractNumbers(string $text): array
    {
        $numbers = [];
        if (preg_match_all('/\b(?:Rs\.?\s*|₹|USD\s*|%\s*)?(\d+(?:,\d+)*(?:\.\d+)?)\s*(?:crore|lakh|lac|million|billion|trillion|thousand|percent|%|km|kg|years?|days?|hours?|people|students?|workers?|jobs?|units?|MW|GW|acres?|hectares?|sq\s*ft|sq\s*km)?\b/i', $text, $matches)) {
            foreach ($matches[0] as $match) {
                $normalized = trim($match);
                if (preg_match('/\d/', $normalized)) {
                    $numbers[] = $normalized;
                }
            }
        }

        return array_slice(array_values(array_unique($numbers)), 0, 8);
    }

    private function extractPrimaryTopics(string $headlinesText): array
    {
        $words = str_word_count($headlinesText, 1);
        $wordFreq = array_count_values($words);

        $stopWords = ['the', 'and', 'for', 'from', 'with', 'said', 'has', 'was', 'are', 'not', 'but', 'that', 'this', 'have', 'been', 'will', 'would', 'could', 'should', 'about', 'after', 'also', 'into', 'like', 'just', 'over', 'than', 'then', 'when', 'were', 'made', 'make', 'more', 'some', 'such', 'they', 'very', 'what', 'year', 'india', 'today', 'new', 'news', 'latest', 'live', 'update', 'updates', 'first', 'two', 'one', 'get', 'set', 'big', 'top', 'now', 'may', 'may', 'can', 'its', 'his', 'her', 'their', 'our', 'your', 'out', 'how', 'who', 'why', 'all', 'back', 'day'];

        foreach ($stopWords as $sw) {
            unset($wordFreq[$sw]);
        }

        arsort($wordFreq);

        return array_slice(array_keys($wordFreq), 0, 15);
    }

    private function generateSearchQuestions(string $headlinesText): array
    {
        $questions = [];

        $questionTemplates = [
            'what is' => "What is %s?",
            'how to' => "How to %s?",
            'why did' => "Why did %s?",
            'when will' => "When will %s?",
            'who is' => "Who is %s?",
            'where is' => "Where is %s?",
            'how does' => "How does %s work?",
            'what are' => "What are the implications of %s?",
        ];

        $topics = $this->extractPrimaryTopics($headlinesText);
        $topicStr = implode(' ', array_slice($topics, 0, 5));

        if ($topicStr !== '') {
            $questions[] = sprintf("What is happening with %s?", $topicStr);
            $questions[] = sprintf("How will %s affect India?", $topicStr);
            $questions[] = sprintf("What does %s mean for Indians?", $topicStr);
            $questions[] = "What are the latest developments on " . implode(' ', array_slice($topics, 0, 3)) . "?";
        }

        return array_slice($questions, 0, 5);
    }

    private function findDominantTopicTerm(string $headlinesText): string
    {
        $topics = $this->extractPrimaryTopics($headlinesText);
        if ($topics === []) {
            return '';
        }

        $bigrams = [];
        $words = array_slice(array_filter(explode(' ', $headlinesText), fn ($w) => strlen($w) > 2), 0, 20);
        for ($i = 0; $i < count($words) - 1; $i++) {
            $bigrams[] = $words[$i] . ' ' . $words[$i + 1];
        }

        $bigramFreq = array_count_values($bigrams);
        arsort($bigramFreq);

        foreach ($bigramFreq as $bg => $freq) {
            if ($freq >= 2) {
                return $bg;
            }
        }

        return $topics[0] ?? '';
    }
}
