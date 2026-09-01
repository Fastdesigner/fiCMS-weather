<?php

if (!$site['onsite']) return false;

if ($context->mode === 'describe') return [
	'purpose'=>'Creates one admin-managed weather location. The first location becomes the default automatically.',
	'args'=>['data'=>'label, lat and lon are required. Optional id, active, forecast_days and default. Latitude must be -90 to 90 and longitude -180 to 180.'],
	'scope'=>['admin']
];

$weatherMcp = ['data'=>is_array($context->args['data'] ?? null) ? $context->args['data'] : []];
return \weather\McpView::create($weatherMcp['data']);
