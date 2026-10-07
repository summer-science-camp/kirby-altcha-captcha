<?php

namespace CircusCirculi\Altcha;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\ChallengeParameters;
use AltchaOrg\Altcha\CreateChallengeOptions;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\ServerSignature;
use AltchaOrg\Altcha\Solution;
use AltchaOrg\Altcha\VerifySolutionOptions;
use DateTimeImmutable;
use Kirby\Cache\Cache;
use Kirby\Cms\App;
use Throwable;

/**
 * Creates and verifies ALTCHA proof-of-work challenges.
 *
 * The visitor's browser solves a small computation before the form is sent.
 * No cookies, no tracking and no third-party service are involved.
 */
class Captcha
{
    public const ENV_SECRET = 'ALTCHA_SECRET';
    public const FIELD      = 'altcha';

    /**
     * New challenge for the widget, signed with the secret
     */
    public static function challenge(): array
    {
        $options = static::options();
        $counter = random_int($options['counterMin'], max($options['counterMin'], $options['counterMax']));

        $challenge = (new Altcha(static::secret()))->createChallenge(new CreateChallengeOptions(
            algorithm: new Pbkdf2(),
            cost: $options['cost'],
            counter: $counter,
            expiresAt: new DateTimeImmutable('+' . $options['expires'] . ' minutes'),
        ));

        return $challenge->toArray();
    }

    /**
     * Verifies the payload the widget writes into the hidden form field.
     *
     * Each solved challenge is accepted only once, so a payload cannot be
     * replayed for several submissions.
     *
     * @param string|null $payload Defaults to the `altcha` field of the current request
     */
    public static function verify(?string $payload = null): bool
    {
        $payload ??= App::instance()->request()->get(static::FIELD);
        $data    = is_string($payload) ? static::decode($payload) : null;

        if ($data === null) {
            return false;
        }

        try {
            // Payload verified by ALTCHA Sentinel or another verification server
            if (isset($data['verificationData'])) {
                return ServerSignature::verifyServerSignature($data, static::secret())->verified
                    && static::markUsed($data['signature'] ?? null, null);
            }

            if (is_array($data['challenge'] ?? null) && is_array($data['solution'] ?? null)) {
                return static::verifySolution($data['challenge'], $data['solution']);
            }
        } catch (Throwable $e) {
            App::instance()->trigger('altcha.error', ['exception' => $e]);
        }

        return false;
    }

    /**
     * HMAC key used to sign the challenges. Comes from the option, the
     * environment or is created once and kept in the plugin cache.
     */
    public static function secret(): string
    {
        $kirby  = App::instance();
        $secret = $kirby->option('circus-circuli.altcha.secret');

        if (is_string($secret) && $secret !== '') {
            return $secret;
        }

        $secret = getenv(static::ENV_SECRET) ?: ($_SERVER[static::ENV_SECRET] ?? $_ENV[static::ENV_SECRET] ?? null);

        if (is_string($secret) && $secret !== '') {
            return $secret;
        }

        $cache  = static::cache();
        $secret = $cache->get('secret');

        if (is_string($secret) === false || strlen($secret) < 32) {
            $secret = bin2hex(random_bytes(32));
            $cache->set('secret', $secret);
        }

        return $secret;
    }

    /**
     * @return array{cost: int, counterMin: int, counterMax: int, expires: int}
     */
    private static function options(): array
    {
        $kirby = App::instance();

        return [
            'cost'       => max(1, (int)$kirby->option('circus-circuli.altcha.cost', 5000)),
            'counterMin' => max(1, (int)$kirby->option('circus-circuli.altcha.counterMin', 1000)),
            'counterMax' => max(1, (int)$kirby->option('circus-circuli.altcha.counterMax', 1200)),
            'expires'    => max(1, (int)$kirby->option('circus-circuli.altcha.expires', 5)),
        ];
    }

    private static function verifySolution(array $challengeData, array $solutionData): bool
    {
        $challenge = new Challenge(
            ChallengeParameters::fromArray($challengeData['parameters'] ?? []),
            $challengeData['signature'] ?? null,
        );

        $solution = new Solution(
            counter: (int)($solutionData['counter'] ?? 0),
            derivedKey: (string)($solutionData['derivedKey'] ?? ''),
        );

        $result = (new Altcha(static::secret()))->verifySolution(new VerifySolutionOptions(
            payload: new Payload($challenge, $solution),
            algorithm: new Pbkdf2(),
        ));

        return $result->verified && static::markUsed($challenge->signature, $challenge->parameters->expiresAt);
    }

    /**
     * Remembers a solved challenge until it expires.
     * Returns false if it was already used.
     */
    private static function markUsed(?string $signature, int|float|null $expiresAt): bool
    {
        if ($signature === null || $signature === '') {
            return false;
        }

        $cache = static::cache();
        $key   = 'used.' . hash('sha256', $signature);

        if ($cache->exists($key)) {
            return false;
        }

        // Expiry is a unix timestamp, the cache expects minutes
        $minutes = $expiresAt !== null
            ? max(1, (int)ceil(((float)$expiresAt - time()) / 60) + 1)
            : static::options()['expires'] + 1;

        $cache->set($key, true, $minutes);

        return true;
    }

    /**
     * Decodes the base64 encoded JSON payload of the widget
     */
    private static function decode(string $payload): ?array
    {
        $json = base64_decode($payload, true);
        $data = $json !== false ? json_decode($json, true) : null;

        return is_array($data) ? $data : null;
    }

    private static function cache(): Cache
    {
        return App::instance()->cache('circus-circuli.altcha');
    }
}
