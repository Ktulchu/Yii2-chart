<?php
declare(strict_types=1);

namespace yii\base {
    class InvalidConfigException extends \RuntimeException {}
    class Component {
        public function __construct(array $config = []) {
            foreach ($config as $name => $value) { $this->$name = $value; }
            $this->init();
        }
        public function init() {}
    }
    class Widget extends Component {
        private static $counter = 0;
        private $id;
        public function getId() { return $this->id ??= 'w' . self::$counter++; }
        public function getView() { return $GLOBALS['chartTestView']; }
    }
}
namespace yii\db {
    class Expression {
        public $expression;
        public function __construct($value) { $this->expression = $value; }
    }
    class ActiveRecord {}
}
namespace yii\web {
    class AssetBundle { public static function register($view) {} }
    class View {
        public const POS_READY = 3;
        public $scripts = [];
        public function registerJs($code, $position = null, $key = null) { $this->scripts[$key] = $code; }
    }
}
namespace yii\helpers {
    class Html {
        public static function tag($name, $content, $options) {
            $attrs = '';
            foreach ($options as $key => $value) {
                $attrs .= ' ' . $key . '="' . htmlspecialchars((string) $value, ENT_QUOTES) . '"';
            }
            return '<' . $name . $attrs . '>' . $content . '</' . $name . '>';
        }
    }
    class Json {
        public static function htmlEncode($data) {
            return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        }
    }
}
namespace chartTests {
    use Ktulchu\Yii2Chart\Chart;
    use Ktulchu\Yii2Chart\Chartwidget;
    use yii\base\InvalidConfigException;
    use yii\db\ActiveRecord;

    require dirname(__DIR__) . '/src/Chart.php';
    require dirname(__DIR__) . '/src/ChartAssetsBundle.php';
    require dirname(__DIR__) . '/src/Chartwidget.php';
    date_default_timezone_set('UTC');
    error_reporting(E_ALL);
    set_error_handler(static function ($level, $message) { throw new \ErrorException($message, 0, $level); });

    function check($condition, $message): void {
        if (!$condition) { throw new \RuntimeException($message); }
    }
    class Column {
        public $type;
        public function __construct($type) { $this->type = $type; }
    }
    class Schema {
        public function getColumn($name) {
            $types = ['created_at' => 'integer', 'logged_at' => 'datetime', 'amount' => 'decimal', 'request' => 'string'];
            return isset($types[$name]) ? new Column($types[$name]) : null;
        }
    }
    class Db {
        public $driverName = 'mysql';
        public function quoteColumnName($name) {
            $q = $this->driverName === 'mysql' ? chr(96) : '"';
            return $q . $name . $q;
        }
    }
    class Query {
        public static $rows = [];
        public static $last;
        public $select;
        public $where;
        public $andWhere;
        public $extra;
        public function __construct() { self::$last = $this; }
        public function select($arg) { $this->select = $arg; return $this; }
        public function where($arg) { $this->where = $arg; return $this; }
        public function andWhere($arg) { $this->andWhere = $arg; return $this; }
        public function groupBy($arg) { return $this; }
        public function orderBy($arg) { return $this; }
        public function indexBy($arg) { return $this; }
        public function asArray() { return $this; }
        public function addSelect($arg) { $this->extra = $arg; return $this; }
        public function all() { return self::$rows; }
    }
    class Record extends ActiveRecord {
        public static $db;
        public static function find() { return new Query(); }
        public static function getDb() { return self::$db; }
        public static function getTableSchema() { return new Schema(); }
    }
    Record::$db = new Db();

    Query::$rows = [
        '2026-11-16 00' => ['metric' => '2', 'named_count' => '1'],
        '2026-11-16 23' => ['metric' => '7', 'named_count' => '6'],
    ];
    $day = (new Chart([
        'modelName' => Record::class, 'fieldName' => 'created_at',
        'range' => 'day', 'start' => '2026-11-16', 'nameAttribute' => 'request',
    ]))->getChart();
    check(count($day['labels']) === 24 && $day['labels'][23] === '23:00', '24 hourly labels');
    check($day['datasets'][0]['data'][0] === 2 && $day['datasets'][0]['data'][1] === 0, 'hour counts and gaps');
    check($day['datasets'][1]['data'][23] === 6, 'optional second series');
    check(Query::$last->where === ['>=', 'created_at', strtotime('2026-11-16')], 'inclusive lower bound');
    check(Query::$last->andWhere === ['<', 'created_at', strtotime('2026-11-17')], 'exclusive upper bound');
    check(strpos(Query::$last->select['bucket']->expression, 'DATE_FORMAT(FROM_UNIXTIME(') !== false, 'MySQL epoch bucket');

    Query::$rows = ['2026-11-16' => ['metric' => '15.50'], '2026-11-22' => ['metric' => '50.25']];
    $sum = (new Chart([
        'modelName' => Record::class, 'fieldName' => 'logged_at', 'start' => '2026-11-18',
        'range' => 'week', 'aggregate' => 'sum', 'valueAttribute' => 'amount',
    ]))->getChart();
    check(count($sum['labels']) === 7 && $sum['labels'][0] === 'Mon', 'seven weekday buckets');
    check($sum['datasets'][0]['data'] === [15.5, 0.0, 0.0, 0.0, 0.0, 0.0, 50.25], 'SUM and zero fill');
    check(strpos(Query::$last->select['metric']->expression, 'SUM(') === 0, 'SUM aggregate');
    check(Query::$last->where === ['>=', 'logged_at', '2026-11-16 00:00:00'], 'datetime bound');
    check(Query::$last->andWhere === ['<', 'logged_at', '2026-11-23 00:00:00'], 'datetime exclusive bound');
    check(strpos(Query::$last->select['bucket']->expression, 'FROM_UNIXTIME') === false, 'datetime direct bucket');

    Query::$rows = ['2024-02-29' => ['metric' => '9']];
    $february = (new Chart(['modelName' => Record::class, 'range' => 'month', 'start' => '2024-02-15']))->getChart();
    check(count($february['labels']) === 29 && $february['datasets'][0]['data'][28] === 9, 'leap year');

    Record::$db->driverName = 'pgsql';
    Query::$rows = ['2024-02' => ['metric' => '22.6']];
    $year = (new Chart([
        'modelName' => Record::class, 'range' => 'year', 'start' => '2024-08-01',
        'aggregate' => 'avg', 'valueAttribute' => 'amount',
    ]))->getChart();
    check(count($year['labels']) === 12 && $year['datasets'][0]['data'][1] === 22.6, 'annual averages');
    check(strpos(Query::$last->select['bucket']->expression, 'TO_CHAR(TO_TIMESTAMP(') !== false, 'PostgreSQL epoch grouping');
    check(strpos(Query::$last->select['metric']->expression, 'AVG(') === 0, 'PostgreSQL avg');

    Query::$rows = [];
    $empty = (new Chart(['modelName' => Record::class, 'range' => 'week']))->getChart();
    check(array_sum($empty['datasets'][0]['data']) === 0, 'empty results');

    foreach ([
        ['range' => 'decade'], ['aggregate' => 'random'], ['aggregate' => 'sum'],
        ['fieldName' => 'created_at);DROP TABLE users;'],
        ['valueAttribute' => 'bad', 'aggregate' => 'sum'],
        ['nameAttribute' => 'unknown'], ['start' => '2026-02-30'],
    ] as $invalid) {
        try {
            new Chart(['modelName' => Record::class] + $invalid);
            throw new \RuntimeException('Invalid config accepted: ' . json_encode($invalid));
        } catch (InvalidConfigException $expected) {}
    }

    $GLOBALS['chartTestView'] = new \yii\web\View();
    $a = new Chartwidget(['options' => ['modelName' => Record::class], 'type' => 'line']);
    $b = new Chartwidget(['options' => ['modelName' => Record::class]]);
    check($a->run() !== $b->run(), 'unique chart ids');
    check(count($GLOBALS['chartTestView']->scripts) === 2, 'both scripts registered');
    foreach ($GLOBALS['chartTestView']->scripts as $id => $code) {
        check(strpos($code, 'getElementById("' . $id . '")') !== false, 'unique JS target');
        check(strpos($code, 'new window.Chart(') !== false, 'Chart.js');
    }
    echo "OK: PHP chart smoke tests\n";
}
