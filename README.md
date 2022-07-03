ChartWidget
===========
bar charts

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist  ktulchu/yii2-chart "*"
```

or add

```
"rkdev/yii2-chart": "*"
```

to the require section of your `composer.json` file.


Usage
-----

Usage example  :

```php
<?= \rkdev\chart\Chartwidget::widget([
	'options'=> [
		'modelName' => 'common\models\Accsesslog',  // Model ActiveRecord
		'created_at' => 'datetime', // property inmodel ActiveRecord for range by day : week : month (intenger)
		'start' =>  '22.06.2022'  // date from the interval (date : string : intenger)
	]
]); ?>

Плпнируемые обновления 
-----
 * Добавление свойства для общей выборки сейчас учитываются все записи
 * Добавление второго свойсива для визуализации сравнения
 * Добавления своего массива для вывода без учета ActiveRecord
 * Добавление поля ActiveForm в виджет для выбора диапазона;
 
 
