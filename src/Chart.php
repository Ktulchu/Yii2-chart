<?php

namespace rkdev\chart;

use DateTimeImmutable;
use InvalidArgumentException;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * Aggregate a single ActiveRecord table into calendar buckets for Chart.js.
 */
class Chart extends Component
{
    /** @var class-string<ActiveRecord> */
    public $modelName;

    /** @var string Date/time column to group by. */
    public $fieldName;

    /** @var string Legacy alias for fieldName. */
    public $created_at = 'created_at';

    /** @var int|string|null Date inside the requested calendar period. */
    public $start;

    /** @var int|string|null Optional inclusive upper bound. */
    public $end;

    /** @var string day, week, month or year. */
    public $range = 'week';

    /** @var string count, sum, avg, min or max. */
    public $aggregate = 'count';

    /** @var string|null Column used by sum, avg, min and max. */
    public $valueAttribute;

    /** @var string|null Optional legacy second series: COUNT(non-null column). */
    public $nameAttribute;

    private $periodEnd;
    private $unixTimestamp = false;

    public function init()
    {
        parent::init();

        if (!is_string($this->modelName) || !is_subclass_of($this->modelName, ActiveRecord::class)) {
            throw new InvalidConfigException('modelName must be an ActiveRecord class');
        }
        if (!in_array($this->range, ['day', 'week', 'month', 'year'], true)) {
            throw new InvalidConfigException('range must be day, week, month or year');
        }
        if (!in_array($this->aggregate, ['count', 'sum', 'avg', 'min', 'max'], true)) {
            throw new InvalidConfigException('Unsupported aggregate');
        }
        if ($this->aggregate !== 'count' && !$this->valueAttribute) {
            throw new InvalidConfigException('valueAttribute is required for ' . $this->aggregate);
        }

        $this->fieldName = $this->fieldName ?? $this->created_at;
        $schema = $this->modelName::getTableSchema();
        if ($schema === null) {
            throw new InvalidConfigException('ActiveRecord table does not exist');
        }
        $column = $schema->getColumn($this->fieldName);
        if ($column === null) {
            throw new InvalidConfigException('Unknown fieldName: ' . $this->fieldName);
        }
        foreach (['valueAttribute', 'nameAttribute'] as $option) {
            if ($this->$option !== null && $schema->getColumn($this->$option) === null) {
                throw new InvalidConfigException('Unknown ' . $option . ': ' . $this->$option);
            }
        }
        $this->unixTimestamp = in_array($column->type, ['integer', 'smallint', 'bigint'], true);

        $date = $this->parseDate($this->start)->setTime(0, 0);
        switch ($this->range) {
            case 'day':
                $next = $date->modify('+1 day');
                break;
            case 'month':
                $date = $date->modify('first day of this month');
                $next = $date->modify('first day of next month');
                break;
            case 'year':
                $date = $date->setDate((int) $date->format('Y'), 1, 1);
                $next = $date->modify('+1 year');
                break;
            default:
                $date = $date->modify('monday this week');
                $next = $date->modify('+1 week');
        }

        $this->start = $date->getTimestamp();
        $this->periodEnd = $next->getTimestamp(); // Exclusive; handles DST and fractional seconds.
        $this->end = $this->end === null
            ? $this->periodEnd - 1
            : min($this->parseDate($this->end)->getTimestamp(), $this->periodEnd - 1);

        if ($this->end < $this->start) {
            throw new InvalidConfigException('end precedes the selected calendar period');
        }
    }

    private function parseDate($value)
    {
        if ($value === null) {
            return new DateTimeImmutable('now');
        }
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (new DateTimeImmutable())->setTimestamp((int) $value);
        }
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidConfigException('Invalid date');
        }

        try {
            $date = new DateTimeImmutable($value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) {
                throw new InvalidArgumentException('Invalid date');
            }
            return $date;
        } catch (\Exception $exception) {
            throw new InvalidConfigException('Invalid date: ' . $value, 0, $exception);
        }
    }

    private function bucketExpression($driver, $column)
    {
        $mysqlFormats = [
            'day' => '%Y-%m-%d %H',
            'week' => '%Y-%m-%d',
            'month' => '%Y-%m-%d',
            'year' => '%Y-%m',
        ];
        $postgresFormats = [
            'day' => 'YYYY-MM-DD HH24',
            'week' => 'YYYY-MM-DD',
            'month' => 'YYYY-MM-DD',
            'year' => 'YYYY-MM',
        ];

        if ($driver === 'mysql') {
            $date = $this->unixTimestamp ? 'FROM_UNIXTIME(' . $column . ')' : $column;
            return 'DATE_FORMAT(' . $date . ", '" . $mysqlFormats[$this->range] . "')";
        }
        if ($driver === 'pgsql') {
            $date = $this->unixTimestamp ? 'TO_TIMESTAMP(' . $column . ')' : $column;
            return 'TO_CHAR(' . $date . ", '" . $postgresFormats[$this->range] . "')";
        }
        throw new InvalidConfigException('Only MySQL/MariaDB and PostgreSQL are supported');
    }

    /**
     * @return array Chart.js-compatible labels and datasets.
     */
    public function getChart()
    {
        $modelName = $this->modelName;
        $db = $modelName::getDb();
        $column = $db->quoteColumnName($this->fieldName);
        $bucket = $this->bucketExpression($db->driverName, $column);
        $metric = $this->aggregate === 'count'
            ? 'COUNT(*)'
            : strtoupper($this->aggregate) . '(' . $db->quoteColumnName($this->valueAttribute) . ')';

        $upperExclusive = min($this->end + 1, $this->periodEnd);
        $from = $this->unixTimestamp ? $this->start : date('Y-m-d H:i:s', $this->start);
        $to = $this->unixTimestamp ? $upperExclusive : date('Y-m-d H:i:s', $upperExclusive);

        $query = $modelName::find()
            ->select([
                'bucket' => new Expression($bucket),
                'metric' => new Expression($metric),
            ])
            ->where(['>=', $this->fieldName, $from])
            ->andWhere(['<', $this->fieldName, $to])
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->indexBy('bucket')
            ->asArray();

        if ($this->nameAttribute !== null) {
            $query->addSelect([
                'named_count' => new Expression('COUNT(' . $db->quoteColumnName($this->nameAttribute) . ')'),
            ]);
        }

        $rows = $query->all();
        $first = (new DateTimeImmutable())->setTimestamp($this->start);
        $length = match ($this->range) {
            'day' => 24,
            'week' => 7,
            'month' => (int) $first->format('t'),
            'year' => 12,
        };

        $labels = [];
        $values = [];
        $namedValues = [];
        for ($i = 0; $i < $length; $i++) {
            if ($this->range === 'day') {
                // A day always has 24 clock-hour buckets, including DST transitions.
                $key = $first->format('Y-m-d') . ' ' . sprintf('%02d', $i);
                $labels[] = sprintf('%02d:00', $i);
            } else {
                $date = $this->range === 'year'
                    ? $first->modify('+' . $i . ' months')
                    : $first->modify('+' . $i . ' days');
                $key = $date->format($this->range === 'year' ? 'Y-m' : 'Y-m-d');
                $labels[] = match ($this->range) {
                    'week' => $date->format('D'),
                    'month' => $date->format('d'),
                    'year' => $date->format('M'),
                };
            }
            $raw = $rows[$key]['metric'] ?? 0;
            $values[] = $this->aggregate === 'count' ? (int) $raw : (float) $raw;
            if ($this->nameAttribute !== null) {
                $namedValues[] = (int) ($rows[$key]['named_count'] ?? 0);
            }
        }

        $result = [
            'labels' => $labels,
            'datasets' => [[
                'label' => $this->aggregate === 'count' ? 'Count' : strtoupper($this->aggregate) . ' ' . $this->valueAttribute,
                'data' => $values,
            ]],
        ];

        if ($this->nameAttribute !== null) {
            $result['datasets'][] = [
                'label' => 'Count ' . $this->nameAttribute,
                'data' => $namedValues,
            ];
        }

        return $result;
    }
}
