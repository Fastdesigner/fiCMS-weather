<?php

if (!$site['onsite'] || !isset($settings['key']) || $html['is_superviser'] != 1) return;

require_once dirname(__DIR__,2).'/src/Weather.php';

$weather = [
	'entry'=>new \weather\Weather(dirname(__DIR__,2)),
	'output'=>['lists'=>[],'result'=>[]],
	'entries'=>['locations'=>[],'settings'=>[],'roadmap'=>[]],
	'tablist'=>[],
	'attributes'=>[],
	'config'=>[],
	'status'=>[],
	'locations'=>[],
	'location'=>[],
	'items'=>[],
	'unit_options'=>[
		['value'=>'metric','name'=>language__get($user['language'],'_weather_units_metric')],
		['value'=>'imperial','name'=>language__get($user['language'],'_weather_units_imperial')],
		['value'=>'standard','name'=>language__get($user['language'],'_weather_units_standard')]
	],
	'cache_options'=>[
		['value'=>30,'name'=>language__get($user['language'],'_weather_cache_30')],
		['value'=>60,'name'=>language__get($user['language'],'_weather_cache_60')],
		['value'=>120,'name'=>language__get($user['language'],'_weather_cache_120')],
		['value'=>240,'name'=>language__get($user['language'],'_weather_cache_240')],
		['value'=>720,'name'=>language__get($user['language'],'_weather_cache_720')]
	]
];

$weather['config'] = $weather['entry']->getConfig();
$weather['status'] = $weather['entry']->serviceStatus();

if (isset($_POST['settings'],$_POST['type'],$_POST['action']) && $_POST['type'] == $settings['key']) {
	$weather['action'] = trim((string) $_POST['action']);
	$weather['id'] = trim((string) ($_POST['id'] ?? ''));
	if (str_starts_with($weather['id'],'location-')) $weather['id'] = substr($weather['id'],9);

	if ($weather['action'] == 'update' && isset($_POST['name'])) {
		$weather['output']['result'] = ['result'=>$weather['entry']->updateSetting(trim((string) $_POST['name']),$_POST['value'] ?? '')];
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $weather['action'] == 'default_location' && $weather['id'] != '') {
		$weather['output']['result'] = ['result'=>$weather['entry']->setDefaultLocation($weather['id'])];
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $weather['action'] == 'ac' && $weather['id'] != '') {
		$weather['output']['result'] = ['result'=>$weather['entry']->setLocationActive($weather['id'],intval($_POST['value'] ?? 0))];
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $weather['action'] == 'delete' && $weather['id'] != '') {
		$weather['output']['result'] = ['result'=>$weather['entry']->deleteLocation($weather['id'])];
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $weather['action'] == 'sync' && $weather['id'] != '') {
		$weather['synced'] = $weather['entry']->refreshLocation($weather['id']);
		$weather['output']['result'] = ['result'=>!empty($weather['synced']['result']),'error'=>$weather['synced']['error'] ?? ''];
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $weather['action'] == 'save_location') {
		$weather['output']['result'] = $weather['entry']->saveLocationFromPost($weather['id'],$_POST);
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $weather['action'] == 'load') {
		$weather['location'] = $weather['id'] == 'new' ? $weather['entry']->blankLocation('new') : $weather['entry']->location($weather['id']);
		$weather['formitems'] = create__form_items([
			'location_active'=>['type'=>'checkbox'],
			'location_id'=>['required'=>true,'attributes'=>['pattern'=>'[a-zA-Z0-9_-]+']],
			'location_label'=>['required'=>true],
			'location_lat'=>['required'=>true,'attributes'=>['inputmode'=>'decimal']],
			'location_lon'=>['required'=>true,'attributes'=>['inputmode'=>'decimal']],
			'location_forecast_days'=>['type'=>'number','attributes'=>['min'=>1,'max'=>8]]
		],[
			'location_active'=>$weather['location']['active'],
			'location_id'=>$weather['location']['id'] == 'new' ? '' : $weather['location']['id'],
			'location_label'=>$weather['location']['label'],
			'location_lat'=>$weather['location']['lat'],
			'location_lon'=>$weather['location']['lon'],
			'location_forecast_days'=>$weather['location']['forecast_days']
		],'weather',$user['language']);
		$weather['formitems']['location_active']['checked'] = intval($weather['location']['active']) == 1;
		$weather['form'] = [];
		foreach (['location_active','location_id','location_label','location_lat','location_lon','location_forecast_days'] as $weather['field']) $weather['form'][] = ['id'=>$settings['key'].'-form-'.$weather['field'],'type'=>'form','classes'=>['forms__item'],'form'=>$weather['formitems'][$weather['field']]];
		$weather['output']['lists'] = create__form($settings['form'],$weather['form'],$weather['id'] == 'new' ? language__get($user['language'],'_weather_location_new') : language__get_parsed($user['language'],'_weather_location_edit',['label'=>$weather['location']['label']]),language__get($user['language'],'_settings_form_save'),['load'=>['action'=>'save_location','id'=>$weather['location']['id']]]);
		$weather['output']['result'] = ['result'=>true];
		$_POST['handled'] = true;
	}
}

$weather['config'] = $weather['entry']->getConfig();
$weather['status'] = $weather['entry']->serviceStatus();
$weather['locations'] = $weather['entry']->locations($weather['config']);

$weather['entries']['settings'][] = [
	'id'=>$settings['key'].'-service-status',
	'tag'=>'ul',
	'classes'=>['system-list'],
	'items'=>[
		['id'=>$settings['key'].'-service-active','description'=>language__get($user['language'],'_weather_service_status'),'subtitle'=>language__get($user['language'],$weather['status']['active'] == 1 ? '_active' : '_inactive')],
		['id'=>$settings['key'].'-service-key','description'=>language__get($user['language'],'_weather_service_key'),'subtitle'=>language__get($user['language'],$weather['status']['has_key'] == 1 ? '_weather_service_key_available' : '_weather_service_key_missing')]
	]
];
$weather['entries']['settings'][] = ['id'=>$settings['key'].'-active','type'=>'form','classes'=>['forms__item'],'form'=>['type'=>'checkbox','option'=>'active','name'=>language__get($user['language'],'_weather_active'),'checked'=>$weather['config']['active'] == 1,'change'=>['function'=>'settings__update'],'attributes'=>['data-default'=>1]]];
$weather['entries']['settings'][] = ['id'=>$settings['key'].'-units','type'=>'form','classes'=>['forms__item'],'form'=>['type'=>'select','option'=>'units','name'=>language__get($user['language'],'_weather_units'),'value'=>$weather['config']['units'],'options'=>$weather['unit_options'],'change'=>['function'=>'settings__update']]];
$weather['entries']['settings'][] = ['id'=>$settings['key'].'-cache','type'=>'form','classes'=>['forms__item'],'form'=>['type'=>'select','option'=>'cache_minutes','name'=>language__get($user['language'],'_weather_cache_minutes'),'value'=>$weather['config']['cache_minutes'],'options'=>$weather['cache_options'],'change'=>['function'=>'settings__update']]];
$weather['entries']['settings'][] = ['id'=>$settings['key'].'-forecast-days','type'=>'form','classes'=>['forms__item'],'form'=>['type'=>'number','option'=>'forecast_days','name'=>language__get($user['language'],'_weather_forecast_days'),'value'=>$weather['config']['forecast_days'],'attributes'=>['min'=>1,'max'=>8],'change'=>['function'=>'settings__update']]];

foreach ($weather['locations'] as $weather['location']) {
	$weather['subtitle'] = [];
	if ($weather['location']['last_success'] > 0) $weather['subtitle'][] = language__get($user['language'],'_weather_last_success').': '.format__date_relative($weather['location']['last_success'],'relative',$user['language'],true);
	if ($weather['location']['last_error'] != '') $weather['subtitle'][] = language__get($user['language'],'_weather_last_error').': '.htmlspecialchars($weather['location']['last_error'],ENT_QUOTES,'UTF-8');
	if (empty($weather['subtitle'])) $weather['subtitle'][] = language__get($user['language'],'_weather_never_synced');
	$weather['items'][] = [
		'id'=>$settings['key'].'-location-'.$weather['location']['id'],
		'tag'=>'li',
		'description'=>$weather['location']['label'] != '' ? $weather['location']['label'] : $weather['location']['id'],
		'subtitle'=>implode(' · ',$weather['subtitle']),
		'icons'=>$weather['config']['default_location'] == $weather['location']['id'] ? [['id'=>$settings['key'].'-'.$weather['location']['id'].'-default','attributes'=>['data-systemicon'=>'check']]] : [],
		'actions'=>[
			'load'=>['id'=>'location-'.$weather['location']['id'],'form'=>true],
			'ac'=>['id'=>'location-'.$weather['location']['id'],'action'=>'ac','name'=>$settings['key'].'-location-'.$weather['location']['id'],'checked'=>$weather['location']['active'] == 1],
			'delete'=>['id'=>'location-'.$weather['location']['id']],
			'icons'=>[
				'sync'=>['id'=>'location-'.$weather['location']['id'],'action'=>'sync','systemicon'=>'sync','title'=>language__get($user['language'],'_weather_sync')],
				'default'=>['id'=>'location-'.$weather['location']['id'],'action'=>'default_location','systemicon'=>'check','title'=>language__get($user['language'],'_weather_set_default')]
			]
		]
	];
}
if (empty($weather['items'])) $weather['items'][] = ['id'=>$settings['key'].'-locations-empty','tag'=>'font','classes'=>['forms__item'],'description'=>language__get($user['language'],'_weather_locations_empty')];
$weather['items'][] = ['id'=>$settings['key'].'-location-new','tag'=>'li','description'=>language__get($user['language'],'_weather_location_new'),'classes'=>['system-next'],'actions'=>['load'=>['id'=>'new','form'=>true]]];
$weather['entries']['locations'][] = create__list($settings['key'].'-locations',$weather['items'],['clear'=>true,'sort'=>true]);

$weather['entries']['roadmap'][] = ['id'=>$settings['key'].'-warnings-info','tag'=>'font','classes'=>['forms__item'],'description'=>language__get($user['language'],'_weather_warnings_roadmap_info')];
$weather['entries']['roadmap'][] = create__list($settings['key'].'-warnings-roadmap',[
	['description'=>language__get($user['language'],'_weather_warnings_roadmap_locations')],
	['description'=>language__get($user['language'],'_weather_warnings_roadmap_channels')],
	['description'=>language__get($user['language'],'_weather_warnings_roadmap_severity')],
	['description'=>language__get($user['language'],'_weather_warnings_roadmap_quiet')]
],['clear'=>true]);

$weather['tablist'] = [
	'locations'=>language__get($user['language'],'_weather_tab_locations'),
	'settings'=>language__get($user['language'],'_weather_tab_settings'),
	'roadmap'=>language__get($user['language'],'_weather_tab_warnings')
];
$weather['attributes'] = [
	'locations'=>['classes'=>['forms__wrapper']],
	'settings'=>['classes'=>['forms__wrapper']],
	'roadmap'=>['classes'=>['forms__wrapper']]
];
$weather['tabs'] = create__tablist($settings['key'],$weather['tablist'],$weather['entries'],$weather['attributes']);
$weather['output']['lists'][$settings['key'].'Content'] = ['id'=>$settings['key'].'Content','items'=>[$weather['tabs']['tabs'],$weather['tabs']['panels']]];

foreach ($weather['output'] as $key => $value) {
	if (empty($value)) continue;
	if (!isset($settings['output'][$key])) $settings['output'][$key] = [];
	$settings['output'][$key] = array_merge($settings['output'][$key],$value);
}

unset($weather);
