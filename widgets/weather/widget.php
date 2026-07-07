<?php

if (!$site['onsite']) return;

require_once dirname(__DIR__,2).'/src/Weather.php';

if (!function_exists('weather__widget_row')) {
	function weather__widget_row(array $row, array $options, array $units, bool $current, \weather\Weather $entry): array {
		$icon = preg_replace('/[^a-z0-9]/i','',trim((string) ($row['icon'] ?? '')));
		$date = intval($row['date'] ?? ($_SERVER['now'] ?? time()));
		return [
			'date'=>$date,
			'datetime'=>date('Y-m-d',$date),
			'date_label'=>function_exists('format__date_relative') ? format__date_relative($date,'date',$_SESSION['language'] ?? false) : date('d.m.',$date),
			'temp'=>htmlspecialchars((string) ($row['temp'] ?? 0),ENT_QUOTES,'UTF-8'),
			'temp_now'=>htmlspecialchars((string) ($row['temp_now'] ?? ($row['temp'] ?? 0)),ENT_QUOTES,'UTF-8'),
			'temp_min'=>htmlspecialchars((string) ($row['temp_min'] ?? 0),ENT_QUOTES,'UTF-8'),
			'temp_max'=>htmlspecialchars((string) ($row['temp_max'] ?? 0),ENT_QUOTES,'UTF-8'),
			'temp_unit'=>$units['temp'],
			'wind_unit'=>$units['wind'],
			'wind_speed'=>htmlspecialchars((string) ($row['wind_speed'] ?? 0),ENT_QUOTES,'UTF-8'),
			'humidity'=>htmlspecialchars((string) ($row['humidity'] ?? 0),ENT_QUOTES,'UTF-8'),
			'pop'=>htmlspecialchars((string) ($row['pop'] ?? 0),ENT_QUOTES,'UTF-8'),
			'description'=>htmlspecialchars(trim((string) ($row['description'] ?? '')),ENT_QUOTES,'UTF-8'),
			'icon'=>$icon,
			'icon_url'=>$icon !== '' ? $entry->iconUrl($icon) : '',
			'has_icon'=>$icon !== '' ? 1 : 0,
			'is_current'=>$current ? 1 : 0,
			'render_description'=>$options['show_description'],
			'render_wind'=>$options['show_wind'],
			'render_humidity'=>$options['show_humidity'],
			'render_rain_probability'=>$options['show_rain_probability']
		];
	}
}

$weather = [
	'entry'=>new \weather\Weather(dirname(__DIR__,2)),
	'structure_file'=>widgets__layout_file('weather'),
	'structure'=>[],
	'block'=>isset($service['temp']['data']['block']) && is_array($service['temp']['data']['block']) ? $service['temp']['data']['block'] : [],
	'parts'=>array_values(array_filter(array_map('trim',explode('|',trim((string) ($service['temp']['data']['add'] ?? '')))),fn($value) => $value !== '')),
	'location'=>'',
	'layout'=>'compact',
	'days'=>0,
	'data'=>[],
	'current'=>[],
	'forecast'=>[],
	'replace'=>[
		'layout'=>'compact',
		'location_id'=>'',
		'count'=>0,
		'current'=>'',
		'forecast'=>'',
		'render_current'=>1,
		'render_forecast'=>1
	],
	'options'=>[
		'show_current'=>1,
		'show_forecast'=>1,
		'show_description'=>1,
		'show_wind'=>0,
		'show_humidity'=>0,
		'show_rain_probability'=>1
	],
	'custom_location'=>[]
];

if ($weather['structure_file'] == '') {
	$service['content'] = '';
	unset($weather);
	return;
}

$weather['structure'] = parser__file($weather['structure_file']);
foreach (['current','forecast'] as $weather['section']) if (!isset($weather['structure'][$weather['section']]['inner'])) $weather['structure'][$weather['section']] = ['inner'=>''];

if (isset($weather['block']['option_weather_location'])) $weather['location'] = trim((string) $weather['block']['option_weather_location']);
if ($weather['location'] == '' && isset($weather['parts'][0])) $weather['location'] = $weather['parts'][0];
if (isset($weather['block']['option_weather_layout'])) $weather['layout'] = trim((string) $weather['block']['option_weather_layout']);
elseif (isset($weather['parts'][1]) && !is_numeric($weather['parts'][1])) $weather['layout'] = $weather['parts'][1];
if (!in_array($weather['layout'],['compact','list'],true)) $weather['layout'] = 'compact';
if (isset($weather['block']['option_widgetnum']) && intval($weather['block']['option_widgetnum']) > 0) $weather['days'] = intval($weather['block']['option_widgetnum']);
elseif (isset($weather['parts'][2]) && intval($weather['parts'][2]) > 0) $weather['days'] = intval($weather['parts'][2]);
elseif (isset($weather['parts'][1]) && is_numeric($weather['parts'][1])) $weather['days'] = intval($weather['parts'][1]);

foreach ($weather['options'] as $weather['option'] => $weather['default']) $weather['options'][$weather['option']] = intval($weather['block']['option_'.$weather['option']] ?? $weather['default']) == 1 ? 1 : 0;

if (isset($weather['parts'][0],$weather['parts'][1]) && is_numeric($weather['parts'][0]) && is_numeric($weather['parts'][1])) {
	$weather['custom_location'] = ['lat'=>$weather['parts'][0],'lon'=>$weather['parts'][1],'label'=>$weather['block']['option_weather_label'] ?? ''];
	if (isset($weather['parts'][2]) && intval($weather['parts'][2]) > 0) $weather['days'] = intval($weather['parts'][2]);
	$weather['data'] = $weather['entry']->forecastCustom($weather['custom_location'],['days'=>$weather['days']]);
} else $weather['data'] = $weather['entry']->forecast($weather['location'],['days'=>$weather['days']]);

if (empty($weather['data']['result'])) {
	$service['content'] = '';
	unset($weather);
	return;
}

$weather['units'] = match ($weather['data']['units'] ?? 'metric') {
	'imperial' => ['temp'=>'°F','wind'=>'mph'],
	'standard' => ['temp'=>'K','wind'=>'m/s'],
	default => ['temp'=>'°C','wind'=>'m/s']
};

$weather['replace']['layout'] = $weather['layout'];
$weather['replace']['location_id'] = htmlspecialchars(trim((string) ($weather['data']['location']['id'] ?? '')),ENT_QUOTES,'UTF-8');
$weather['replace']['render_current'] = $weather['options']['show_current'];
$weather['replace']['render_forecast'] = $weather['options']['show_forecast'];

if ($weather['options']['show_current'] == 1 && !empty($weather['data']['current'])) {
	$weather['current'][] = parser__replace($weather['structure']['current']['inner'],weather__widget_row($weather['data']['current'],$weather['options'],$weather['units'],true,$weather['entry']));
}

foreach (($weather['data']['daily'] ?? []) as $weather['key'] => $weather['row']) {
	if ($weather['key'] === 0 && $weather['options']['show_current'] == 1) continue;
	$weather['forecast'][] = parser__replace($weather['structure']['forecast']['inner'],weather__widget_row($weather['row'],$weather['options'],$weather['units'],false,$weather['entry']));
}

$weather['replace']['current'] = implode('',$weather['current']);
$weather['replace']['forecast'] = implode('',$weather['forecast']);
$weather['replace']['count'] = count($weather['forecast']) + count($weather['current']);
$service['content'] = parser__replace($weather['structure']['frame'],$weather['replace']);
$_SERVER['load_services']['weather'] = true;

unset($weather);
