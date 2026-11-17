# Yii2 Chart

A Composer-installable Yii2 widget for ActiveRecord time-series charts (PHP 8+, Chart.js).

## Project background

This widget builds on charting needs and implementation experience from a private commercial Yii2 application. The public repository is a standalone Composer packaging of that work, not a publication of the original application. Its earlier development history remains in private commercial GitLab; public GitHub commits show the independent package's history.

## Install

~~~bash
composer require ktulchu/yii2-chart
~~~

This command becomes available when the package is published on Packagist. Until then, configure its GitHub repository as a Composer VCS source (with access to the private repository) before running Composer.

## Usage

~~~php
use app\models\AccessLog;

echo \rkdev\chart\Chartwidget::widget([
    'options' => [
        'modelName' => AccessLog::class,
        'fieldName' => 'created_at',
        'start' => '2026-11-16',
        'range' => 'day',
        'aggregate' => 'count',
    ],
]);
~~~

Sum payment amounts per day of the selected week:

~~~php
echo \rkdev\chart\Chartwidget::widget([
    'options' => [
        'modelName' => \app\models\Payment::class,
        'fieldName' => 'paid_at',
        'start' => '2026-11-16',
        'range' => 'week',
        'aggregate' => 'sum',
        'valueAttribute' => 'amount',
    ],
    'type' => 'line', // bar (default), line, pie, doughnut
]);
~~~

**One setting, two jobs:** `range` determines both the calendar period and its grouping:

| range | Period containing `start` | Buckets |
| --- | --- | --- |
| `day` | Calendar day | 24 hours |
| `week` | Monday through Sunday | 7 days |
| `month` | Calendar month | 28–31 days |
| `year` | Calendar year | 12 months |

Missing intervals are filled with zeroes. One aggregate SQL `GROUP BY` query fetches the data; there is no per-bucket N+1.

### Options

- `modelName` **(required)**: an `yii\db\ActiveRecord` class.
- `fieldName`: model column with an integer Unix timestamp or SQL DATE/DATETIME/TIMESTAMP; defaults to `created_at`. The legacy `created_at` option remains available.
- `start`: a date in the selected period, as `YYYY-MM-DD` or a Unix timestamp; defaults to current date.
- `range`: `day`, `week`, `month`, `year` (default: `week`).
- `aggregate`: `count` (default), `sum`, `avg`, `min`, `max`.
- `valueAttribute`: a numeric column, required for aggregates other than `count`.
- `nameAttribute`: optional second dataset with a count of non-null values of this column (legacy).
- `end`: optional inclusive timestamp/datetime in the selected calendar period.
- Widget `type`: `bar` (default), `line`, `pie`, `doughnut`; `height` in pixels (default 260).

Requires Yii2 2.0.50+, PHP 8.0+, and MySQL/MariaDB or PostgreSQL. SQL column identifiers are validated against the ActiveRecord table schema. For Unix timestamp fields, PHP and the database session must use the **same time zone** for consistent date grouping (UTC recommended).

The widget loads pinned **Chart.js 4.6.1** UMD from jsDelivr. This requires browser access to the CDN. Override `\rkdev\chart\ChartAssetsBundle` in Yii's asset manager to serve a local copy for offline operation or strict CSP.

## Tests

~~~bash
php tests/smoke.php
~~~

CI also runs real Yii2 integration tests against MySQL 8 and PostgreSQL 16. The dependency-free smoke test does not replace browser-level testing.

## Later

- Query filtering and adapters
- Interactive period selector
- Independent analytics query builder
