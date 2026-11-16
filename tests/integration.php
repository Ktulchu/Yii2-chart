<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use rkdev\chart\Chart;
use yii\console\Application;
use yii\db\ActiveRecord;

date_default_timezone_set('UTC');
error_reporting(E_ALL);
set_error_handler(static function ($level, $message) {
    throw new ErrorException($message, 0, $level);
});

class ChartTestEvent extends ActiveRecord
{
    public static function tableName()
    {
        return 'chart_events';
    }
}

function checkResult($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$driver = getenv('DB_DRIVER') ?: 'mysql';
if (!in_array($driver, ['mysql', 'pgsql'], true)) {
    throw new InvalidArgumentException('DB_DRIVER must be mysql or pgsql');
}
$dsn = $driver === 'mysql'
    ? 'mysql:host=127.0.0.1;port=3306;dbname=chart_test'
    : 'pgsql:host=127.0.0.1;port=5432;dbname=chart_test';

new Application([
    'id' => 'yii2-chart-integration',
    'basePath' => __DIR__,
    'components' => [
        'db' => [
            'class' => 'yii\db\Connection',
            'dsn' => $dsn,
            'username' => $driver === 'mysql' ? 'root' : 'postgres',
            'password' => 'test',
            'charset' => 'utf8',
        ],
    ],
]);

$db = Yii::$app->db;
$db->open();
if ($driver === 'mysql') {
    $db->createCommand("SET time_zone = '+00:00'")->execute();
} else {
    $db->createCommand("SET TIME ZONE 'UTC'")->execute();
}
$db->createCommand('DROP TABLE IF EXISTS chart_events')->execute();
$db->createCommand($driver === 'mysql'
    ? 'CREATE TABLE chart_events (id INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY, created_at BIGINT NOT NULL, logged_at DATETIME NOT NULL, amount DECIMAL(12,2) NOT NULL, request VARCHAR(64) NULL)'
    : 'CREATE TABLE chart_events (id SERIAL PRIMARY KEY, created_at BIGINT NOT NULL, logged_at TIMESTAMP NOT NULL, amount NUMERIC(12,2) NOT NULL, request VARCHAR(64) NULL)'
)->execute();

try {
    $events = [
        ['2026-11-16 00:15:00', '10.50', 'a'],
        ['2026-11-16 00:45:00', '4.50', null],
        ['2026-11-16 02:00:00', '7.00', 'b'],
        ['2026-11-22 23:59:59', '9.00', 'c'],
        ['2024-02-29 12:30:00', '13.00', 'd'],
        ['2026-12-31 16:00:00', '5.00', 'e'],
        ['2027-01-01 00:00:00', '6.00', 'f'],
    ];
    foreach ($events as [$time, $amount, $request]) {
        $db->createCommand()->insert('chart_events', [
            'created_at' => strtotime($time . ' UTC'),
            'logged_at' => $time,
            'amount' => $amount,
            'request' => $request,
        ])->execute();
    }

    $base = ['modelName' => ChartTestEvent::class, 'fieldName' => 'created_at', 'start' => '2026-11-16'];

    $day = (new Chart($base + ['range' => 'day', 'nameAttribute' => 'request']))->getChart();
    checkResult(count($day['labels']) === 24, 'Day: 24 buckets');
    checkResult($day['datasets'][0]['data'][0] === 2, 'Day: midnight count');
    checkResult($day['datasets'][0]['data'][1] === 0, 'Day: missing hour is zero');
    checkResult($day['datasets'][0]['data'][2] === 1, 'Day: 02:00 count');
    checkResult($day['datasets'][1]['data'][0] === 1, 'Day: non-null request');

    $week = (new Chart($base + ['range' => 'week']))->getChart();
    checkResult($week['datasets'][0]['data'] === [3, 0, 0, 0, 0, 0, 1], 'Week: exact counts');

    $sum = (new Chart(array_replace($base, [
        'range' => 'week', 'fieldName' => 'logged_at',
        'aggregate' => 'sum', 'valueAttribute' => 'amount',
    ])))->getChart();
    checkResult($sum['datasets'][0]['data'] === [22.0, 0.0, 0.0, 0.0, 0.0, 0.0, 9.0], 'SUM for SQL DATETIME');

    foreach (['avg' => 7.5, 'min' => 4.5, 'max' => 10.5] as $aggregate => $expected) {
        $data = (new Chart($base + [
            'range' => 'day', 'aggregate' => $aggregate, 'valueAttribute' => 'amount',
        ]))->getChart();
        checkResult($data['datasets'][0]['data'][0] === $expected, $aggregate . ': first bucket');
    }

    $month = (new Chart([
        'modelName' => ChartTestEvent::class,
        'start' => '2024-02-15', 'range' => 'month',
    ]))->getChart();
    checkResult(count($month['labels']) === 29, 'February leap year has 29 days');
    checkResult($month['datasets'][0]['data'][28] === 1, 'February 29 present');

    $year = (new Chart($base + ['range' => 'year']))->getChart();
    checkResult($year['datasets'][0]['data'][10] === 4, 'November 2026');
    checkResult($year['datasets'][0]['data'][11] === 1, 'December 2026');
    checkResult(array_sum($year['datasets'][0]['data']) === 5, 'Year excludes Jan 2027');

    $legacy = (new Chart([
        'modelName' => ChartTestEvent::class,
        'created_at' => 'logged_at',
        'range' => 'day', 'start' => '2026-11-16',
    ]))->getChart();
    checkResult($legacy['datasets'][0]['data'][0] === 2, 'Legacy created_at alias');

    echo "OK: real Yii2 + " . $driver . " integration\n";
} finally {
    $db->createCommand('DROP TABLE IF EXISTS chart_events')->execute();
}
