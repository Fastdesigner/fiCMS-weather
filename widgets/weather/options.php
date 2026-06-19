<?php

if (!isset($block)) return [];

require_once dirname(__DIR__,2).'/src/Weather.php';

$weather_options = [
	'entry'=>new \weather\Weather(dirname(__DIR__,2)),
	'options'=>[],
	'values'=>[],
	'datalists'=>[],
	'dependencies'=>['widget'=>['enable'=>['weather'],'disable'=>[]]],
	'locations'=>[],
	'layouts'=>[
		'compact'=>['value'=>'compact','name'=>language__get($GLOBALS['user']['language'],'_weather_widget_layout_compact')],
		'list'=>['value'=>'list','name'=>language__get($GLOBALS['user']['language'],'_weather_widget_layout_list')]
	],
	'fields'=>[
		'weather_location'=>['type'=>'datalist','default'=>'','dynamic_name'=>'_weather_widget_location','attributes'=>['data-list'=>'weather-locations','data-exact'=>'true']],
		'weather_layout'=>['type'=>'select','default'=>'compact','dynamic_name'=>'_weather_widget_layout','options'=>'layouts'],
		'widgetnum'=>['type'=>'number','default'=>3,'dynamic_name'=>'_weather_widget_days','attributes'=>['min'=>1,'max'=>8]],
		'show_current'=>['type'=>'checkbox','default'=>1,'dynamic_name'=>'_weather_widget_show_current'],
		'show_forecast'=>['type'=>'checkbox','default'=>1,'dynamic_name'=>'_weather_widget_show_forecast'],
		'show_description'=>['type'=>'checkbox','default'=>1,'dynamic_name'=>'_weather_widget_show_description'],
		'show_rain_probability'=>['type'=>'checkbox','default'=>1,'dynamic_name'=>'_weather_widget_show_rain_probability'],
		'show_wind'=>['type'=>'checkbox','default'=>0,'dynamic_name'=>'_weather_widget_show_wind'],
		'show_humidity'=>['type'=>'checkbox','default'=>0,'dynamic_name'=>'_weather_widget_show_humidity']
	]
];

foreach ($weather_options['entry']->locations() as $weather_options['location']) {
	$weather_options['locations'][$weather_options['location']['id']] = [
		'value'=>$weather_options['location']['id'],
		'name'=>$weather_options['location']['label'] != '' ? $weather_options['location']['label'] : $weather_options['location']['id']
	];
}
$weather_options['datalists']['weather-locations'] = $weather_options['locations'];

foreach ($weather_options['fields'] as $weather_options['key'] => $weather_options['field']) {
	$weather_options['options'][$weather_options['key']] = [
		'type'=>$weather_options['field']['type'],
		'default'=>$weather_options['field']['default'],
		'dynamic_name'=>$weather_options['field']['dynamic_name'],
		'name'=>language__get($GLOBALS['user']['language'],$weather_options['field']['dynamic_name']),
		'option'=>$weather_options['key'],
		'include'=>true,
		'dependencies'=>$weather_options['dependencies']
	];
	foreach (['attributes'] as $weather_options['property']) if (isset($weather_options['field'][$weather_options['property']])) $weather_options['options'][$weather_options['key']][$weather_options['property']] = $weather_options['field'][$weather_options['property']];
	if (($weather_options['field']['options'] ?? '') == 'layouts') $weather_options['options'][$weather_options['key']]['options'] = array_values($weather_options['layouts']);
}

return ['options'=>$weather_options['options'],'values'=>$weather_options['values'],'datalists'=>$weather_options['datalists']];
