<?php

declare(strict_types=1);

namespace Wayfinder\Health\Checks;

use Wayfinder\Health\HealthCheck;
use Wayfinder\Health\HealthResult;
use Wayfinder\Support\Config;

final readonly class AppConfigHealthCheck implements HealthCheck
{
    public function __construct(
        private Config $config,
        private string $name = 'app.config',
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function check(): HealthResult
    {
        $environment = (string) $this->config->get('app.environment', 'local');
        $key = trim((string) $this->config->get('app.key', ''));
        $url = trim((string) $this->config->get('app.url', ''));
        $debug = (bool) $this->config->get('app.debug', false);
        $trustedHosts = $this->config->get('app.trusted_hosts', []);
        $warnings = [];

        if ($key === '') {
            $warnings[] = 'APP_KEY is not set.';
        }

        if ($url === '') {
            $warnings[] = 'APP_URL is not set.';
        }

        if ($environment === 'production' && $debug) {
            $warnings[] = 'APP_DEBUG should be false in production.';
        }

        if ($environment === 'production' && (! is_array($trustedHosts) || $trustedHosts === [])) {
            $warnings[] = 'Trusted hosts should be configured in production.';
        }

        if ($warnings !== []) {
            return HealthResult::warn($this->name, implode(' ', $warnings), [
                'environment' => $environment,
                'debug' => $debug,
            ]);
        }

        return HealthResult::ok($this->name, 'Application configuration looks ready.', [
            'environment' => $environment,
            'debug' => $debug,
        ]);
    }
}
