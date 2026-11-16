# Yii2 Chart

A Yii2 widget for bar charts based on Flot.

## Requirements

- PHP 8.0 or newer
- Yii2 2.0.50 or newer (2.0.x series)
- MySQL / MariaDB; the configured date column stores Unix timestamps
- jQuery is loaded by the Yii asset bundle

## Installation

```bash
composer require ktulchu/yii2-chart
```

## Usage

```php
<?= \rkdev\chart\Chartwidget::widget([
    'options' => [
        'modelName' => \app\models\AccessLog::class,
        'created_at' => 'created_at',
        'start' => '2026-11-16',
        'range' => 'week', // week, month, or year
        'nameAttribute' => 'request', // optional: count non-null values
    ],
]); ?>
```

`start` and `end` accept Unix timestamps or date strings. The start is aligned to the beginning of the selected week (Monday), month, or year. Without `end`, the full period is shown. Missing days/months are filled with zeroes.

The first series counts all records; the second counts rows with a non-null `nameAttribute` (or shows zeroes when omitted).

## Compatibility checks

Run `php tests/smoke.php` for dependency-free PHP compatibility smoke tests. These do not replace integration tests with real Yii2, MySQL, and the browser.

## Future ideas

- Filters for the total selection
- Comparison against another attribute
- Direct array input without ActiveRecord
- ActiveForm range selector
