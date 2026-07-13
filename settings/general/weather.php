<?php

if (!file_exists(DESIGNSYSTEM.'/assets/js/admin/sys.js')) {
	require PLUGINPATH.'/fiCMS-weather/deprecated/settings/general/weather.php';
	return;
}

if (!$site['onsite'] || !isset($settings['key']) || $html['is_superviser'] != 1) return;

require_once dirname(__DIR__,2).'/src/Weather.php';

$weather = ['entry'=>new \weather\Weather(dirname(__DIR__,2)),'output'=>['result'=>[]],'config'=>[],'status'=>[],'locations'=>[],'location'=>[]];

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
		$weather['form_ui'] = new \ficms\Ui($settings['key'],'weather',$user['language']);
		foreach (['-headline','-body','-submit-wrapper'] as $weather['suffix']) $weather['form_ui']->register($settings['form'].$weather['suffix']);
		$weather['form_ui']->slot($settings['form'].'-headline',['headline'=>$weather['id'] == 'new' ? language__get($user['language'],'_weather_location_new') : language__get_parsed($user['language'],'_weather_location_edit',['label'=>$weather['location']['label']])]);
		$weather['form'] = $weather['form_ui']->slot($settings['form'].'-body',['clear'=>true]);
		$weather['form']->check('location_active',intval($weather['location']['active']) == 1,['call'=>false]);
		foreach (['label','lat','lon'] as $weather['field']) $weather['form']->field('location_'.$weather['field'],'input',$weather['location'][$weather['field']],['id'=>$settings['key'].'-form-location_'.$weather['field'],'attrs'=>array_merge(['required'=>'required'],in_array($weather['field'],['lat','lon'],true) ? ['inputmode'=>'decimal'] : []),'call'=>false]);
		$weather['submit'] = $weather['form_ui']->slot($settings['form'].'-submit-wrapper',['clear'=>true]);
		$weather['submit']->button('save',['label'=>language__get($user['language'],'_settings_form_save'),'action'=>'save_location','aid'=>$weather['location']['id']]);
		$weather['form_ui']->emit($settings);
		$weather['output']['result'] = ['result'=>true];
		$_POST['handled'] = true;
	}
}

$weather['config'] = $weather['entry']->getConfig();
$weather['status'] = $weather['entry']->serviceStatus();
$weather['locations'] = $weather['entry']->locations($weather['config']);
$weather['ui'] = new \ficms\Ui($settings['key'],'weather',$user['language']);
$weather['locations_tab'] = $weather['ui']->tab('locations',['label'=>language__get($user['language'],'_weather_tab_locations')]);
$weather['locations_list'] = $weather['locations_tab']->listing('locations',['clear'=>true,'sort'=>true]);
foreach ($weather['locations'] as $weather['location']) {
	$weather['preview'] = $weather['entry']->preview($weather['location']['id']);
	$weather['dropdown'] = $weather['locations_list']->dropdown('location-'.$weather['location']['id'],['label'=>$weather['location']['label'] != '' ? $weather['location']['label'] : $weather['location']['id'],'image'=>$weather['entry']->iconUrl($weather['preview']['current']['icon'] ?? '01d'),'independent'=>true]);
	if ($weather['preview']['daily']) {
		$weather['dropdown']->text('forecast-title',language__get($user['language'],'_weather_forecast_preview'));
		foreach (array_slice($weather['preview']['daily'],0,5) as $weather['day']) {
			if (!is_array($weather['day'])) continue;
			$weather['dropdown']->item('forecast-'.$weather['day']['date'],['label'=>format__date_relative(intval($weather['day']['date'] ?? $_SERVER['now']),'date',$user['language']),'subtitle'=>implode(' · ',array_filter([trim((string) ($weather['day']['description'] ?? '')),($weather['day']['temp_min'] ?? '').' / '.($weather['day']['temp_max'] ?? '').' °'.(($weather['preview']['current']['units'] ?? 'metric') == 'imperial' ? 'F' : 'C')],fn($value) => trim((string) $value) !== '')),'image'=>$weather['entry']->iconUrl($weather['day']['icon'] ?? '01d')]);
		}
	}
	if ($weather['location']['last_error'] != '') $weather['dropdown']->item('error',['label'=>language__get($user['language'],'_weather_last_error'),'subtitle'=>$weather['location']['last_error'],'notify'=>'warning']);
	$weather['dropdown']->item('active',['label'=>language__get($user['language'],'_weather_location_active'),'toggle'=>['id'=>'location-'.$weather['location']['id'],'action'=>'ac','name'=>$settings['key'].'-location-'.$weather['location']['id'],'checked'=>$weather['location']['active'] == 1]]);
	$weather['dropdown']->item('default',['label'=>language__get($user['language'],'_weather_default_location'),'toggle'=>['id'=>'location-'.$weather['location']['id'],'action'=>'default_location','name'=>$settings['key'].'-location-'.$weather['location']['id'].'-default','checked'=>$weather['config']['default_location'] == $weather['location']['id']]]);
	$weather['dropdown']->item('sync',['label'=>language__get($user['language'],'_weather_sync'),'actions'=>['icons'=>['sync'=>['id'=>'location-'.$weather['location']['id'],'action'=>'sync','systemicon'=>'refresh','title'=>language__get($user['language'],'_weather_sync')]]]]);
	$weather['dropdown']->item('edit',['label'=>language__get($user['language'],'_weather_location_edit_action'),'load'=>['id'=>'location-'.$weather['location']['id'],'form'=>true]]);
	$weather['dropdown']->button('delete',['label'=>language__get($user['language'],'_weather_location_delete'),'action'=>'delete','aid'=>'location-'.$weather['location']['id'],'confirm'=>language__get($user['language'],'_ui_confirm_delete')]);
}
if (!$weather['locations']) $weather['locations_list']->text('empty',language__get($user['language'],'_weather_locations_empty'));
$weather['locations_list']->item('new',['label'=>language__get($user['language'],'_weather_location_new'),'load'=>['id'=>'new','form'=>true]]);

$weather['settings_tab'] = $weather['ui']->tab('settings',['label'=>language__get($user['language'],'_weather_tab_settings')]);
$weather['settings_tab']->item('service-status',['label'=>language__get($user['language'],'_weather_service_status'),'subtitle'=>language__get($user['language'],$weather['status']['active'] == 1 ? '_active' : '_inactive').' · '.language__get($user['language'],$weather['status']['has_key'] == 1 ? '_weather_service_key_available' : '_weather_service_key_missing'),'image'=>$weather['entry']->iconUrl('01d')]);
$weather['settings_tab']->field('cache_hours','number',max(1,intval(round($weather['config']['cache_minutes'] / 60))),['attrs'=>['min'=>1,'max'=>24,'step'=>1,'inputmode'=>'numeric']]);
$weather['icons'] = $weather['settings_tab']->dropdown('icons',['label'=>language__get($user['language'],'_weather_icons_title'),'subtitle'=>language__get($user['language'],'_weather_icons_subtitle'),'image'=>$weather['entry']->iconUrl('02d'),'independent'=>true]);
$weather['icons_grid'] = $weather['icons']->listing('icons-grid',['sort'=>true,'attrs'=>['data-listview'=>'grid','data-aspect'=>1]]);
foreach ($weather['entry']->iconCodes() as $weather['icon']) $weather['icons_grid']->field('icon_'.$weather['icon'],'media',[],['label'=>language__get($user['language'],'_weather_icon_'.$weather['icon'],true),'attrs'=>['data-preview'=>$weather['entry']->iconUrl($weather['icon'])]]);

$weather['roadmap'] = $weather['ui']->tab('roadmap',['label'=>language__get($user['language'],'_weather_tab_warnings')]);
$weather['roadmap']->text('info',language__get($user['language'],'_weather_warnings_roadmap_info'));
$weather['roadmap_list'] = $weather['roadmap']->listing('warnings-roadmap',['clear'=>true]);
foreach (['locations','channels','severity','quiet'] as $weather['roadmap_item']) $weather['roadmap_list']->item($weather['roadmap_item'],['label'=>language__get($user['language'],'_weather_warnings_roadmap_'.$weather['roadmap_item'])]);
$weather['ui']->emit($settings);

foreach ($weather['output'] as $key => $value) {
	if (empty($value)) continue;
	if (!isset($settings['output'][$key])) $settings['output'][$key] = [];
	$settings['output'][$key] = array_merge($settings['output'][$key],$value);
}

unset($weather);
