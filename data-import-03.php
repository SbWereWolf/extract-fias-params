<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use SbWereWolf\FiasGarDataImport\Cli\ImportCommand;
use SbWereWolf\FiasGarDataImport\Cli\ImportOptions;
use SbWereWolf\FiasGarDataImport\Import\Processor\AddHouseTypes;
use SbWereWolf\FiasGarDataImport\Import\Processor\AddressObjectDivision;
use SbWereWolf\FiasGarDataImport\Import\Processor\AddressObjectParams;
use SbWereWolf\FiasGarDataImport\Import\Processor\AddressObjects;
use SbWereWolf\FiasGarDataImport\Import\Processor\AddressObjectTypes;
use SbWereWolf\FiasGarDataImport\Import\Processor\AdministrativeHierarchy;
use SbWereWolf\FiasGarDataImport\Import\Processor\Apartments;
use SbWereWolf\FiasGarDataImport\Import\Processor\ApartmentsParams;
use SbWereWolf\FiasGarDataImport\Import\Processor\ApartmentTypes;
use SbWereWolf\FiasGarDataImport\Import\Processor\CarPlaces;
use SbWereWolf\FiasGarDataImport\Import\Processor\CarPlacesParams;
use SbWereWolf\FiasGarDataImport\Import\Processor\ChangeHistory;
use SbWereWolf\FiasGarDataImport\Import\Processor\Houses;
use SbWereWolf\FiasGarDataImport\Import\Processor\HousesParams;
use SbWereWolf\FiasGarDataImport\Import\Processor\HouseTypes;
use SbWereWolf\FiasGarDataImport\Import\Processor\MunicipalHierarchy;
use SbWereWolf\FiasGarDataImport\Import\Processor\NormativeDocuments;
use SbWereWolf\FiasGarDataImport\Import\Processor\NormativeDocumentsKinds;
use SbWereWolf\FiasGarDataImport\Import\Processor\NormativeDocumentsTypes;
use SbWereWolf\FiasGarDataImport\Import\Processor\ObjectLevels;
use SbWereWolf\FiasGarDataImport\Import\Processor\OperationTypes;
use SbWereWolf\FiasGarDataImport\Import\Processor\ParamTypes;
use SbWereWolf\FiasGarDataImport\Import\Processor\ReestrObjects;
use SbWereWolf\FiasGarDataImport\Import\Processor\Rooms;
use SbWereWolf\FiasGarDataImport\Import\Processor\RoomsParams;
use SbWereWolf\FiasGarDataImport\Import\Processor\RoomTypes;
use SbWereWolf\FiasGarDataImport\Import\Processor\Steads;
use SbWereWolf\FiasGarDataImport\Import\Processor\SteadsParams;
use SbWereWolf\Scripting\Config\EnvReader;
use SbWereWolf\Scripting\Convert\DurationPrinter;
use SbWereWolf\Scripting\FileSystem\Path;

$startMoment = hrtime(true);

$message = 'Script is starting';
echo $message . PHP_EOL;

$pathParts = [__DIR__, 'vendor', 'autoload.php'];
$autoloaderPath = join(DIRECTORY_SEPARATOR, $pathParts);
require_once($autoloaderPath);

$logger = new Logger('common');

$pathComposer = new Path(__DIR__);
$logsPath = $pathComposer->make(
    [
        'logs',
        pathinfo(__FILE__, PATHINFO_FILENAME) . '-' . time() . '.log',
    ]
);

$writeHandler = new StreamHandler($logsPath);
$logger->pushHandler($writeHandler);

$logger->pushProcessor(function ($record) {
    /** @var LogRecord $record */
    echo "{$record->datetime} {$record->message}" . PHP_EOL;

    return $record;
});

$logger->notice($message);

$configPath = $pathComposer->make(['config.env']);
(new EnvReader($configPath))->defineConstants();

$connection = (new PDO(
    constant('DSN'),
    constant('LOGIN'),
    constant('PASSWORD'),
));
$schema = constant('SCHEMA');
$connection->exec("SET search_path TO {$schema}");

$directory = constant('XML_FILES_PATH');
$command = new ImportCommand($connection, $logger, $directory);

$doAddNewWithCheck = constant('DO_IMPORT_WITH_CHECK') !== 'FALSE';
$options = new ImportOptions(
    $doAddNewWithCheck,
    [ ],
    '{59,60,61,62,63,64,65,66,67,68,69,70,71,72,73,74,75,76,77,78,79,80,81,82,83,84,85,86,87,88,89,90,91,92,93,94,95,99}',
    [
        AddressObjects::class =>
            'AS_ADDR_OBJ_20*.{x,X}{m,M}{l,L}',
        AddressObjectParams::class =>
            'AS_ADDR_OBJ_PARAMS_20*.{x,X}{m,M}{l,L}',
        AdministrativeHierarchy::class =>
            'AS_ADM_HIERARCHY_20*.{x,X}{m,M}{l,L}',
        HousesParams::class =>
            'AS_HOUSES_PARAMS_20*.{x,X}{m,M}{l,L}',
        MunicipalHierarchy::class =>
            'AS_MUN_HIERARCHY_20*.{x,X}{m,M}{l,L}',
    ],
);

$rowsRead = 0;
$successOperations = 0;
$commitPortion = (int)constant('BATCH_SIZE');
$formatted = number_format($commitPortion, 0, ',', ' ');

$message =
    "Run import FIAS GAR files from `$directory`," .
    " with commit each `$formatted` operations";
$logger->notice($message);

$start = hrtime(true);
$connection->beginTransaction();
foreach (
    $command->run($options, $commitPortion) as $isSuccess
) {
    $rowsRead++;
    if ($isSuccess) {
        $successOperations++;
    }

    $letCommit = $successOperations % $commitPortion == 0;
    if ($letCommit) {
        $connection->commit();

        $finish = hrtime(true);
        $duration = $finish - $start;

        $timeParts = new DurationPrinter();
        $printout = $timeParts->printNanoseconds($duration);
        $formatted = number_format($rowsRead, 0, ',', ' ');
        $scriptAllocated = memory_get_usage(true) / 1024 / 1024;

        $message =
            "Batch duration is $printout," .
            " rows was read is `$formatted`," .
            " mem allocated is `$scriptAllocated`Mb," .
            " import processing with `$successOperations`" .
            " success Operations";
        $logger->info($message);

        $start = hrtime(true);
        $connection->beginTransaction();
    }
}

$hasTransaction = $connection->inTransaction();
if ($hasTransaction) {
    $connection->commit();

    $finish = hrtime(true);
    $duration = $finish - $start;

    $timeParts = new DurationPrinter();
    $printout = $timeParts->printNanoseconds($duration);
    $formatted = number_format($rowsRead, 0, ',', ' ');
    $scriptAllocated = memory_get_usage(true) / 1024 / 1024;

    $message =
        "Batch duration is $printout," .
        " rows was read is `$formatted`," .
        " mem allocated is `$scriptAllocated`Mb," .
        " import processing with `$successOperations`" .
        " success Operations";
    $logger->info($message);
}

$formatted = number_format($rowsRead, 0, ',', ' ');
$scriptMaxMem =
    round(memory_get_peak_usage(true) / 1024 / 1024, 1);

$message =
    "Rows was read is `$formatted`," .
    " import was processed with `$successOperations`" .
    " success operations," .
    " max mem allocated is `$scriptMaxMem`Mb";
$logger->notice($message);

$finishMoment = hrtime(true);

$totalTime = $finishMoment - $startMoment;
$timeParts = new DurationPrinter();
$printout = $timeParts->printNanoseconds($totalTime);

$message = "Import duration is $printout";
$logger->notice($message);

$message = 'Script is finished';
$logger->notice($message);

$logger->close();
