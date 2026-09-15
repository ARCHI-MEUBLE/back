<?php

declare(strict_types=1);

namespace App\Domain\Calendly;

final class CalendlyQuestionExtractor
{
    private const PHONE_KEYWORDS = ['téléphone', 'phone', 'numero', 'numéro', 'portable', 'mobile', 'contact', 'tel'];

    public static function extract(array $questionsAndAnswers): array
    {
        $configUrl = '';
        $notes = '';
        foreach ($questionsAndAnswers as $qa) {
            $question = strtolower($qa['question'] ?? '');
            $answer = (string) ($qa['answer'] ?? '');
            if (str_contains($question, 'configuration') || str_contains($question, 'lien')) {
                $configUrl = $answer;
            }
            if (str_contains($question, 'note') || str_contains($question, 'information')) {
                $notes = $answer;
            }
        }
        return [$configUrl, $notes];
    }

    public static function extractPhone(array $questionsAndAnswers, string $fallback = ''): string
    {
        $phone = $fallback;
        foreach ($questionsAndAnswers as $qa) {
            if ($phone !== '') {
                break;
            }
            $question = strtolower($qa['question'] ?? '');
            foreach (self::PHONE_KEYWORDS as $keyword) {
                if (str_contains($question, $keyword)) {
                    $phone = (string) ($qa['answer'] ?? '');
                    break;
                }
            }
        }
        return $phone;
    }
}
