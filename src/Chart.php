<?php

namespace rkdev\chart;

use DateTimeImmutable;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\db\Expression;

class Chart extends Component
{
    /** @var int|string|null Start date or Unix timestamp. */
    public $start;

    /** @var int|string|null End date or Unix timestamp. */
    public $end;

    /** @var string week, month, or year. */
    public $range = 'week';

    /** @var string ActiveRecord class. */
    public $modelName;

    /** @var string|null Optional attribute counted when non-null. */
    public $nameAttribute;

    /** @var string Column containing Unix timestamps. */
    public $created_at = 'created_at';

    public function init()
    {
        parent::init();

        if (!$this->modelName) {
            throw new InvalidConfigException('You must specify a class for the working model');
        }
        if (!in_array($this->range, ['week', 'month', 'year'], true)) {
            throw new InvalidConfigException('The range must be week, month, or year');
        }

        $date = (new DateTimeImmutable())
            ->setTimestamp($this->parseTimestamp($this->start))
            ->setTime(0, 0, 0);

        switch ($this->range) {
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
        $this->end = $this->end === null
            ? $next->getTimestamp() - 1
            : $this->parseTimestamp($this->end);

        if ($this->end < $this->start) {
            throw new InvalidConfigException('End date must not precede start date');
        }
    }

    private function parseTimestamp($value)
    {
        if ($value === null) {
            return time();
        }
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }
        if (!is_string($value) || ($timestamp = strtotime($value)) === false) {
            throw new InvalidConfigException('Invalid chart date');
        }

        return $timestamp;
    }

    public function getChart()
    {
        // The underlying ActiveRecord column contains Unix timestamps (MySQL).
        $format = $this->range === 'year' ? '%Y-%m' : '%Y-%m-%d';
        $modelName = $this->modelName;
        $query = $modelName::find()
            ->select([
                'pop_date' => new Expression('FROM_UNIXTIME(' . $this->created_at . ', "' . $format . '")'),
                'total' => new Expression('COUNT(*)'),
            ])
            ->where(['between', $this->created_at, $this->start, $this->end])
            ->groupBy('pop_date')
            ->indexBy('pop_date')
            ->asArray();

        if ($this->nameAttribute) {
            $query->addSelect([
                'total_poprequests' => new Expression('COUNT(' . $this->nameAttribute . ')'),
            ]);
        }

        $data = $query->all();
        $result = [
            'total' => ['data' => []],
            'poprequests' => ['data' => []],
            'xaxis' => [],
        ];

        $first = (new DateTimeImmutable())->setTimestamp($this->start);
        if ($this->range === 'week') {
            $length = 7;
        } elseif ($this->range === 'month') {
            $length = (int) $first->format('t');
        } else {
            $length = 12;
        }

        for ($i = 0; $i < $length; $i++) {
            $date = $this->range === 'year'
                ? $first->modify('+' . $i . ' months')
                : $first->modify('+' . $i . ' days');
            $key = $date->format($this->range === 'year' ? 'Y-m' : 'Y-m-d');
            $x = $this->range === 'week' ? $i : $i + 1;
            $label = $this->range === 'week' ? $date->format('D')
                : ($this->range === 'month' ? $date->format('d') : $date->format('M'));
            $row = $data[$key] ?? [];

            $result['total']['data'][] = [$x, (int) ($row['total'] ?? 0)];
            $result['poprequests']['data'][] = [$x, (int) ($row['total_poprequests'] ?? 0)];
            $result['xaxis'][] = [$x, $label];
        }

        return $result;
    }
}
