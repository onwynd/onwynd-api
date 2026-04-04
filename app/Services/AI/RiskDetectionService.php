<?php

namespace App\Services\AI;

class RiskDetectionService
{
    protected $highRiskKeywords = [
        'suicide', 'kill myself', 'end my life', 'better off dead', 'want to die',
        'hurt myself', 'cutting', 'overdose', 'hang myself', 'no reason to live',
    ];

    protected $abuseKeywords = [
        'hit me', 'beat me', 'hurt me', 'scared of him', 'scared of her', 'sexual abuse', 'rape',
        'punched me', 'kicked me', 'forced me',
    ];

    protected $moderateRiskKeywords = [
        'depressed', 'sad', 'anxious', 'panic', 'hopeless', 'worthless', 'lonely', 'tired of everything',
        // Life stressors and challenges
        'chores overwhelming', 'housework too much', 'cant keep up', 'bills piling up', 'debt collectors',
        'rent overdue', 'eviction notice', 'cant pay rent', 'family expectations', 'parental pressure',
        'childhood trauma', 'rejection hurts', 'divorce pain', 'breakup depression', 'poor grades',
        'carry over courses', 'academic failure', 'no results', 'feeling backward', 'cooking stress',
        'laundry piling', 'productivity low', 'office politics', 'work stress', 'conspiracy theories',
        'weight gain', 'cant lose weight', 'migraine pain', 'chronic headache', 'sti diagnosis',
        'sexual health', 'medical bills', 'health anxiety', 'overwhelmed', 'burnout', 'exhausted',
    ];

    protected $highRiskKeywordsPidgin = [
        'i wan die', 'i go kill myself', 'no wan live again', 'i no fit again',
        'make i end am', 'wan end my life', 'no reason to live again',
    ];

    protected $abuseKeywordsPidgin = [
        'dem dey beat me', 'him dey beat me', 'she dey beat me', 'dem force me',
        'he rape me', 'she rape me', 'dem hurt me', 'i dey fear am',
    ];

    protected $moderateRiskKeywordsPidgin = [
        'i dey sad', 'i dey depressed', 'i dey panic', 'i no happy', 'i dey lonely',
        // Life stressors in Pidgin
        'house work too much', 'i no fit cope', 'bills too much', 'dey owe people', 'rent don expire',
        'family dey pressure me', 'papa mama want make i', 'pikinhood wahala', 'dem reject me', 'divorce pain dey',
        'school wahala', 'i fail exam', 'no result show', 'i dey backward', 'cooking wahala',
        'cloth don full basket', 'work too much', 'office wahala', 'politics for work', 'conspiracy theory',
        'i dey fat', 'i no fit lose weight', 'migraine dey worry me', 'headache no gree stop', 'sti wahala',
        'health wahala', 'hospital bill', 'health anxiety', 'i taya', 'burnout', 'exhausted',
    ];

    protected $highRiskKeywordsYoruba = [
        'mo fe pa ara mi', 'emi o fe ye mo', 'mo fe ku', 'aye mi ko dun mo',
    ];

    protected $abuseKeywordsYoruba = [
        'won n a mi', 'o n a mi', 'won n se mi ni ipalara', 'ipa ba mi',
        'o fi agbara gba mi', 'ibaje ibalopo', 'rape',
    ];

    protected $moderateRiskKeywordsYoruba = [
        'inu mi baje', 'inu mi ko dun', 'ife mi baje', 'ori mi n yiya', 'arinrinrin',
        // Life stressors in Yoruba
        'ise ile ju', 'mi le ba ara mi', 'bills pọ ju', 'mi n owe', 'rent ti pari',
        'idile n fa mi lara', 'baba iya n fe ki n', 'ijiya igba omo', 'won kọ mi', 'ipanu igbeyawo',
        'iwe ile-iwe wahala', 'mi fọ idanwo', 'ko si abajade', 'mi n pada sẹyin', 'sisun wahala',
        'asọ ti kun kete', 'ise ju', 'ise ofisi wahala', 'politics ni ofisi', 'conspiracy theory',
        'mi n de bi', 'mi le dinku iwaju', 'migraine n dun mi', 'ori n dun mi', 'sti wahala',
        'ila ilera', 'owó ilera', 'iberu ilera', 'mi n reti', 'burnout', 'exhausted',
    ];

    protected $highRiskKeywordsHausa = [
        'zan kashe kaina', 'ban son rayuwa', 'ina so in mutu', 'rayuwa ta kare',
    ];

    protected $abuseKeywordsHausa = [
        'yana buguna', 'tana buguna', 'ana min duka', 'ana cin zarafina',
        'an tilasta mini', 'fyade',
    ];

    protected $moderateRiskKeywordsHausa = [
        'ina bakin ciki', 'ina damuwa', 'ina tsoro', 'na gaji da komai',
        // Life stressors in Hausa
        'aiki gida ya yi yawa', 'ba na iya zaman kaina', 'bills sun yi yawa', 'ina bashi', 'haya ta ƙare',
        'iyali yana min matsin lamba', 'uban uwa suna so in', 'zamani na ƙasa', 'an ƙi ni', 'bazara ciwo',
        'makarantar wahala', 'na fashi a jarabawa', 'babu sakamako', 'na komo baya', 'dafa wahala',
        'tufafi sun cika kwali', 'aiki ya yi yawa', 'aiki ofis wahala', 'siyasa a ofis', 'conspiracy theory',
        'ina girma', 'ba na iya rage min', 'migraine yana min ciwo', 'kai yana min ciwo', 'sti wahala',
        'lafiyar jiki', 'kuɗin asibiti', 'tashin lafiya', 'na gaji', 'burnout', 'exhausted',
    ];

    protected $highRiskKeywordsIgbo = [
        'aga m egbu onwe m', 'achọghị m ibi kwa', 'achọrọ m ịkụ mmadụ', 'ụwa agwụla m',
    ];

    protected $abuseKeywordsIgbo = [
        'na akụ m', 'na-eti m', 'emechara m ike', 'eme m ihe ike', 'ime ihe ike n\'akpanwa',
    ];

    protected $moderateRiskKeywordsIgbo = [
        'obi adịghị m mma', 'adịghị m ụtọ', 'anwụọla m n\'obi', 'anaghị m ewere ya nke ọma',
        // Life stressors in Igbo
        'ọrụ ụlọ abụba', 'abụghị m enye aka', 'bills pụrụ iche', 'na m bụrụ onye ọrụ', 'ọrụ ụlọ gasịrị',
        'ezinụlọ na-achọ ka m', 'nna nne na-achọ ka m', 'ọrụ ụmụaka', 'ha jụrụ m', 'ịkwa ọkọ ọlụ',
        'ọrụ akwụkwọ wahala', 'm mebiri nyocha', 'enweghị isi', 'na m laghachi azụ', 'isi nni wahala',
        'uwe ejidela akpụkpọ', 'ọrụ ejidela', 'ọrụ ofisi wahala', 'politics na ofisi', 'conspiracy theory',
        'na m abụkarị nnukwu', 'abụghị m enye elu', 'migraine na-eti m', 'isi na-eti m', 'sti wahala',
        'ahụike ahụ', 'ego ụlọ ọgwụ', 'ahụike iwe', 'm gbara', 'burnout', 'exhausted',
    ];

    public function analyze(string $text): array
    {
        $text = mb_strtolower($text);
        $riskLevel = 'low';
        $detectedRisks = [];
        $score = 0;

        if ($this->containsHighRiskKeywords($text)) {
            $riskLevel = 'severe';
            $detectedRisks[] = 'self_harm_suicide';
            $score += 50;
        }

        if ($this->containsAbuseKeywords($text)) {
            if ($riskLevel !== 'severe') {
                $riskLevel = 'high';
            }
            $detectedRisks[] = 'abuse';
            $score += 30;
        }

        if ($score < 30) {
            if ($this->containsModerateRiskKeywords($text)) {
                if ($riskLevel === 'low') {
                    $riskLevel = 'moderate';
                }
                $detectedRisks[] = 'distress';
                $score += 10;
            }
        }

        return [
            'risk_level' => $riskLevel,
            'detected_risks' => array_unique($detectedRisks),
            'score' => $score,
            'requires_escalation' => $score >= 30,
        ];
    }

    public function containsHighRiskKeywords(string $text, ?string $lang = null): bool
    {
        $text = mb_strtolower($text);
        $keywords = $this->getKeywordsForLang('high', $lang);
        foreach ($keywords as $kw) {
            if (str_contains($text, $kw)) {
                return true;
            }
        }

        return false;
    }

    public function containsAbuseKeywords(string $text, ?string $lang = null): bool
    {
        $text = mb_strtolower($text);
        $keywords = $this->getKeywordsForLang('abuse', $lang);
        foreach ($keywords as $kw) {
            if (str_contains($text, $kw)) {
                return true;
            }
        }

        return false;
    }

    public function containsModerateRiskKeywords(string $text, ?string $lang = null): bool
    {
        $text = mb_strtolower($text);
        $keywords = $this->getKeywordsForLang('moderate', $lang);
        foreach ($keywords as $kw) {
            if (str_contains($text, $kw)) {
                return true;
            }
        }

        return false;
    }

    protected function getKeywordsForLang(string $type, ?string $lang = null): array
    {
        $all = [];

        // If specific language requested, prioritize it
        if ($lang) {
            $suffix = match ($lang) {
                'pcm' => 'Pidgin',
                'yo' => 'Yoruba',
                'ha' => 'Hausa',
                'ig' => 'Igbo',
                default => ''
            };
            $prop = "{$type}RiskKeywords{$suffix}";
            if ($type === 'abuse') {
                $prop = "abuseKeywords{$suffix}";
            }

            if (property_exists($this, $prop)) {
                return $this->$prop;
            }
        }

        // Fallback or global check: combine all languages
        $langs = ['', 'Pidgin', 'Yoruba', 'Hausa', 'Igbo'];
        foreach ($langs as $l) {
            $prop = "{$type}RiskKeywords{$l}";
            if ($type === 'abuse') {
                $prop = "abuseKeywords{$l}";
            }
            if (property_exists($this, $prop)) {
                $all = array_merge($all, $this->$prop);
            }
        }

        return array_unique($all);
    }
}
