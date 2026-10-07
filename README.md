# ALTCHA Captcha for Kirby

Kirby 5 plugin that protects forms with [ALTCHA](https://altcha.org/open-source-captcha/), a self-hosted, privacy-friendly captcha based on proof of work. The visitor's browser solves a small computation before the form is sent: no image puzzles, no cookies, no tracking and no third-party service.

The verification follows [FroshAltchaCaptcha](https://github.com/FriendsOfShopware/FroshAltchaCaptcha) for Shopware (MIT).

## Features

- **Self-hosted**: challenges are created and verified by Kirby, the widget is served from the plugin
- **Replay protection**: each solved challenge is accepted only once
- **Zero configuration**: the signing secret is created automatically if none is configured
- **Accessible**: checkbox widget with screen reader labels, German and other translations included
- **Kirby validator** `altcha` for use with `invalid()` and `V::altcha()`
- **ALTCHA Sentinel** payloads (server signatures) are verified as well

## Requirements

- Kirby 5
- PHP 8.2 or newer
- Visitors need JavaScript and a browser with Web Crypto support (all current browsers)

## Installation

```bash
composer require circus-circuli/kirby-altcha-captcha
```

The plugin depends on the PHP library [altcha-org/altcha](https://github.com/altcha-org/altcha-lib-php). When you copy the plugin to `site/plugins` instead of using Composer, run `composer install` in the plugin folder.

## Usage

Add the widget inside the form:

```php
<form method="post">
  <!-- your fields -->
  <?php snippet('altcha') ?>
  <button type="submit">Send</button>
</form>
```

Verify the submission in the controller:

```php
use CircusCirculi\Altcha\Captcha;

if ($kirby->request()->is('POST')) {
    if (Captcha::verify() === false) {
        $errors['altcha'] = t('error.validation.altcha');
    }

    // ...
}
```

`Captcha::verify()` reads the `altcha` field of the current request. Pass a payload to verify another value: `Captcha::verify($payload)`.

With Kirby's validation:

```php
$errors = invalid($data, [
    'altcha' => ['altcha'],
]);
```

The message for a failed check is `t('error.validation.altcha')`, translated into German and English.

## Widget options

Override the defaults per form:

```php
<?php snippet('altcha', ['options' => [
    'auto'    => 'onload',
    'display' => 'floating',
]]) ?>
```

| Option | Values | Default |
| --- | --- | --- |
| `auto` | When verification starts: `onsubmit`, `onfocus`, `onload`, `off` | `onsubmit` |
| `display` | `standard`, `floating`, `invisible` | `standard` |
| `type` | `checkbox`, `switch`, `native` | `checkbox` |
| `hideLogo` | Hide the ALTCHA logo | `false` |
| `hideFooter` | Hide the "Protected by ALTCHA" link | `false` |
| `language` | Force a language, e.g. `de` (otherwise taken from `<html lang>`) | none |
| `name` | Name of the hidden field | `altcha` |

With `onsubmit`, the widget solves the challenge when the visitor sends the form, so there is nothing to click.

## Configuration

```php
return [
    'circus-circuli.altcha' => [
        'secret'     => null,      // see Secret
        'cost'       => 5000,      // PBKDF2 iterations per attempt
        'counterMin' => 1000,      // range of the secret counter the
        'counterMax' => 1200,      // browser has to find
        'expires'    => 5,         // minutes a challenge stays valid
        'languages'  => ['de'],    // widget translations to load
        'widget'     => [          // defaults for all forms, see Widget options
            'auto'    => 'onsubmit',
            'display' => 'standard',
        ],
    ],
];
```

Solving takes well under a second with the defaults on current devices. Higher `cost` or counters make automated submissions more expensive but slow down older phones.

Included translations: `de`, `fr-fr`, `es-es`, `it`, `nl`, `pl`, `tr`, `uk`, `ru`, `ar`. English is built in.

## Secret

Challenges are signed with an HMAC secret. The plugin takes it from, in this order:

1. The option `circus-circuli.altcha.secret`
2. The environment variable `ALTCHA_SECRET`
3. A random secret created on first use and kept in the plugin cache

Option 3 needs no setup. Set an explicit secret when several servers share the load or when the cache is cleared often: clearing the cache creates a new secret, which only invalidates forms that are open at that moment. Keep the secret out of Git, e.g. in the environment or a host-specific config file.

## Hooks

`altcha.error` is triggered with the exception when a challenge cannot be created or a payload causes an error during verification, e.g. for logging.

## Content Security Policy

The widget runs its proof of work in Web Workers created from inline code (`blob:` and `data:` URLs) and ships its styles inline. With a strict Content Security Policy, allow `worker-src blob: data:` and inline styles for the widget, or switch to the `altcha/external` build of the widget.

## License

MIT, see [LICENSE](LICENSE). Third-party code and attributions are listed in [THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md).
