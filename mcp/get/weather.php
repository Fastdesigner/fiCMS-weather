<?php

if (!$site['onsite']) return false;

if ($context->mode === 'describe') return [
	'purpose'=>'Loads current conditions and the forecast for a configured business location. Use it for direct weather questions and when a near-term arrival, departure or outdoor plan makes verified weather genuinely useful; never infer conditions without this result.',
	'args'=>[
		'id'=>'"default", "list", or a configured weather location id.',
		'data'=>'Optional days from 1 to 8 and units "metric" or "imperial". An admin passes {"view":"edit"} before updating one location.'
	],
	'scope'=>['user','admin'],
	'discover'=>['chat'],
	'anonymous'=>true
];

$weatherMcp = [
	'id'=>trim((string) ($context->args['id'] ?? 'default')),
	'data'=>(isset($context->args['data']) && is_array($context->args['data'])) ? $context->args['data'] : []
];
$weatherMcp['result'] = \weather\McpView::read($weatherMcp['id'],$weatherMcp['data'],$context->scope);
unset($weatherMcp['id'],$weatherMcp['data']);
return $weatherMcp['result'];
