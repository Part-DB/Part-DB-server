<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\FuncCall\CountArrayToEmptyArrayComparisonRector;
use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
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
    ]);

    //Do not apply rules to Symfony own files
    $rectorConfig->skip([
        __DIR__ . '/public/index.php',
        __DIR__ . '/src/Kernel.php',
        __DIR__ . '/config/preload.php',
        __DIR__ . '/config/bundles.php',
    ]);
};
