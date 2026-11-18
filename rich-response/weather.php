<?php

return [
	'purpose'=>'Block weather card from this exact forecast, including alerts and stale status.',
	'syntax'=>'[Title](weather:<card.reference>)',
	'layout'=>'block',
	'requires'=>['get:weather'],
	'available'=>[\weather\Card::class,'available'],
	'parse'=>[\weather\Card::class,'parse'],
	'html'=>[\weather\Card::class,'html'],
	'plain'=>[\weather\Card::class,'plain'],
	'attachments'=>[\weather\Card::class,'attachments']
];
