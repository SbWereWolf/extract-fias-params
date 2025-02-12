<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use SbWereWolf\FiasGarSchemaDeploy\Cli\ExecuteSqlFromTemplatesCommand;
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

$logger->notice("Starting creation indexes for all tables");

$connection->beginTransaction();

$templatesPath = $pathComposer->make(['template']);
$command = new ExecuteSqlFromTemplatesCommand(
    $connection,
    $logger,
    $templatesPath,
);

$templatesKitList = [
    'ADDR_OBJ_PARAMS',
    'ADDRESSOBJECTS',
    'ADM_HIERARCHY',
    'HOUSES_PARAMS',
    'MUN_HIERARCHY',
];
$command->run($templatesKitList, 'create-index.php');

$connection->commit();

$finishMoment = hrtime(true);
$totalTime = $finishMoment - $startMoment;

$timeParts = new DurationPrinter();
$printout = $timeParts->printNanoseconds($totalTime);
$logger->notice("Creation index duration is $printout");

$logger->notice('Script is finished');

$logger->close();
