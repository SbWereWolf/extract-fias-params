<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use SbWereWolf\FiasGarDataImport\Cli\ImportCommand;
use SbWereWolf\FiasGarDataImport\Cli\ImportOptions;
use SbWereWolf\FiasGarDataImport\Import\Processor\Houses;
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
    [],
    '{74,75,76,77,78,79,80,81,82,83,84,85,86,87,88,89,90,91,92,93,94,95,99}',
    [
        Houses::class =>
            'AS_HOUSES_20*.{x,X}{m,M}{l,L}',
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
