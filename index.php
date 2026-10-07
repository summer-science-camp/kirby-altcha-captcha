<?php

use CircusCirculi\Altcha\Captcha;
use Kirby\Cms\App;
use Kirby\Http\Response;

// Dependencies when the plugin is not installed with the site's Composer
@include_once __DIR__ . '/vendor/autoload.php';

load([
    'CircusCirculi\\Altcha\\Captcha' => 'src/Captcha.php',
], __DIR__);

App::plugin('circus-circuli/altcha', [
    'options' => [
        // HMAC secret. Leave empty to use the ALTCHA_SECRET environment
        // variable or a secret created once and kept in the plugin cache.
        'secret'     => null,

        // Difficulty: PBKDF2 iterations per attempt and the range of the
        // secret counter the browser has to find
        'cost'       => 5000,
        'counterMin' => 1000,
        'counterMax' => 1200,

        // Minutes a challenge stays valid
        'expires'    => 5,

        // Widget defaults, each can be overridden per snippet call
        'widget' => [
            // When verification starts: onsubmit, onfocus, onload or off
            'auto'       => 'onsubmit',
            // standard, floating or invisible
            'display'    => 'standard',
            // checkbox, switch or native
            'type'       => 'checkbox',
            'hideLogo'   => false,
            'hideFooter' => false,
        ],

        // Widget translations to load, English is always included
        'languages' => ['de'],

        'cache' => true,
    ],

    'routes' => [
        [
            'pattern' => 'altcha/challenge',
            'method'  => 'GET',
            'action'  => function () {
                // Every visitor needs a fresh challenge
                $headers = ['Cache-Control' => 'no-store'];

                try {
                    return Response::json(Captcha::challenge(), 200, null, $headers);
                } catch (Throwable $e) {
                    kirby()->trigger('altcha.error', ['exception' => $e]);
                    return Response::json(['error' => 'Challenge could not be created.'], 500, null, $headers);
                }
            },
        ],
    ],

    'snippets' => [
        'altcha' => __DIR__ . '/snippets/altcha.php',
    ],

    'validators' => [
        /**
         * `invalid($data, ['altcha' => ['altcha']])` or V::altcha($payload)
         */
        'altcha' => fn ($value): bool => Captcha::verify(is_string($value) ? $value : null),
    ],

    'translations' => [
        'de' => [
            'error.validation.altcha' => 'Bitte bestätige, dass du kein Roboter bist.',
            'altcha.noscript'         => 'Bitte aktiviere JavaScript, um das Formular abzuschicken.',
        ],
        'en' => [
            'error.validation.altcha' => 'Please confirm that you are not a robot.',
            'altcha.noscript'         => 'Please enable JavaScript to send the form.',
        ],
    ],
]);
