<?php

declare(strict_types=1);

namespace Wayfinder\Transport;

use Wayfinder\Contracts\Queue\QueueBus;
use Wayfinder\Mail\Mailer;
use Wayfinder\Scenario\EventRecorder;
use Wayfinder\Scenario\ScenarioContext;
use Wayfinder\Support\Clock;
use Wayfinder\Support\ClockManager;
use Wayfinder\Sms\SmsSender;
use Wayfinder\Support\Container;
use Wayfinder\Webhook\WebhookClient;

final class TransportContext
{
    public static function fake(Container $container, callable $callback, bool $executeQueuedJobs = false): mixed
    {
        $events = new EventRecorder();
        $queue = new FakeQueueDispatcher($events, $executeQueuedJobs, $container);
        $clock = new ClockManager();
        $manager = new TransportManager(
            new FakeMailer($events),
            new FakeSmsSender($events),
            $queue,
            new FakeWebhookClient($events),
        );
        $scenario = new ScenarioContext($manager, $events, $clock);

        return $container->scopedInstances([
            TransportManager::class => $manager,
            ScenarioContext::class => $scenario,
            Clock::class => $clock,
            ClockManager::class => $clock,
            Mailer::class => $manager->mailer(),
            SmsSender::class => $manager->sms(),
            QueueBus::class => $queue,
            WebhookClient::class => $manager->webhooks(),
        ], static fn (Container $container): mixed => $callback($scenario, $container));
    }
}
