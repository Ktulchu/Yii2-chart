<?php

declare(strict_types=1);

// Dependency-free PHP compatibility smoke checks. Not a substitute for Yii/MySQL integration tests.
namespace yii\base {
    class InvalidConfigException extends \RuntimeException {}
    class Component
    {
        public function __construct(array $config = [])
        {
            foreach ($config as $name => $value) {
                $this->$name = $value;
            }
            $this->init();
        }
        public function init() {}
    }
    class Widget extends Component
    {
        private static $nextId = 0;
        private $id;
        public function getId()
        {
            if ($this->id === null) {
                $this->id = 'w' . self::$nextId++;
            }
            return $this->id;
        }
        public function getView()
        {
            return $GLOBALS['testView'];
        }
    }
}

namespace yii\db {
    class Expression
    {
        public $expression;
        public function __construct($expression) { $this->expression = $expression; }
    }
}

namespace yii\web {
    class AssetBundle
    {
        public static function register($view) {}
    }
    class View
    {
        public const POS_READY = 3;
        public $scripts = [];
        public function registerJs($code, $position = null, $key = null)
        {
            $this->scripts[$key] = $code;
        }
    }
}

namespace yii\helpers {
    class Html
    {
        public static function tag($name, $content, $options)
        {
            return '<' . $name . ' id="' . $options['id'] . '" style="' . $options['style'] . '">' . $content . '</' . $name . '>';
        }
    }
    class Json
    {
        public static function htmlEncode($value)
        {
            return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        }
    }
}

namespace chartTests {
    use rkdev\chart\Chart;
    use rkdev\chart\Chartwidget;
    use yii\base\InvalidConfigException;

    require dirname(__DIR__) . '/src/Chart.php';
    require dirname(__DIR__) . '/src/ChartAssetsBundle.php';
    require dirname(__DIR__) . '/src/Chartwidget.php';

    date_default_timezone_set('UTC');
    error_reporting(E_ALL);
    set_error_handler(static function ($level, $message) { throw new \ErrorException($message, 0, $level); });

    function check($condition, $message)
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    class Query
    {
        public static $rows = [];
        public static $last;
        public $selected;
        public $where;
        public $extra = [];
        public function __construct() { self::$last = $this; }
        public function select($value) { $this->selected = $value; return $this; }
        public function where($value) { $this->where = $value; return $this; }
        public function groupBy($value) { return $this; }
        public function indexBy($value) { return $this; }
        public function asArray() { return $this; }
        public function addSelect($value) { $this->extra = $value; return $this; }
        public function all() { return self::$rows; }
    }
    class Record
    {
        public static function find() { return new Query(); }
    }

    Query::$rows = [
        '2022-06-20' => ['total' => '4', 'total_poprequests' => '2'],
        '2022-06-26' => ['total' => '7', 'total_poprequests' => '5'],
    ];
    $config = ['modelName' => Record::class, 'start' => '22.06.2022', 'nameAttribute' => 'request'];
    $week = new Chart($config);
    check($week->start === strtotime('2022-06-20 00:00:00'), 'Week must start on Monday');
    check($week->end === strtotime('2022-06-26 23:59:59'), 'Week must end on Sunday');
    $result = $week->getChart();
    check(count($result['total']['data']) === 7, 'Week must contain seven days');
    check($result['xaxis'][0] === [0, 'Mon'], 'Week must start with Monday label');
    check($result['total']['data'][0] === [0, 4], 'Monday total');
    check($result['poprequests']['data'][6] === [6, 5], 'Sunday named count');
    check($result['total']['data'][3] === [3, 0], 'Missing day must be zero-filled');
    check(isset(Query::$last->extra['total_poprequests']), 'Named attribute must be counted');
    check(Query::$last->where === ['between', 'created_at', $week->start, $week->end], 'SQL date bounds');

    Query::$rows = ['2024-02-29' => ['total' => 10]];
    $month = new Chart(['modelName' => Record::class, 'range' => 'month', 'start' => '2024-02-15']);
    $result = $month->getChart();
    check($month->start === strtotime('2024-02-01 00:00:00'), 'Month start');
    check($month->end === strtotime('2024-02-29 23:59:59'), 'Leap year month end');
    check(count($result['total']['data']) === 29, 'Leap February must contain 29 days');
    check($result['total']['data'][28] === [29, 10], 'Last day maps to correct day index');
    check($result['poprequests']['data'][28] === [29, 0], 'Absent attribute means zero second series');

    Query::$rows = ['2024-02' => ['total' => 23, 'total_poprequests' => 7]];
    $year = new Chart(['modelName' => Record::class, 'range' => 'year', 'start' => 1710500000, 'nameAttribute' => 'request']);
    $result = $year->getChart();
    check($year->start === strtotime('2024-01-01 00:00:00'), 'Year start');
    check(count($result['total']['data']) === 12, 'Year must contain twelve months');
    check($result['total']['data'][1] === [2, 23], 'February total');
    check($result['poprequests']['data'][1] === [2, 7], 'February named count');
    check($result['xaxis'][11] === [12, 'Dec'], 'Year ends with December');

    Query::$rows = [];
    $empty = (new Chart(['modelName' => Record::class, 'start' => '2024-08-01']))->getChart();
    check(count($empty['total']['data']) === 7, 'Empty query still gets a full week');
    check($empty['total']['data'][0][1] === 0, 'Empty totals are zero');

    foreach (['nonsense-date', [], new \stdClass()] as $invalid) {
        try {
            new Chart(['modelName' => Record::class, 'start' => $invalid]);
            throw new \RuntimeException('Invalid date was accepted');
        } catch (InvalidConfigException $e) {
            // Expected.
        }
    }

    $GLOBALS['testView'] = new \yii\web\View();
    $firstWidget = new Chartwidget(['options' => $config]);
    $secondWidget = new Chartwidget(['options' => $config]);
    $htmlA = $firstWidget->run();
    $htmlB = $secondWidget->run();
    check($htmlA !== $htmlB, 'Each chart must have a unique DOM id');
    check(count($GLOBALS['testView']->scripts) === 2, 'Both chart scripts must be registered');
    foreach ($GLOBALS['testView']->scripts as $id => $script) {
        check(strpos($script, 'getElementById("' . $id . '")') !== false, 'Script must reference its own chart');
    }

    echo "OK: PHP chart smoke checks (week, month, year, empty data, invalid dates, multiple widgets)\n";
}
