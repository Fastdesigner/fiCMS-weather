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
	'details'=>[],
	'forecast_details'=>[],
	'icon_items'=>[],
	'icon_grid'=>[]
];

$weather['config'] = $weather['entry']->getConfig();
$weather['status'] = $weather['entry']->serviceStatus();

if (isset($_POST['settings'],$_POST['type'],$_POST['action']) && $_POST['type'] == $settings['key']) {
	$weather['action'] = trim((string) $_POST['action']);
	$weather['id'] = trim((string) ($_POST['id'] ?? ''));
	if (str_starts_with($weather['id'],'location-')) $weather['id'] = substr($weather['id'],9);

	if ($weather['action'] == 'update' && isset($_POST['name']) && str_starts_with((string) $_POST['name'],'icon_')) {
		$weather['output']['result'] = $weather['entry']->saveIconMedia(trim((string) $_POST['name']),$_POST['value'] ?? '');
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $weather['action'] == 'update' && isset($_POST['name'])) {
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
			'location_label'=>['required'=>true],
			'location_lat'=>['required'=>true,'attributes'=>['inputmode'=>'decimal']],
			'location_lon'=>['required'=>true,'attributes'=>['inputmode'=>'decimal']]
		],[
			'location_active'=>$weather['location']['active'],
			'location_label'=>$weather['location']['label'],
			'location_lat'=>$weather['location']['lat'],
			'location_lon'=>$weather['location']['lon']
		],'weather',$user['language']);
		$weather['formitems']['location_active']['checked'] = intval($weather['location']['active']) == 1;
		$weather['form'] = [];
		foreach (['location_active','location_label','location_lat','location_lon'] as $weather['field']) $weather['form'][] = ['id'=>$settings['key'].'-form-'.$weather['field'],'type'=>'form','classes'=>['forms__item'],'form'=>$weather['formitems'][$weather['field']]];
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
	'description'=>language__get($user['language'],'_weather_service_status'),
	'subtitle'=>language__get($user['language'],$weather['status']['active'] == 1 ? '_active' : '_inactive').' · '.language__get($user['language'],$weather['status']['has_key'] == 1 ? '_weather_service_key_available' : '_weather_service_key_missing'),
	'image'=>$weather['entry']->iconUrl('01d'),
	'classes'=>['system-highlight']
];
$weather['entries']['settings'][] = ['id'=>$settings['key'].'-cache','type'=>'form','classes'=>['forms__item'],'form'=>['type'=>'number','option'=>'cache_hours','name'=>language__get($user['language'],'_weather_cache_hours'),'value'=>max(1,intval(round($weather['config']['cache_minutes'] / 60))),'attributes'=>['min'=>1,'max'=>24,'step'=>1,'inputmode'=>'numeric'],'change'=>['function'=>'settings__update']]];

foreach ($weather['entry']->iconCodes() as $weather['icon']) {
	$weather['icon_items'][] = [
		'id'=>$settings['key'].'-icon-'.$weather['icon'],
		'type'=>'form',
		'classes'=>['forms__item','img--obj-contain'],
		'image'=>$weather['entry']->iconUrl($weather['icon']),
		'form'=>['type'=>'media','option'=>'icon_'.$weather['icon'],'name'=>language__get($user['language'],'_weather_icon_'.$weather['icon'],true),'value'=>[],'change'=>['function'=>'settings__update']]
	];
}
$weather['icon_grid'] = ['id'=>$settings['key'].'-icons-grid','tag'=>'ul','classes'=>['system-list'],'attributes'=>['data-listview'=>'grid','data-aspect'=>1],'sort'=>true,'items'=>$weather['icon_items']];
$weather['entries']['settings'][] = create__dropdown($settings['key'].'-icons',language__get($user['language'],'_weather_icons_title'),$weather['icon_grid'],['subtitle'=>language__get($user['language'],'_weather_icons_subtitle'),'attributes'=>['data-details-independent'=>'true'],'image'=>$weather['entry']->iconUrl('02d')]);

foreach ($weather['locations'] as $weather['location']) {
	$weather['preview'] = $weather['entry']->preview($weather['location']['id']);
	$weather['current'] = $weather['preview']['current'];
	$weather['details'] = [];
	$weather['forecast_details'] = [];
	foreach (array_slice($weather['preview']['daily'],0,5) as $weather['day']) {
		if (!is_array($weather['day'])) continue;
		$weather['forecast_details'][] = [
			'id'=>$settings['key'].'-location-'.$weather['location']['id'].'-forecast-'.$weather['day']['date'],
			'description'=>format__date_relative(intval($weather['day']['date'] ?? $_SERVER['now']),'date',$user['language']),
			'subtitle'=>htmlspecialchars(implode(' · ',array_filter([trim((string) ($weather['day']['description'] ?? '')),($weather['day']['temp_min'] ?? '').' / '.($weather['day']['temp_max'] ?? '').' °'.(($weather['preview']['current']['units'] ?? 'metric') == 'imperial' ? 'F' : 'C')],fn($value) => trim((string) $value) !== '')),ENT_QUOTES,'UTF-8'),
			'image'=>$weather['entry']->iconUrl($weather['day']['icon'] ?? '01d')
		];
	}
	if (!empty($weather['forecast_details'])) {
		$weather['details'][] = ['id'=>$settings['key'].'-location-'.$weather['location']['id'].'-forecast','description'=>language__get($user['language'],'_weather_forecast_preview')];
		$weather['details'] = array_merge($weather['details'],$weather['forecast_details']);
	}
	if ($weather['location']['last_error'] != '') $weather['details'][] = ['id'=>$settings['key'].'-location-'.$weather['location']['id'].'-error','description'=>language__get($user['language'],'_weather_last_error'),'subtitle'=>htmlspecialchars($weather['location']['last_error'],ENT_QUOTES,'UTF-8')];
	$weather['details'][] = ['id'=>$settings['key'].'-location-'.$weather['location']['id'].'-active','description'=>language__get($user['language'],'_weather_location_active'),'actions'=>['ac'=>['id'=>'location-'.$weather['location']['id'],'action'=>'ac','name'=>$settings['key'].'-location-'.$weather['location']['id'],'checked'=>$weather['location']['active'] == 1,'dropdown_sync'=>false]]];
	$weather['details'][] = ['id'=>$settings['key'].'-location-'.$weather['location']['id'].'-default','description'=>language__get($user['language'],'_weather_default_location'),'actions'=>['ac'=>['id'=>'location-'.$weather['location']['id'],'action'=>'default_location','name'=>$settings['key'].'-location-'.$weather['location']['id'].'-default','checked'=>$weather['config']['default_location'] == $weather['location']['id'],'dropdown_sync'=>false]]];
	$weather['details'][] = ['id'=>$settings['key'].'-location-'.$weather['location']['id'].'-sync','description'=>language__get($user['language'],'_weather_sync'),'actions'=>['icons'=>['sync'=>['id'=>'location-'.$weather['location']['id'],'action'=>'sync','systemicon'=>'refresh','title'=>language__get($user['language'],'_weather_sync')]]]];
	$weather['details'][] = ['id'=>$settings['key'].'-location-'.$weather['location']['id'].'-edit','description'=>language__get($user['language'],'_weather_location_edit_action'),'actions'=>['load'=>['id'=>'location-'.$weather['location']['id'],'form'=>true]]];
	$weather['details'][] = ['id'=>$settings['key'].'-location-'.$weather['location']['id'].'-delete','tag'=>'li','items'=>[
		['id'=>$settings['key'].'-location-'.$weather['location']['id'].'-delete-button','tag'=>'button','classes'=>['system-button'],'attributes'=>['type'=>'button','data-confirmation'=>language__get($user['language'],'_ui_confirm_delete')],'description'=>language__get($user['language'],'_weather_location_delete'),'actions'=>['load'=>['action'=>'delete','id'=>'location-'.$weather['location']['id']]]]
	]];
	$weather['items'][] = [
		'id'=>$settings['key'].'-location-'.$weather['location']['id'],
		'tag'=>'li',
		'items'=>[
			create__dropdown($settings['key'].'-location-'.$weather['location']['id'].'-dropdown',$weather['location']['label'] != '' ? $weather['location']['label'] : $weather['location']['id'],create__list($settings['key'].'-location-'.$weather['location']['id'].'-list',$weather['details'],['clear'=>true]),[
				'image'=>$weather['entry']->iconUrl($weather['current']['icon'] ?? '01d'),
				'attributes'=>['class'=>'system-next','data-details-independent'=>'true']
			]
			)
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
