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
    [
        AddHouseTypes::class =>
            'AS_ADDHOUSE_TYPES_20*.{x,X}{m,M}{l,L}',
        AddressObjectTypes::class =>
            'AS_ADDR_OBJ_TYPES_20*.{x,X}{m,M}{l,L}',
        ApartmentTypes::class =>
            'AS_APARTMENT_TYPES_20*.{x,X}{m,M}{l,L}',
        HouseTypes::class =>
            'AS_HOUSE_TYPES_20*.{x,X}{m,M}{l,L}',
        NormativeDocumentsKinds::class =>
            'AS_NORMATIVE_DOCS_KINDS_20*.{x,X}{m,M}{l,L}',
        NormativeDocumentsTypes::class =>
            'AS_NORMATIVE_DOCS_TYPES_20*.{x,X}{m,M}{l,L}',
        ObjectLevels::class =>
            'AS_OBJECT_LEVELS_20*.{x,X}{m,M}{l,L}',
        OperationTypes::class =>
            'AS_OPERATION_TYPES_20*.{x,X}{m,M}{l,L}',
        ParamTypes::class =>
            'AS_PARAM_TYPES_20*.{x,X}{m,M}{l,L}',
        RoomTypes::class =>
            'AS_ROOM_TYPES_20*.{x,X}{m,M}{l,L}',
    ],
    '{01,02,03,04,05,06,07,08,09,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33}',
    [
        AddressObjects::class =>
            'AS_ADDR_OBJ_20*.{x,X}{m,M}{l,L}',
        AddressObjectParams::class =>
            'AS_ADDR_OBJ_PARAMS_20*.{x,X}{m,M}{l,L}',
        AdministrativeHierarchy::class =>
            'AS_ADM_HIERARCHY_20*.{x,X}{m,M}{l,L}',
        HousesParams::class =>
            'AS_HOUSES_PARAMS_20*.{x,X}{m,M}{l,L}',
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
