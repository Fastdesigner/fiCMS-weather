<?php

if (!$site['onsite']) return false;

if ($context->mode === 'describe') return [
	'purpose'=>'Updates one weather location after its admin edit view was loaded.',
	'args'=>[
		'id'=>'Weather location id loaded with get(...,{view:"edit"}).',
		'data'=>'Fields to change: label, lat, lon, active, forecast_days and default. Setting default:true makes this the default location.'
	],
	'scope'=>['admin']
];

$weatherMcp = [
	'id'=>trim((string) ($context->args['id'] ?? '')),
	'data'=>is_array($context->args['data'] ?? null) ? $context->args['data'] : []
];
if ($weatherMcp['id'] === '') return ['error'=>'Weather location id is required.'];
return \weather\McpView::update($weatherMcp['id'],$weatherMcp['data']);
