<?php
/**
 * ALTCHA widget for a form. Place it inside the <form> element.
 *
 * @var array|null $options Widget options, override the plugin defaults:
 *                          auto, display, type, hideLogo, hideFooter, language, name
 */

use CircusCirculi\Altcha\Captcha;

$kirby    = kirby();
$plugin   = $kirby->plugin('circus-circuli/altcha');
$settings = array_merge((array)$kirby->option('circus-circuli.altcha.widget', []), $options ?? []);

$configuration = array_filter([
    'hideLogo'   => (bool)($settings['hideLogo'] ?? false),
    'hideFooter' => (bool)($settings['hideFooter'] ?? false),
]);

$scripts = [$plugin->asset('altcha.min.js')?->url()];

foreach ((array)$kirby->option('circus-circuli.altcha.languages', []) as $language) {
    $scripts[] = $plugin->asset('i18n/' . basename($language) . '.js')?->url();
}
?>
<altcha-widget
  challenge="<?= url('altcha/challenge') ?>"
  name="<?= esc($settings['name'] ?? Captcha::FIELD, 'attr') ?>"
  auto="<?= esc($settings['auto'] ?? 'onsubmit', 'attr') ?>"
  display="<?= esc($settings['display'] ?? 'standard', 'attr') ?>"
  type="<?= esc($settings['type'] ?? 'checkbox', 'attr') ?>"
  <?php if (isset($settings['language'])): ?>language="<?= esc($settings['language'], 'attr') ?>"<?php endif ?>
  <?php if ($configuration !== []): ?>configuration="<?= esc(json_encode($configuration), 'attr') ?>"<?php endif ?>
></altcha-widget>
<noscript><p><?= t('altcha.noscript') ?></p></noscript>
<?php foreach (array_filter($scripts) as $script): ?>
<script type="module" src="<?= $script ?>"></script>
<?php endforeach ?>
