<?php

namespace Tests\Unit;

use App\Services\StudentSurveyResultService;
use Tests\TestCase;

class StudentSurveyResultServiceTest extends TestCase
{
    public function test_adaptation_reverses_the_documented_questions(): void
    {
        $reverse = [2, 5, 7, 8, 11, 12, 15, 16];
        $questions = collect(range(1, 16))
            ->map(fn (int $number): array => [
                'answer' => in_array($number, $reverse, true) ? 'Нет – 0 баллов' : 'Да – 2 балла',
            ])
            ->all();

        $result = $this->summary('Тест «Шкала адаптации»', $questions);

        $this->assertSame('calculated', $result['status']);
        $this->assertSame(16, $result['metrics'][0]['score']);
        $this->assertSame(16, $result['metrics'][1]['score']);
        $this->assertSame('Высокий уровень', $result['metrics'][0]['level']);
    }

    public function test_rosenberg_uses_the_documented_reverse_key(): void
    {
        $reverse = [2, 5, 7, 8, 9, 10];
        $questions = collect(range(1, 10))
            ->map(fn (int $number): array => [
                'answer' => in_array($number, $reverse, true) ? 'Абсолютно не согласен' : 'Полностью согласен',
            ])
            ->all();

        $result = $this->summary('Шкала самооценки Розенберга', $questions);

        $this->assertSame(40, $result['metrics'][0]['score']);
        $this->assertSame('Высокое самоуважение', $result['metrics'][0]['level']);
    }

    public function test_loneliness_and_stress_follow_the_documented_ranges(): void
    {
        $loneliness = $this->summary('Опросник С.Г. Корчагиной «Одиночество»', array_fill(0, 12, ['answer' => 'всегда — 4']));
        $stress = $this->summary('Шкала психологического стресса', array_fill(0, 25, ['answer' => '8 – постоянно']));

        $this->assertSame(48, $loneliness['metrics'][0]['score']);
        $this->assertSame('Очень глубокое переживание одиночества', $loneliness['metrics'][0]['level']);
        $this->assertSame(193, $stress['metrics'][0]['score']);
        $this->assertSame('Высокий уровень стресса', $stress['metrics'][0]['level']);
    }

    public function test_undefined_stress_boundary_is_not_classified(): void
    {
        $answers = array_fill(0, 25, 1);
        foreach (range(0, 8) as $index) {
            $answers[$index] = 8;
        }
        $answers[9] = 5;

        $result = $this->summary('Шкала психологического стресса', array_map(
            fn (int $answer): array => ['answer' => $answer.' – вариант'],
            $answers,
        ));

        $this->assertSame(99, $result['metrics'][0]['score']);
        $this->assertNull($result['metrics'][0]['level']);
        $this->assertNotNull($result['message']);
    }

    public function test_obsolete_surveys_are_omitted_in_both_languages(): void
    {
        $surveys = array_map(fn (string $name): array => [
            'name' => $name,
            'questions' => [['answer' => 'да']],
        ], [
            'Опросник для определения типа темперамента',
            'Темперамент типін анықтауға арналған сауалнама',
            'Тест для определения типа характера',
            'Мінез типін анықтауға арналған тест',
        ]);
        $surveys[] = ['name' => 'PGSI', 'questions' => array_fill(0, 9, ['answer' => '0 — Никогда'])];

        foreach (['ru', 'kk'] as $locale) {
            $results = app(StudentSurveyResultService::class)->summarizeAll($surveys, $locale);

            $this->assertCount(1, $results);
            $this->assertSame('PGSI', $results[0]['name']);
            $this->assertSame('calculated', $results[0]['status']);
        }
    }

    public function test_missing_or_unscored_surveys_do_not_receive_an_invented_result(): void
    {
        $incomplete = $this->summary('Тест «Шкала адаптации»', array_fill(0, 15, ['answer' => 'Да – 2 балла']));
        $unsupported = $this->summary('Анкета без ключа расчёта', []);

        $this->assertSame('incomplete', $incomplete['status']);
        $this->assertSame([], $incomplete['metrics']);
        $this->assertSame('unavailable', $unsupported['status']);
        $this->assertSame([], $unsupported['metrics']);
    }

    public function test_hads_uses_each_questions_documented_score_and_separate_scales(): void
    {
        $answers = [
            'часто', 'да, но не очень сильно', 'иногда', 'иногда', 'совсем нет', 'совсем нет', 'часто',
            'в очень малой степени', 'да, безусловно', 'иногда', 'совсем нет',
            'возможно стал(а) уделять меньше внимания', 'как обычно', 'часто',
        ];

        $result = $this->summary('Шкала тревоги и депрессии (HADS)', array_map(
            fn (string $answer): array => ['answer' => $answer],
            $answers,
        ));

        $this->assertSame('calculated', $result['status']);
        $this->assertSame(9, $result['metrics'][0]['score']);
        $this->assertSame('Пограничный уровень', $result['metrics'][0]['level']);
        $this->assertSame(7, $result['metrics'][1]['score']);
        $this->assertSame('Норма', $result['metrics'][1]['level']);
    }

    public function test_hads_does_not_guess_unknown_answers(): void
    {
        $result = $this->summary('Шкала тревоги и депрессии (HADS)', array_fill(0, 14, ['answer' => 'другой вариант']));

        $this->assertSame('incomplete', $result['status']);
        $this->assertSame([], $result['metrics']);
    }

    public function test_four_temperament_blocks_become_one_formula_result(): void
    {
        $blocks = [
            ['I блок — Холерический темперамент', 20, 10],
            ['II блок — Сангвинический темперамент', 20, 5],
            ['III блок — Флегматический темперамент', 21, 3],
            ['IV блок — Меланхолический темперамент', 20, 2],
        ];
        $surveys = array_map(fn (array $block): array => [
            'name' => $block[0],
            'questions' => [
                ...array_fill(0, $block[2], ['answer' => 'да']),
                ...array_fill(0, $block[1] - $block[2], ['answer' => 'нет']),
            ],
        ], $blocks);

        $result = app(StudentSurveyResultService::class)->summarizeAll($surveys, 'ru');

        $this->assertCount(1, $result);
        $this->assertSame('Формула темперамента', $result[0]['name']);
        $this->assertSame('calculated', $result[0]['status']);
        $this->assertSame([50.0, 25.0, 15.0, 10.0], array_column($result[0]['metrics'], 'share'));
        $this->assertSame(21, $result[0]['metrics'][2]['maximum']);

        unset($surveys[3]);
        $incomplete = app(StudentSurveyResultService::class)->summarizeAll(array_values($surveys), 'ru');
        $this->assertSame('incomplete', $incomplete[0]['status']);
    }

    public function test_five_traits_use_question_specific_choice_keys_and_documented_interpretations(): void
    {
        $questions = array_fill(0, 30, ['answer' => 'а) да,']);
        $questions[0] = ['answer' => 'в) нет.'];

        $result = $this->summary('Тест Пять черт характера С. Грачева. Опросники по психологии личности.', $questions);

        $this->assertSame('calculated', $result['status']);
        $this->assertSame([8, 6, 6, 6, 6], array_column($result['metrics'], 'score'));
        $this->assertSame([
            'Более замкнутый, сдержанный человек',
            'Эмоционально неустойчивый человек',
            'Практичный, добросовестный человек',
            'Более расчётливый, проницательный человек',
            'Низкий уровень самоконтроля',
        ], array_column($result['metrics'], 'level'));
        $this->assertNull($result['message']);
        $this->assertNotEmpty($result['metrics'][0]['description']);
    }

    public function test_five_traits_change_interpretation_at_each_documented_boundary(): void
    {
        $questions = array_fill(0, 30, ['answer' => 'б) иногда']);
        $cases = [
            [0, [1, 7], 8, 'Более замкнутый, сдержанный человек', 9, 'Общительный, открытый человек'],
            [1, [], 6, 'Эмоционально неустойчивый человек', 7, 'Эмоционально устойчивый человек'],
            [2, [], 6, 'Практичный, добросовестный человек', 7, 'Романтичный, творческий человек'],
            [3, [4], 5, 'Более прямолинейный, естественный человек', 6, 'Более расчётливый, проницательный человек'],
            [4, [], 6, 'Низкий уровень самоконтроля', 7, 'Высокий уровень самоконтроля'],
        ];
        $boundaryQuestion = [25, 2, 3, 4, 23];

        foreach ($cases as [$scale, $lowerChoices, $lowerScore, $lowerLevel, $upperScore, $upperLevel]) {
            $answers = $questions;
            foreach ($lowerChoices as $number) {
                $answers[$number - 1] = ['answer' => $scale === 3 ? 'а) да' : 'в) нет'];
            }
            $lower = $this->summary('Тест Пять черт характера С. Грачева. Опросники по психологии личности.', $answers)['metrics'][$scale];
            $this->assertSame($lowerScore, $lower['score']);
            $this->assertSame($lowerLevel, $lower['level']);

            $answers[$boundaryQuestion[$scale] - 1] = ['answer' => $scale === 3 ? 'б) иногда' : 'в) нет'];
            $upper = $this->summary('Тест Пять черт характера С. Грачева. Опросники по психологии личности.', $answers)['metrics'][$scale];
            $this->assertSame($upperScore, $upper['score']);
            $this->assertSame($upperLevel, $upper['level']);
            $this->assertNotSame($lower['description'], $upper['description']);
        }
    }

    public function test_sl19_reverses_protective_items_and_pgsi_uses_documented_ranges(): void
    {
        $protective = [1, 2, 8, 12, 13, 18, 19];
        $questions = collect(range(1, 19))->map(fn (int $number): array => [
            'answer' => in_array($number, $protective, true) ? 'нет' : 'да',
        ])->all();

        $sl19 = $this->summary('Тест «СЛ-19»', $questions);
        $pgsiNone = $this->summary('PGSI', array_fill(0, 9, ['answer' => '0 — Никогда']));
        $pgsiHigh = $this->summary('PGSI', array_fill(0, 9, ['answer' => '1 — Иногда']));

        $this->assertSame(19, $sl19['metrics'][0]['score']);
        $this->assertSame('Высокий показатель по СЛ-19', $sl19['metrics'][0]['level']);
        $this->assertSame(0, $pgsiNone['metrics'][0]['score']);
        $this->assertSame('Нет признаков риска по PGSI', $pgsiNone['metrics'][0]['level']);
        $this->assertSame(9, $pgsiHigh['metrics'][0]['score']);
        $this->assertSame('Высокий показатель по PGSI', $pgsiHigh['metrics'][0]['level']);
    }

    public function test_pgsi_classification_changes_at_documented_boundaries(): void
    {
        foreach ([
            0 => 'Нет признаков риска по PGSI',
            1 => 'Низкий показатель по PGSI',
            2 => 'Низкий показатель по PGSI',
            3 => 'Средний показатель по PGSI',
            7 => 'Средний показатель по PGSI',
            8 => 'Высокий показатель по PGSI',
        ] as $score => $level) {
            $questions = [
                ...array_fill(0, $score, ['answer' => '1 — Иногда']),
                ...array_fill(0, 9 - $score, ['answer' => '0 — Никогда']),
            ];
            $result = $this->summary('PGSI', $questions);

            $this->assertSame($score, $result['metrics'][0]['score']);
            $this->assertSame($level, $result['metrics'][0]['level']);
        }
    }

    private function summary(string $name, array $questions): array
    {
        return app(StudentSurveyResultService::class)->summarize([
            'name' => $name,
            'questions' => $questions,
        ]);
    }
}
