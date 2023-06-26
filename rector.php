<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\FuncCall\CountArrayToEmptyArrayComparisonRector;
use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\PHPUnit\Rector\ClassMethod\AddDoesNotPerformAssertionToNonAssertingTestRector;
use Rector\PHPUnit\Set\PHPUnitLevelSetList;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Set\SymfonyLevelSetList;
use Rector\Symfony\Set\SymfonySetList;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;

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
        SymfonyLevelSetList::UP_TO_SYMFONY_62,
        SymfonySetList::SYMFONY_CODE_QUALITY,

        //Doctrine rules
        DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
        DoctrineSetList::DOCTRINE_CODE_QUALITY,

        //PHPUnit rules
        PHPUnitLevelSetList::UP_TO_PHPUNIT_90,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
    ]);

    $rectorConfig->skip([
        AddDoesNotPerformAssertionToNonAssertingTestRector::class,
        CountArrayToEmptyArrayComparisonRector::class,
    ]);
};
