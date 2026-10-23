<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Questions\Types\MatchingHandler;
use App\Domain\Questions\Types\MultipleChoiceHandler;
use App\Domain\Questions\Types\NumericHandler;
use App\Domain\Questions\Types\OrderingHandler;
use App\Domain\Questions\Types\QuestionTypeHandler;
use App\Domain\Questions\Types\QuestionTypeRegistry;
use App\Domain\Questions\Types\SingleChoiceHandler;
use App\Domain\Questions\Types\TextHandler;
use App\Domain\Questions\Types\TrueFalseHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the question type handlers ("Synapse modules"). A new question
 * type is added only here (plus its handler class and frontend module).
 */
final class QuestionTypeServiceProvider extends ServiceProvider
{
    /**
     * Handler classes, one per App\Enums\QuestionType case (a test enforces it).
     *
     * @var list<class-string<QuestionTypeHandler>>
     */
    public const array HANDLERS = [
        SingleChoiceHandler::class,
        MultipleChoiceHandler::class,
        TrueFalseHandler::class,
        NumericHandler::class,
        TextHandler::class,
        OrderingHandler::class,
        MatchingHandler::class,
    ];

    public function register(): void
    {
        $this->app->singleton(QuestionTypeRegistry::class, fn (Application $app): QuestionTypeRegistry => new QuestionTypeRegistry(
            array_map(fn (string $handler): QuestionTypeHandler => $app->make($handler), self::HANDLERS),
        ));
    }
}
