<?php

declare(strict_types=1);

namespace Wayfinder\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Wayfinder\Contracts\Queue\QueueBus;
use Wayfinder\Mail\MailMessage;
use Wayfinder\Mail\Mailer;
use Wayfinder\Sms\SmsMessage;
use Wayfinder\Sms\SmsSender;
use Wayfinder\Support\Clock;
use Wayfinder\Support\ClockManager;
use Wayfinder\Support\Container;
use Wayfinder\Transport\FakeMailer;
use Wayfinder\Transport\TransportAssertions;
use Wayfinder\Transport\TransportContext;
use Wayfinder\Transport\TransportManager;
use Wayfinder\Webhook\WebhookClient;
use Wayfinder\Webhook\WebhookRequest;

final class TransportTest extends TestCase
{
    public function testFakeTransportManagerRecordsAndAssertsTransportActivity(): void
    {
        $manager = TransportManager::fake();

        $manager->mailer()->send(new MailMessage('person@example.com', 'Hello', 'Body'));
        $manager->sms()->send(new SmsMessage('+15555550100', 'Hi'));
        $manager->queue()->dispatch(new ExampleJob());
        $manager->webhooks()->post(new WebhookRequest('https://example.com/hook', ['ok' => true], event: 'survey.completed'));

        self::assertCount(1, $manager->mailer()->sent());
        self::assertCount(1, $manager->sms()->sent());
        self::assertCount(1, $manager->queue()->dispatched());
        self::assertCount(1, $manager->webhooks()->posted());

        $assert = new TransportAssertions($manager);
        $assert->mailer()->sentCount(1);
        $assert->mailer()->sentTo('person@example.com');
        $assert->sms()->sentCount(1);
        $assert->sms()->sentTo('+15555550100');
        $assert->queue()->dispatchedCount(1);
        $assert->queue()->dispatched(ExampleJob::class);
        $assert->webhooks()->postedCount(1);
        $assert->webhooks()->postedEvent('survey.completed');
    }

    public function testScopedFakeTransportsRestoreContainerBindings(): void
    {
        $container = new Container();
        $originalMailer = new FakeMailer();
        $container->instance(Mailer::class, $originalMailer);

        $context = TransportContext::fake($container, function ($context, Container $container) use ($originalMailer): mixed {
            self::assertNotSame($originalMailer, $container->get(Mailer::class));
            self::assertInstanceOf(SmsSender::class, $container->get(SmsSender::class));
            self::assertInstanceOf(QueueBus::class, $container->get(QueueBus::class));
            self::assertInstanceOf(WebhookClient::class, $container->get(WebhookClient::class));
            self::assertInstanceOf(ClockManager::class, $container->get(Clock::class));

            $container->get(Mailer::class)->send(new MailMessage('scoped@example.com', 'Scoped', 'Body'));
            $container->get(Clock::class)->freeze('2026-05-01 09:30:00', 'UTC');

            return $context;
        });

        self::assertSame($originalMailer, $container->get(Mailer::class));
        self::assertSame(1, $context->events->count('mail.sent'));
        self::assertSame('2026-05-01 09:30:00', $context->clock->now()->format('Y-m-d H:i:s'));
    }

    public function testFakeQueueCanExecuteJobsImmediately(): void
    {
        $container = new Container();

        TransportContext::fake($container, static function ($context, Container $container): void {
            $container->get(QueueBus::class)->dispatch(new ExecutableJob());
        }, executeQueuedJobs: true);

        self::assertTrue(ExecutableJob::$handled);
    }

    protected function setUp(): void
    {
        parent::setUp();
        ExecutableJob::$handled = false;
    }
}

final class ExampleJob
{
}

final class ExecutableJob
{
    public static bool $handled = false;

    public function handle(): void
    {
        self::$handled = true;
    }
}
