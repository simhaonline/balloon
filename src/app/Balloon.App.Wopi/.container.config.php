<?php
use Balloon\App\Wopi\Constructor\Http;
use Balloon\Bootstrap\AbstractBootstrap;
use Micro\Auth\Auth;
use Balloon\App\Wopi\Auth\Token;

return [
    AbstractBootstrap::class => [
        'calls' => [
            'Balloon.App.Wopi' => [
                'method' => 'inject',
                'arguments' => ['object' => '{'.Http::class.'}']
            ],
        ]
    ],
    Auth::class => [
        'calls' => [
            Token::class => [
                'method' => 'injectAdapter',
                'arguments' => ['adapter' => '{'.Token::class.'}']
            ],
        ],
    ],
];