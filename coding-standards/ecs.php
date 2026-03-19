<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\ArrayNotation\ArraySyntaxFixer;
use PhpCsFixer\Fixer\AttributeNotation\OrderedAttributesFixer;
use PhpCsFixer\Fixer\CastNotation\NoShortBoolCastFixer;
use PhpCsFixer\Fixer\ClassNotation\OrderedClassElementsFixer;
use PhpCsFixer\Fixer\ClassNotation\OrderedTraitsFixer;
use PhpCsFixer\Fixer\ClassNotation\ProtectedToPrivateFixer;
use PhpCsFixer\Fixer\ClassUsage\DateTimeImmutableFixer;
use PhpCsFixer\Fixer\ControlStructure\NoUnneededBracesFixer;
use PhpCsFixer\Fixer\ControlStructure\TrailingCommaInMultilineFixer;
use PhpCsFixer\Fixer\FunctionNotation\NativeFunctionInvocationFixer;
use PhpCsFixer\Fixer\FunctionNotation\StaticLambdaFixer;
use PhpCsFixer\Fixer\FunctionNotation\VoidReturnFixer;
use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use PhpCsFixer\Fixer\Import\OrderedImportsFixer;
use PhpCsFixer\Fixer\Operator\ConcatSpaceFixer;
use PhpCsFixer\Fixer\Operator\NewWithParenthesesFixer;
use PhpCsFixer\Fixer\Semicolon\NoEmptyStatementFixer;
use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use Symplify\CodingStandard\Fixer\Commenting\ParamReturnAndVarTagMalformsFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

/** @noinspection PhpUnhandledExceptionInspection */
return ECSConfig::configure()
    ->withPaths(['app', 'bootstrap', 'coding-standards', 'config', 'database', 'routes', 'tests'])
    ->withSkip(['bootstrap/cache'])
    ->withPreparedSets(psr12: true, cleanCode: true)
    ->withConfiguredRule(ArraySyntaxFixer::class, ['syntax' => 'short'])
    ->withConfiguredRule(ConcatSpaceFixer::class, ['spacing' => 'one'])
    ->withConfiguredRule(NativeFunctionInvocationFixer::class, ['include' => ['@all']])
    ->withRules([
        DateTimeImmutableFixer::class,
        DeclareStrictTypesFixer::class,
        NoEmptyStatementFixer::class,
        NoShortBoolCastFixer::class,
        NoUnneededBracesFixer::class,
        NewWithParenthesesFixer::class,
        NoUnusedImportsFixer::class,
        OrderedAttributesFixer::class,
        OrderedClassElementsFixer::class,
        OrderedImportsFixer::class,
        OrderedTraitsFixer::class,
        ParamReturnAndVarTagMalformsFixer::class,
        ProtectedToPrivateFixer::class,
        StaticLambdaFixer::class,
        TrailingCommaInMultilineFixer::class,
        VoidReturnFixer::class,
    ]);
