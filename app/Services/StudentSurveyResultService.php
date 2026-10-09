<?php

namespace App\Services;

use Illuminate\Support\Str;

class StudentSurveyResultService
{
    private const EXCLUDED_SURVEYS = [
        'Опросник для определения типа темперамента',
        'Темперамент типін анықтауға арналған сауалнама',
        'Тест для определения типа характера',
        'Мінез типін анықтауға арналған тест',
    ];

    /** @param array<int, array{name: ?string, questions: array<int, array{question: ?string, answer: ?string}>}> $surveys */
    public function summarizeAll(array $surveys, string $locale): array
    {
        $surveys = array_values(array_filter(
            $surveys,
            fn (array $survey): bool => ! in_array($survey['name'] ?? null, self::EXCLUDED_SURVEYS, true),
        ));

        $blocks = [];
        foreach ($surveys as $survey) {
            $number = $this->temperamentBlockNumber($survey['name'] ?? null);
            if ($number !== null) {
                $blocks[$number] = $survey['questions'] ?? [];
            }
        }

        $results = [];
        $formulaAdded = false;
        foreach ($surveys as $survey) {
            if ($this->temperamentBlockNumber($survey['name'] ?? null) !== null) {
                if (! $formulaAdded) {
                    $results[] = [
                        'key' => 'temperament_formula',
                        'name' => $locale === 'kk' ? 'Темперамент формуласы' : 'Формула темперамента',
                        ...$this->temperamentFormula($blocks),
                    ];
                    $formulaAdded = true;
                }
                continue;
            }

            $results[] = $this->summarize($survey);
        }

        return $results;
    }

    /**
     * @param  array{name: ?string, questions: array<int, array{question: ?string, answer: ?string}>}  $survey
     * @return array<string, mixed>
     */
    public function summarize(array $survey): array
    {
        $name = $survey['name'] ?? null;
        $questions = $survey['questions'] ?? [];

        $key = match ($name) {
            'Шкала самооценки Розенберга', 'Розенбергтің өзін-өзі бағалау шкаласы' => 'self_esteem',
            'Тест «Шкала адаптации»', 'Тест «Бейімделу шкаласы»' => 'adaptation',
            'Опросник С.Г. Корчагиной «Одиночество»', 'С.Г.Корчагинаның «Жалғыздық» сауалнамасы' => 'loneliness',
            'Шкала психологического стресса', 'Психологиялық стресс шкаласы' => 'stress',
            'Шкала тревоги и депрессии (HADS)', 'Мазасыздық және депрессия шкаласы (HADS)' => 'hads',
            'Тест Пять черт характера С. Грачева. Опросники по психологии личности.', 'С. Грачевтің «Тұлғаның бес сипаты» тесті' => 'five_traits',
            'Тест «СЛ-19»' => 'sl19',
            'PGSI' => 'pgsi',
            default => null,
        };

        $summary = match ($key) {
            'self_esteem' => $this->selfEsteem($questions),
            'adaptation' => $this->adaptation($questions),
            'loneliness' => $this->loneliness($questions),
            'stress' => $this->stress($questions),
            'hads' => $this->hads($questions),
            'five_traits' => $this->fiveTraits($questions),
            'sl19' => $this->sl19($questions),
            'pgsi' => $this->pgsi($questions),
            default => $this->unavailable(),
        };

        return ['key' => $key, 'name' => $name, ...$summary];
    }

    /** @param array<int, array{answer: ?string}> $questions */
    private function selfEsteem(array $questions): array
    {
        $answers = $this->answers($questions, 10, $this->agreement(...));

        if ($answers === null) {
            return $this->incomplete();
        }

        foreach ([2, 5, 7, 8, 9, 10] as $number) {
            $answers[$number - 1] = 5 - $answers[$number - 1];
        }

        $score = array_sum($answers);
        $level = match (true) {
            $score <= 18 => 'Низкая самооценка',
            $score <= 22 => 'Нестабильная самооценка',
            $score <= 34 => 'Адекватная самооценка',
            default => 'Высокое самоуважение',
        };

        return $this->calculated([$this->metric('Самооценка', $score, 40, $level)]);
    }

    /** @param array<int, array{answer: ?string}> $questions */
    private function adaptation(array $questions): array
    {
        $answers = $this->answers($questions, 16, $this->adaptationAnswer(...));

        if ($answers === null) {
            return $this->incomplete();
        }

        foreach ([2, 5, 7, 8, 11, 12, 15, 16] as $number) {
            $answers[$number - 1] = 2 - $answers[$number - 1];
        }

        $groupScore = array_sum(array_slice($answers, 0, 8));
        $studyScore = array_sum(array_slice($answers, 8, 8));
        $level = fn (int $score): string => match (true) {
            $score <= 8 => 'Низкий уровень',
            $score <= 12 => 'Средний уровень',
            default => 'Высокий уровень',
        };

        return $this->calculated([
            $this->metric('Адаптация к группе', $groupScore, 16, $level($groupScore)),
            $this->metric('Адаптация к учёбе', $studyScore, 16, $level($studyScore)),
        ]);
    }

    /** @param array<int, array{answer: ?string}> $questions */
    private function loneliness(array $questions): array
    {
        $answers = $this->answers($questions, 12, $this->lonelinessAnswer(...));

        if ($answers === null) {
            return $this->incomplete();
        }

        $score = array_sum($answers);
        $level = match (true) {
            $score <= 16 => 'Одиночество не выражено',
            $score <= 27 => 'Лёгкое переживание одиночества',
            $score <= 38 => 'Выраженное одиночество',
            default => 'Очень глубокое переживание одиночества',
        };

        return $this->calculated([$this->metric('Одиночество', $score, 48, $level)]);
    }

    /** @param array<int, array{answer: ?string}> $questions */
    private function stress(array $questions): array
    {
        $answers = $this->answers($questions, 25, $this->stressAnswer(...));

        if ($answers === null) {
            return $this->incomplete();
        }

        $answers[13] = 9 - $answers[13];
        $score = array_sum($answers);
        $level = match (true) {
            $score > 155 => 'Высокий уровень стресса',
            $score >= 100 => 'Средний уровень стресса',
            $score < 99 => 'Низкий уровень стресса',
            default => null,
        };

        return $this->calculated(
            [$this->metric('Психическая напряжённость', $score, 200, $level)],
            $level === null ? 'Порог для 99 баллов в ТЗ не указан.' : null,
        );
    }

    /** @param array<int, array{answer: ?string}> $questions */
    private function hads(array $questions): array
    {
        if (count($questions) !== 14) {
            return $this->incomplete();
        }

        $scores = [];
        foreach ($questions as $index => $question) {
            $score = $this->hadsAnswer($index, $question['answer'] ?? null);
            if ($score === null) {
                return $this->incomplete();
            }
            $scores[] = $score;
        }

        $level = fn (int $score): string => match (true) {
            $score <= 7 => 'Норма',
            $score <= 10 => 'Пограничный уровень',
            default => 'Высокий показатель',
        };

        $anxiety = array_sum(array_slice($scores, 0, 7));
        $depression = array_sum(array_slice($scores, 7, 7));

        return $this->calculated([
            $this->metric('Тревога (HADS-A)', $anxiety, 21, $level($anxiety)),
            $this->metric('Депрессия (HADS-D)', $depression, 21, $level($depression)),
        ]);
    }

    private function hadsAnswer(int $index, ?string $answer): ?int
    {
        // The Hub returns the selected option text, while the TЗ assigns a different score to the same text in different questions.
        $options = [
            ['совсем не чувствую', 'иногда', 'часто', 'всё время'],
            ['совсем не испытываю', 'иногда, но это меня не беспокоит', 'да, но не очень сильно', 'да, очень сильно'],
            ['редко', 'иногда', 'большую часть времени', 'постоянно'],
            ['безусловно да', 'скорее да', 'иногда', 'совсем нет'],
            ['совсем нет', 'иногда', 'часто', 'очень часто'],
            ['совсем нет', 'в некоторой степени', 'скорее да', 'безусловно да'],
            ['никогда', 'редко', 'часто', 'очень часто'],
            ['безусловно да', 'возможно да', 'в очень малой степени', 'совсем нет'],
            ['совсем не способен(на)', 'в очень малой степени', 'возможно да', 'да, безусловно'],
            ['почти всегда', 'иногда', 'очень редко', 'совсем не чувствую'],
            ['совсем нет', 'иногда', 'часто', 'почти всегда'],
            ['как и раньше слежу за собой', 'возможно стал(а) уделять меньше внимания', 'уделяю меньше времени', 'да, безусловно'],
            ['как обычно', 'да, но меньше, чем раньше', 'значительно меньше, чем раньше', 'совсем нет'],
            ['часто', 'иногда', 'редко', 'очень редко'],
        ];

        $kazakhOptions = [
            1 => ['жиі' => 2],
            2 => ['иә, солай, бірақ қорқыныш онша үлкен емес' => 2],
            3 => ['кейде және жиі емес' => 1],
            4 => ['анда-санда ғана' => 2],
            5 => ['мен мүлдем сезінбеймін' => 0],
            6 => ['мүлдем сезінбеймін' => 0],
            7 => ['жиі' => 2],
            8 => ['өте аз дәрежеде ғана, солай' => 2],
            9 => ['әрине, солай' => 3],
            10 => ['кейде' => 1],
            11 => ['мүлдем жоқ' => 0],
            12 => ['мүмкін мен оған аз уақыт бөле бастадым' => 1],
            13 => ['әдеттегідей' => 0],
            14 => ['жиі' => 0],
        ];

        $normalized = str_replace('ё', 'е', Str::lower(trim((string) $answer)));
        $choices = array_map(fn (string $choice): string => str_replace('ё', 'е', $choice), $options[$index]);
        $score = array_search($normalized, $choices, true);

        return $score === false ? ($kazakhOptions[$index + 1][$normalized] ?? null) : $score;
    }

    private function temperamentBlockNumber(?string $name): ?string
    {
        return preg_match('/^(IV|III|II|I)\s+блок/iu', (string) $name, $matches)
            ? strtoupper($matches[1])
            : null;
    }

    /** @param array<string, array<int, array{answer: ?string}>> $blocks */
    private function temperamentFormula(array $blocks): array
    {
        $definitions = [
            'I' => ['label' => 'Холерический темперамент', 'count' => 20],
            'II' => ['label' => 'Сангвинический темперамент', 'count' => 20],
            'III' => ['label' => 'Флегматический темперамент', 'count' => 21],
            'IV' => ['label' => 'Меланхолический темперамент', 'count' => 20],
        ];

        $counts = [];
        foreach ($definitions as $number => $definition) {
            $answers = $this->answers($blocks[$number] ?? [], $definition['count'], $this->yesNo(...));
            if ($answers === null) {
                return $this->incomplete();
            }
            $counts[$number] = count(array_filter($answers));
        }

        $total = array_sum($counts);
        $metrics = [];
        foreach ($definitions as $number => $definition) {
            $share = $total > 0 ? round($counts[$number] * 100 / $total, 1) : null;
            $level = match (true) {
                $share === null => null,
                $share >= 40 => 'Доминирующий тип',
                $share >= 30 => 'Ярко выраженные черты',
                $share >= 20 => 'Средне выраженные черты',
                $share >= 10 => 'Слабо выраженные черты',
                default => null,
            };
            $metrics[] = [
                ...$this->metric($definition['label'], $counts[$number], $definition['count'], $level),
                'share' => $share,
            ];
        }

        return $this->calculated(
            $metrics,
            $total === 0 ? 'Нет положительных ответов; доли не рассчитаны.' : null,
        );
    }

    /** @param array<int, array{answer: ?string}> $questions */
    private function fiveTraits(array $questions): array
    {
        $answers = $this->answers($questions, 30, $this->choiceLetter(...));
        if ($answers === null) {
            return $this->incomplete();
        }

        $scales = [
            'Замкнутость / общительность' => [
                [1, 7, 9, 13, 19, 25], [0, 0, 2, 2, 2, 0], 8,
                ['Более замкнутый, сдержанный человек', 'Предпочитает небольшой круг общения, не всегда испытывает потребность в постоянном контакте с другими людьми. Может быть осторожным в установлении новых знакомств, избирательным в дружеских отношениях и склонным к самостоятельности.'],
                ['Общительный, открытый человек', 'Легко вступает в контакт с людьми, доброжелателен, внимателен и непринуждён в общении. Хорошо чувствует себя в коллективе, легче устанавливает новые знакомства и открыт к взаимодействию с окружающими.'],
            ],
            'Эмоциональная устойчивость / неустойчивость' => [
                [2, 5, 8, 14, 20, 26], [0, 0, 2, 2, 2, 0], 6,
                ['Эмоционально неустойчивый человек', 'Более чувствителен к происходящим событиям, может сильнее переживать ситуации, испытывать колебания настроения и эмоциональные реакции. Иногда может быть импульсивным в эмоционально значимых обстоятельствах.'],
                ['Эмоционально устойчивый человек', 'Спокойный, выдержанный, способен сохранять самообладание в различных ситуациях. Более реалистично оценивает происходящее, умеет контролировать эмоциональные реакции и сохранять стабильное настроение.'],
            ],
            'Практицизм / романтизм' => [
                [3, 6, 15, 18, 21, 27], [0, 0, 2, 2, 2, 0], 6,
                ['Практичный, добросовестный человек', 'Реалистично относится к жизни, предпочитает конкретность и порядок, хорошо ориентируется на общепринятые нормы и правила. Склонен опираться на реальные обстоятельства и практическую пользу.'],
                ['Романтичный, творческий человек', 'Обладает богатым воображением, развитой фантазией и творческим потенциалом. Склонен мечтать, видеть необычные возможности и воспринимать мир более эмоционально и образно. Иногда может быть менее ориентирован на практическую сторону ситуации.'],
            ],
            'Расчётливость / прямолинейность' => [
                [4, 10, 16, 22, 24, 28], [0, 2, 2, 2, 0, 0], 5,
                ['Более прямолинейный, естественный человек', 'Открыто выражает своё мнение, склонен к непосредственности и искренности в общении. Может меньше анализировать скрытые мотивы людей и ситуации, предпочитая простое и непосредственное взаимодействие.'],
                ['Более расчётливый, проницательный человек', 'Склонен внимательно анализировать людей и обстоятельства, учитывать возможные последствия и рационально оценивать ситуацию. В принятии решений больше опирается на разум и практическую целесообразность.'],
            ],
            'Самоконтроль' => [
                [11, 12, 17, 23, 29, 30], [2, 2, 2, 0, 0, 0], 6,
                ['Низкий уровень самоконтроля', 'Может быть характерна внутренняя конфликтность, трудности с управлением эмоциональными реакциями и поведением. В отдельных ситуациях человеку может быть сложно придерживаться установленных правил и требований.'],
                ['Высокий уровень самоконтроля', 'Целеустремлённость, организованность, способность контролировать эмоции и поведение. Человек способен придерживаться общепринятых правил, требований и норм поведения.'],
            ],
        ];

        $metrics = [];
        foreach ($scales as $label => [$numbers, $aScores, $lowMaximum, $low, $high]) {
            $score = 0;
            foreach ($numbers as $index => $number) {
                $choice = $answers[$number - 1];
                $score += $choice === 1 ? 1 : ($choice === 0 ? $aScores[$index] : 2 - $aScores[$index]);
            }
            [$level, $description] = $score <= $lowMaximum ? $low : $high;
            $metrics[] = [...$this->metric($label, $score, 12, $level), 'description' => $description];
        }

        return $this->calculated($metrics);
    }

    /** @param array<int, array{answer: ?string}> $questions */
    private function sl19(array $questions): array
    {
        $answers = $this->answers($questions, 19, $this->yesNo(...));
        if ($answers === null) {
            return $this->incomplete();
        }

        $reverse = [1, 2, 8, 12, 13, 18, 19];
        $score = 0;
        foreach ($answers as $index => $yes) {
            $score += in_array($index + 1, $reverse, true) ? (int) ! $yes : (int) $yes;
        }

        $level = match (true) {
            $score <= 5 => 'Низкий показатель по СЛ-19',
            $score <= 12 => 'Средний показатель по СЛ-19',
            default => 'Высокий показатель по СЛ-19',
        };

        return $this->calculated(
            [$this->metric('Показатель СЛ-19', $score, 19, $level)],
            'Скрининговый результат требует оценки психолога.',
        );
    }

    /** @param array<int, array{answer: ?string}> $questions */
    private function pgsi(array $questions): array
    {
        $answers = $this->answers($questions, 9, $this->pgsiAnswer(...));
        if ($answers === null) {
            return $this->incomplete();
        }

        $score = array_sum($answers);
        $level = match (true) {
            $score === 0 => 'Нет признаков риска по PGSI',
            $score <= 2 => 'Низкий показатель по PGSI',
            $score <= 7 => 'Средний показатель по PGSI',
            default => 'Высокий показатель по PGSI',
        };

        return $this->calculated([$this->metric('Игровое поведение (PGSI)', $score, 27, $level)]);
    }

    private function choiceLetter(?string $answer): ?int
    {
        if (! preg_match('/^\s*([аaбbвv])\s*\)/iu', (string) $answer, $match)) {
            return null;
        }

        return match (Str::lower($match[1])) {
            'а', 'a' => 0,
            'б', 'b' => 1,
            'в', 'v' => 2,
        };
    }

    private function pgsiAnswer(?string $answer): ?int
    {
        if (preg_match('/^\s*([0-3])\s*[—–-]/u', (string) $answer, $match)) {
            return (int) $match[1];
        }

        return match (Str::lower(trim((string) $answer))) {
            'никогда', 'ешқашан' => 0,
            'иногда', 'кейде' => 1,
            'большую часть времени', 'көбінесе' => 2,
            'почти всегда', 'әрқашан дерлік' => 3,
            default => null,
        };
    }

    /**
     * @param  array<int, array{answer: ?string}>  $questions
     * @return array<int, int|bool>|null
     */
    private function answers(array $questions, int $expected, callable $parse): ?array
    {
        if (count($questions) !== $expected) {
            return null;
        }

        $answers = [];
        foreach ($questions as $question) {
            $answer = $parse($question['answer'] ?? null);

            if ($answer === null) {
                return null;
            }

            $answers[] = $answer;
        }

        return $answers;
    }

    private function yesNo(?string $answer): ?bool
    {
        return match (Str::lower(trim((string) $answer))) {
            'да', 'иә', 'ия' => true,
            'нет', 'жоқ' => false,
            default => null,
        };
    }

    private function agreement(?string $answer): ?int
    {
        return match (Str::lower(trim((string) $answer))) {
            'полностью согласен', 'толық келісемін' => 4,
            'согласен', 'келісемін' => 3,
            'не согласен', 'келіспеймін' => 2,
            'абсолютно не согласен', 'мүлдем келіспеймін' => 1,
            default => null,
        };
    }

    private function adaptationAnswer(?string $answer): ?int
    {
        $value = Str::lower(trim((string) $answer));

        return match (true) {
            Str::startsWith($value, ['да', 'иә', 'ия']) => 2,
            Str::startsWith($value, ['затрудняюсь', 'айту қиын']) => 1,
            Str::startsWith($value, ['нет', 'жоқ']) => 0,
            default => null,
        };
    }

    private function lonelinessAnswer(?string $answer): ?int
    {
        if (! preg_match('/(?:^|\s)([1-4])\s*$/u', (string) $answer, $match)) {
            return null;
        }

        return (int) $match[1];
    }

    private function stressAnswer(?string $answer): ?int
    {
        if (! preg_match('/^\s*([1-8])(?:\s|[–—-]|$)/u', (string) $answer, $match)) {
            return null;
        }

        return (int) $match[1];
    }

    private function metric(string $label, int $score, int $maximum, ?string $level = null): array
    {
        return compact('label', 'score', 'maximum', 'level');
    }

    private function calculated(array $metrics, ?string $message = null): array
    {
        return ['status' => 'calculated', 'metrics' => $metrics, 'message' => $message];
    }

    private function incomplete(): array
    {
        return ['status' => 'incomplete', 'metrics' => [], 'message' => 'Недостаточно ответов для расчёта.'];
    }

    private function unavailable(?string $message = null): array
    {
        return ['status' => 'unavailable', 'metrics' => [], 'message' => $message ?? 'Методика расчёта для этой анкеты в ТЗ не указана.'];
    }
}
