<?php

declare(strict_types=1);

/*
 * Demo question bank for DemoContentSeeder (local/testing only).
 *
 * Each entry: [teacher, category slug, type, difficulty, body, explanation|null, spec, tags]
 * `spec` is a compact, human-written form that the seeder turns into a definition
 * value object with random short ids:
 *   single_choice   ['options' => [...], 'correct' => index]
 *   multiple_choice ['options' => [...], 'correct' => [indexes], 'scoring' => 'partial'|'all_or_nothing']
 *   true_false      ['correct' => bool]
 *   text            ['max_length' => int, 'reference' => string|null]
 *   numeric         ['correct' => decimal string, 'tolerance' => decimal string, 'unit' => string|null]
 *   ordering        ['items' => [... in the correct order]]
 *   matching        ['pairs' => [[left, right], ...], 'distractors' => [...]]
 */

return [
    // ── Mathematics / Algebra (teacher1) ──────────────────────────────────────
    ['teacher1', 'algebra', 'single_choice', 'easy', 'რას უდრის 7 × 8?', 'გამრავლების ტაბულა: 7 × 8 = 56.',
        ['options' => ['54', '56', '63', '48'], 'correct' => 1], ['arithmetic']],
    ['teacher1', 'algebra', 'numeric', 'easy', 'Solve for x: 2x + 6 = 14', 'Subtract 6, then divide by 2: x = 4.',
        ['correct' => '4', 'tolerance' => '0', 'unit' => null], ['equations']],
    ['teacher1', 'algebra', 'multiple_choice', 'medium', 'Which of these numbers are prime?', 'A prime has exactly two divisors: 1 and itself.',
        ['options' => ['2', '9', '13', '21', '29'], 'correct' => [0, 2, 4], 'scoring' => 'partial'], ['number-theory']],
    ['teacher1', 'algebra', 'true_false', 'easy', 'ნებისმიერი ლუწი რიცხვის კვადრატი ლუწია.', '(2k)² = 4k², რაც ლუწია.',
        ['correct' => true], ['number-theory']],
    ['teacher1', 'algebra', 'numeric', 'medium', 'რას უდრის √144?', null,
        ['correct' => '12', 'tolerance' => '0', 'unit' => null], ['arithmetic']],
    ['teacher1', 'algebra', 'single_choice', 'hard', 'What are the roots of x² − 5x + 6 = 0?', 'Factor: (x − 2)(x − 3) = 0.',
        ['options' => ['1 and 6', '2 and 3', '−2 and −3', '−1 and 6'], 'correct' => 1], ['equations']],
    ['teacher1', 'algebra', 'ordering', 'medium', 'დაალაგეთ რიცხვები ზრდადობით.', null,
        ['items' => ['−3', '0.5', '¾', '2', '√5']], ['arithmetic']],
    ['teacher1', 'algebra', 'text', 'hard', 'Explain why division by zero is undefined.', null,
        ['max_length' => 800, 'reference' => 'No number multiplied by 0 gives a non-zero dividend, and 0/0 would allow every number as an answer.'], ['number-theory']],

    // ── Mathematics / Geometry (teacher1) ─────────────────────────────────────
    ['teacher1', 'geometry', 'numeric', 'easy', 'რას უდრის სამკუთხედის შიდა კუთხეების ჯამი (გრადუსებში)?', null,
        ['correct' => '180', 'tolerance' => '0', 'unit' => '°'], ['angles']],
    ['teacher1', 'geometry', 'numeric', 'medium', 'Area of a circle with radius 3 cm (use π ≈ 3.14)?', 'A = πr² = 3.14 × 9.',
        ['correct' => '28.26', 'tolerance' => '0.1', 'unit' => 'cm²'], ['area']],
    ['teacher1', 'geometry', 'single_choice', 'medium', 'How many faces does a cube have?', null,
        ['options' => ['4', '6', '8', '12'], 'correct' => 1], ['solids']],
    ['teacher1', 'geometry', 'matching', 'hard', 'შეუსაბამეთ ფიგურას ფართობის ფორმულა.', null,
        ['pairs' => [['კვადრატი', 'a²'], ['სამკუთხედი', '½·a·h'], ['წრე', 'πr²']], 'distractors' => ['2πr']], ['area']],
    ['teacher1', 'geometry', 'true_false', 'hard', 'Every rhombus is a square.', 'A square is a rhombus with right angles, not the other way round.',
        ['correct' => false], ['angles']],

    // ── History (teacher1) ────────────────────────────────────────────────────
    ['teacher1', 'georgian-history', 'single_choice', 'easy', 'რომელ წელს მოხდა დიდგორის ბრძოლა?', 'დავით აღმაშენებლის გამარჯვება 1121 წელს.',
        ['options' => ['1089', '1121', '1184', '1213'], 'correct' => 1], ['middle-ages', 'georgia']],
    ['teacher1', 'georgian-history', 'single_choice', 'medium', 'ვინ დაწერა „ვეფხისტყაოსანი“?', null,
        ['options' => ['შოთა რუსთაველი', 'ილია ჭავჭავაძე', 'ნიკოლოზ ბარათაშვილი', 'აკაკი წერეთელი'], 'correct' => 0], ['literature', 'georgia']],
    ['teacher1', 'georgian-history', 'ordering', 'hard', 'დაალაგეთ მეფეები ქრონოლოგიურად.', null,
        ['items' => ['ფარნავაზი', 'ვახტანგ გორგასალი', 'დავით აღმაშენებელი', 'თამარ მეფე']], ['middle-ages', 'georgia']],
    ['teacher1', 'georgian-history', 'true_false', 'easy', 'საქართველოს დამოუკიდებლობა 1991 წლის 9 აპრილს აღდგა.', null,
        ['correct' => true], ['georgia']],
    ['teacher1', 'georgian-history', 'numeric', 'medium', 'რომელ წელს გამოცხადდა საქართველოს დემოკრატიული რესპუბლიკა?', '1918 წლის 26 მაისი.',
        ['correct' => '1918', 'tolerance' => '0', 'unit' => null], ['georgia']],
    ['teacher1', 'georgian-history', 'text', 'hard', 'აღწერეთ დავით აღმაშენებლის რეფორმების მნიშვნელობა.', null,
        ['max_length' => 1000, 'reference' => 'სამხედრო რეფორმა, ეკლესიის რეფორმა (რუის-ურბნისის კრება), ქვეყნის გაერთიანება.'], ['middle-ages', 'georgia']],
    ['teacher1', 'world-history', 'single_choice', 'easy', 'In which year did World War II end?', null,
        ['options' => ['1918', '1939', '1945', '1950'], 'correct' => 2], ['modern-history']],
    ['teacher1', 'world-history', 'matching', 'medium', 'Match the event to its century.', null,
        ['pairs' => [['Fall of Constantinople', '15th'], ['French Revolution', '18th'], ['First Moon landing', '20th']], 'distractors' => ['12th']], ['modern-history']],
    ['teacher1', 'world-history', 'multiple_choice', 'hard', 'Which of these were empires of antiquity?', null,
        ['options' => ['Roman Empire', 'Ottoman Empire', 'Achaemenid Empire', 'British Empire'], 'correct' => [0, 2], 'scoring' => 'all_or_nothing'], ['antiquity']],
    ['teacher1', 'world-history', 'true_false', 'medium', 'The Berlin Wall fell in 1989.', null,
        ['correct' => true], ['modern-history']],

    // ── Biology (teacher1) ────────────────────────────────────────────────────
    ['teacher1', 'biology', 'single_choice', 'easy', 'Which organelle is called the "powerhouse of the cell"?', null,
        ['options' => ['Nucleus', 'Ribosome', 'Mitochondrion', 'Golgi apparatus'], 'correct' => 2], ['cells']],
    ['teacher1', 'biology', 'multiple_choice', 'medium', 'რომელი ორგანიზმებია ხერხემლიანი?', null,
        ['options' => ['ორაგული', 'ობობა', 'ბაყაყი', 'ლოკოკინა', 'არწივი'], 'correct' => [0, 2, 4], 'scoring' => 'partial'], ['animals']],
    ['teacher1', 'biology', 'true_false', 'easy', 'Plants release oxygen during photosynthesis.', null,
        ['correct' => true], ['plants']],
    ['teacher1', 'biology', 'ordering', 'medium', 'Order the levels of organisation from smallest to largest.', null,
        ['items' => ['Cell', 'Tissue', 'Organ', 'Organ system', 'Organism']], ['cells']],
    ['teacher1', 'biology', 'numeric', 'hard', 'How many chromosomes does a typical human body cell have?', '23 pairs.',
        ['correct' => '46', 'tolerance' => '0', 'unit' => null], ['genetics']],
    ['teacher1', 'biology', 'text', 'medium', 'რა განსხვავებაა მიტოზსა და მეიოზს შორის?', null,
        ['max_length' => 600, 'reference' => 'მიტოზი: ორი იდენტური დიპლოიდური უჯრედი; მეიოზი: ოთხი ჰაპლოიდური გამეტა.'], ['genetics']],

    // ── Geography (teacher1) ──────────────────────────────────────────────────
    ['teacher1', 'geography', 'single_choice', 'easy', 'რომელია საქართველოს უმაღლესი მწვერვალი?', null,
        ['options' => ['ყაზბეგი', 'შხარა', 'თეთნულდი', 'უშბა'], 'correct' => 1], ['georgia', 'mountains']],
    ['teacher1', 'geography', 'matching', 'easy', 'Match the country to its capital.', null,
        ['pairs' => [['Georgia', 'Tbilisi'], ['Armenia', 'Yerevan'], ['Azerbaijan', 'Baku']], 'distractors' => ['Ankara']], ['capitals']],
    ['teacher1', 'geography', 'numeric', 'medium', 'Approximately how high is Mount Everest, in metres?', null,
        ['correct' => '8849', 'tolerance' => '10', 'unit' => 'm'], ['mountains']],
    ['teacher1', 'geography', 'true_false', 'medium', 'The Mtkvari (Kura) river flows into the Caspian Sea.', null,
        ['correct' => true], ['georgia', 'rivers']],
    ['teacher1', 'geography', 'text', 'easy', 'დაწერეთ საქართველოს სამი მსხვილი ქალაქი.', null,
        ['max_length' => 200, 'reference' => 'თბილისი, ქუთაისი, ბათუმი (ასევე რუსთავი, გორი, ზუგდიდი).'], ['georgia']],
    ['teacher1', 'geography', 'multiple_choice', 'hard', 'Which of these countries border Georgia?', null,
        ['options' => ['Turkey', 'Iran', 'Russia', 'Armenia', 'Ukraine'], 'correct' => [0, 2, 3], 'scoring' => 'all_or_nothing'], ['georgia', 'capitals']],

    // ── Programming / PHP (teacher2) ──────────────────────────────────────────
    ['teacher2', 'php', 'single_choice', 'easy', 'Which symbol starts a variable name in PHP?', null,
        ['options' => ['@', '#', '$', '&'], 'correct' => 2], ['php', 'syntax']],
    ['teacher2', 'php', 'true_false', 'easy', 'In PHP 8, `0 == "a"` evaluates to false.', 'PHP 8 changed string-to-number comparison.',
        ['correct' => true], ['php', 'types']],
    ['teacher2', 'php', 'multiple_choice', 'medium', 'Which of these are PHP scalar types?', null,
        ['options' => ['int', 'array', 'string', 'bool', 'object'], 'correct' => [0, 2, 3], 'scoring' => 'partial'], ['php', 'types']],
    ['teacher2', 'php', 'numeric', 'medium', 'What does `intdiv(17, 5)` return?', null,
        ['correct' => '3', 'tolerance' => '0', 'unit' => null], ['php']],
    ['teacher2', 'php', 'ordering', 'hard', 'Order the steps of a Laravel HTTP request.', null,
        ['items' => ['public/index.php', 'HTTP kernel', 'Middleware', 'Router', 'Controller', 'Response']], ['php', 'laravel']],
    ['teacher2', 'php', 'matching', 'medium', 'Match the HTTP status code to its meaning.', null,
        ['pairs' => [['200', 'OK'], ['403', 'Forbidden'], ['404', 'Not Found'], ['422', 'Unprocessable Content']], 'distractors' => ['Teapot']], ['http']],
    ['teacher2', 'php', 'text', 'hard', 'Why should user input never be concatenated into SQL queries?', null,
        ['max_length' => 800, 'reference' => 'SQL injection: use prepared statements / query bindings so input is data, not code.'], ['security']],
    ['teacher2', 'php', 'single_choice', 'hard', 'Which function compares two strings in constant time?', 'Use it for tokens and hashes to avoid timing attacks.',
        ['options' => ['strcmp', 'hash_equals', 'strcasecmp', 'similar_text'], 'correct' => 1], ['security', 'php']],

    // ── Programming / JavaScript (teacher2) ───────────────────────────────────
    ['teacher2', 'javascript', 'single_choice', 'easy', 'Which keyword declares a block-scoped constant?', null,
        ['options' => ['var', 'let', 'const', 'static'], 'correct' => 2], ['javascript', 'syntax']],
    ['teacher2', 'javascript', 'true_false', 'medium', '`typeof null` returns "null".', 'It returns "object" (a historical quirk).',
        ['correct' => false], ['javascript', 'types']],
    ['teacher2', 'javascript', 'numeric', 'medium', 'What is `[1, 2, 3].reduce((a, b) => a + b, 10)`?', null,
        ['correct' => '16', 'tolerance' => '0', 'unit' => null], ['javascript']],
    ['teacher2', 'javascript', 'multiple_choice', 'hard', 'Which values are falsy in JavaScript?', null,
        ['options' => ['0', '"0"', 'null', '[]', 'NaN'], 'correct' => [0, 2, 4], 'scoring' => 'partial'], ['javascript', 'types']],
    ['teacher2', 'javascript', 'matching', 'hard', 'Match the array method to what it returns.', null,
        ['pairs' => [['map', 'a new array'], ['find', 'the first match'], ['some', 'a boolean']], 'distractors' => ['a promise']], ['javascript']],
    ['teacher2', 'javascript', 'ordering', 'easy', 'Order these from smallest to largest scope.', null,
        ['items' => ['Block', 'Function', 'Module', 'Global']], ['javascript', 'syntax']],
    ['teacher2', 'javascript', 'text', 'medium', 'Explain the difference between `==` and `===`.', null,
        ['max_length' => 500, 'reference' => '`===` compares without type coercion; `==` converts operands first.'], ['javascript', 'types']],

    // ── English (teacher2) ────────────────────────────────────────────────────
    ['teacher2', 'english-grammar', 'single_choice', 'easy', 'Choose the correct form: She ___ to school every day.', null,
        ['options' => ['go', 'goes', 'going', 'gone'], 'correct' => 1], ['grammar', 'present-simple']],
    ['teacher2', 'english-grammar', 'single_choice', 'medium', 'Choose the correct form: If I ___ you, I would study more.', 'Second conditional uses "were" for all persons.',
        ['options' => ['am', 'was', 'were', 'be'], 'correct' => 2], ['grammar', 'conditionals']],
    ['teacher2', 'english-grammar', 'true_false', 'easy', '"Their", "there" and "they\'re" are pronounced the same.', null,
        ['correct' => true], ['vocabulary']],
    ['teacher2', 'english-grammar', 'matching', 'medium', 'შეუსაბამეთ ინგლისური სიტყვა თარგმანს.', null,
        ['pairs' => [['apple', 'ვაშლი'], ['mountain', 'მთა'], ['river', 'მდინარე']], 'distractors' => ['ზღვა']], ['vocabulary']],
    ['teacher2', 'english-grammar', 'ordering', 'medium', 'Put the words in order: "always / she / early / arrives".', null,
        ['items' => ['She', 'always', 'arrives', 'early']], ['grammar']],
    ['teacher2', 'english-grammar', 'multiple_choice', 'hard', 'Which of these verbs are irregular?', null,
        ['options' => ['go', 'walk', 'write', 'play', 'think'], 'correct' => [0, 2, 4], 'scoring' => 'partial'], ['grammar', 'vocabulary']],
    ['teacher2', 'english-grammar', 'text', 'hard', 'Write two sentences using the present perfect.', null,
        ['max_length' => 400, 'reference' => 'e.g. "I have visited Batumi twice." "She has just finished her homework."'], ['grammar', 'present-perfect']],
    ['teacher2', 'english-grammar', 'numeric', 'easy', 'How many letters are there in the English alphabet?', null,
        ['correct' => '26', 'tolerance' => '0', 'unit' => null], ['vocabulary']],
    ['teacher2', 'english-grammar', 'single_choice', 'hard', 'Choose the correct form: By next year, they ___ the bridge.', 'Future perfect: will have + past participle.',
        ['options' => ['will finish', 'will have finished', 'finished', 'are finishing'], 'correct' => 1], ['grammar']],

    // ── Extra programming practice (teacher2) ─────────────────────────────────
    ['teacher2', 'programming', 'single_choice', 'medium', 'What does HTTP stand for?', null,
        ['options' => ['HyperText Transfer Protocol', 'High Transfer Text Protocol', 'Hyperlink Text Transport Process', 'Home Tool Transfer Protocol'], 'correct' => 0], ['http']],
    ['teacher2', 'programming', 'true_false', 'hard', 'A unique index in MySQL allows multiple NULL values.', 'NULL is never equal to NULL, so unique indexes accept several NULLs.',
        ['correct' => true], ['databases']],
    ['teacher2', 'programming', 'numeric', 'hard', 'How many bits are in a byte?', null,
        ['correct' => '8', 'tolerance' => '0', 'unit' => 'bits'], ['basics']],
    ['teacher2', 'programming', 'multiple_choice', 'easy', 'Which of these are version-control systems?', null,
        ['options' => ['Git', 'Docker', 'Mercurial', 'Nginx'], 'correct' => [0, 2], 'scoring' => 'all_or_nothing'], ['tools']],
];
