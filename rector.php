<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\CodingStyle\Rector\FuncCall\CountArrayToEmptyArrayComparisonRector;
use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertEmptyNullableObjectToAssertInstanceofRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\CodeQuality\Rector\Class_\EventListenerToEventSubscriberRector;
use Rector\Symfony\CodeQuality\Rector\ClassMethod\ActionSuffixRemoverRector;
use Rector\Symfony\CodeQuality\Rector\MethodCall\LiteralGetToRequestClassConstantRector;
use Rector\Symfony\Set\SymfonySetList;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;

return RectorConfig::configure()
    ->withComposerBased(phpunit: true)

    ->withSymfonyContainerPhp(__DIR__ . '/tests/symfony-container.php')
    ->withSymfonyContainerXml(__DIR__ . '/var/cache/dev/App_KernelDevDebugContainer.xml')

    ->withImportNames(importShortClasses: false)
    ->withPaths([
        __DIR__ . '/config',
        __DIR__ . '/public',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])

    ->withSets([
        PHPUnitSetList::ANNOTATIONS_TO_ATTRIBUTES,
        PHPUnitSetList::PHPUNIT_90,
        PHPUnitSetList::PHPUNIT_110,
    ])

    ->withRules([
        DeclareStrictTypesRector::class
    ])

    ->withSkip([
        //Leave our AssertNull tests alone
        AssertEmptyNullableObjectToAssertInstanceofRector::class,


        CountArrayToEmptyArrayComparisonRector::class,
        //Leave our !== null checks alone
        FlipTypeControlToUseExclusiveTypeRector::class,
        //Leave our PartList TableAction alone
        ActionSuffixRemoverRector::class,
        //We declare event listeners via attributes, therefore no need to migrate them to subscribers
        EventListenerToEventSubscriberRector::class,
        PreferPHPUnitThisCallRector::class,
        //Do not replace 'GET' with class constant,
        LiteralGetToRequestClassConstantRector::class,
    ])

    //Do not apply rules to Symfony own files
    ->withSkip([
        __DIR__ . '/public/index.php',
        __DIR__ . '/src/Kernel.php',
        __DIR__ . '/config/preload.php',
        __DIR__ . '/config/bundles.php',
    ])

    ;

/*
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->symfonyContainerXml(__DIR__ . '/var/cache/dev/App_KernelDevDebugContainer.xml');
    $rectorConfig->symfonyContainerPhp(__DIR__ . '/tests/symfony-container.php');

    //Import class names instead of using fully qualified class names
    $rectorConfig->importNames();
    //But keep the fully qualified class names for classes in the global namespace
    $rectorConfig->importShortClasses(false);

    $rectorConfig->paths([
        __DIR__ . '/config',
        __DIR__ . '/public',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    // register a single rule
    //$rectorConfig->rule(InlineConstructorDefaultToPropertyRector::class);

    $rectorConfig->rules([
        DeclareStrictTypesRector::class,
    ]);

    // define sets of rules
    $rectorConfig->sets([
        //PHP rules
        SetList::CODE_QUALITY,
        LevelSetList::UP_TO_PHP_81,

        //Symfony rules
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::SYMFONY_64,

        //Doctrine rules
        DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
        DoctrineSetList::DOCTRINE_CODE_QUALITY,

        //PHPUnit rules
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        PHPUnitSetList::PHPUNIT_90,
    ]);

    $rectorConfig->skip([
        CountArrayToEmptyArrayComparisonRector::class,
        //Leave our !== null checks alone
        FlipTypeControlToUseExclusiveTypeRector::class,
        //Leave our PartList TableAction alone
        ActionSuffixRemoverRector::class,
        //We declare event listeners via attributes, therefore no need to migrate them to subscribers
        EventListenerToEventSubscriberRector::class,
        PreferPHPUnitThisCallRector::class,
        //Do not replace 'GET' with class constant,
        LiteralGetToRequestClassConstantRector::class,
    ]);

    //Do not apply rules to Symfony own files
    $rectorConfig->skip([
        __DIR__ . '/public/index.php',
        __DIR__ . '/src/Kernel.php',
        __DIR__ . '/config/preload.php',
        __DIR__ . '/config/bundles.php',
    ]);
};
*/
