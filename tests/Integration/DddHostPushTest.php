<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PwaBundle\Tests\Integration\Ddd\DddPushTestKernel;
use Nowo\PwaBundle\Tests\Integration\Ddd\Domain\StoredPushSubscription;
use Nowo\PwaBundle\Tests\Integration\Ddd\Infrastructure\DoctrinePushRecipientSubscriptionProvider;
use Nowo\PwaBundle\Tests\Integration\Ddd\Infrastructure\FixedCsrfTokenManager;
use Nowo\PwaBundle\Tests\Integration\Ddd\Infrastructure\RecordingWebPushSender;
use Nowo\PwaBundle\WebPush\PushSubscriptionProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Security\Core\User\UserProviderInterface;

use function gc_collect_cycles;
use function is_subclass_of;
use function json_decode;
use function memory_get_usage;
use function sys_get_temp_dir;

use const JSON_THROW_ON_ERROR;

/**
 * REQ-ARCH-001 DDD host: the push test endpoint with a host that implements only the identifier-keyed port, stores
 * subscriptions on a dedicated entity manager and references users by id. REQ-RUNTIME-002: the same kernel serves many
 * requests alternating users without the services resetter.
 */
final class DddHostPushTest extends TestCase
{
    private const ALICE      = 'https://fcm.googleapis.com/fcm/send/alice-1';
    private const ALICE_GONE = 'https://updates.push.services.mozilla.com/wpush/v2/alice-gone';
    private const BOB        = 'https://fcm.googleapis.com/fcm/send/bob-1';

    /** Requests of the kept-alive worker test (alice, bob and a guest in turn: 500 each). */
    private const WORKER_ROUNDS = 1500;

    /** Allowed memory growth after the warm-up rounds. */
    private const MEMORY_MARGIN_BYTES = 2 * 1024 * 1024;

    private ?DddPushTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        (new Filesystem())->remove(sys_get_temp_dir() . '/nowo-pwa-ddd-kernel');
    }

    public function testHostImplementsOnlyTheIdentifierPort(): void
    {
        self::assertFalse(is_subclass_of(DoctrinePushRecipientSubscriptionProvider::class, PushSubscriptionProviderInterface::class));
    }

    public function testPushesOnlyToTheRecipientsOwnSubscriptionsOnTheDedicatedEntityManager(): void
    {
        $client = $this->client(true);

        [$status, $body] = $this->post($client, 'alice');
        self::assertSame(200, $status);
        self::assertTrue($body['ok']);
        self::assertSame([self::ALICE, self::ALICE_GONE], $this->sender($client)->take());
        self::assertSame([self::ALICE], $this->storedEndpoints($client, 'alice'), 'the expired subscription is removed through the port');
        self::assertSame([self::BOB], $this->storedEndpoints($client, 'bob'), 'another recipient is never touched');

        [$status] = $this->post($client, 'bob');
        self::assertSame(200, $status);
        self::assertSame([self::BOB], $this->sender($client)->take());

        [$status, $body] = $this->post($client, null);
        self::assertSame(401, $status);
        self::assertSame('unauthenticated', $body['error']);
        self::assertSame([], $this->sender($client)->take());

        // The default entity manager maps nothing of the host push storage.
        /** @var ManagerRegistry $doctrine */
        $doctrine = $client->getContainer()->get('doctrine');
        $default  = $doctrine->getManager('default');
        self::assertInstanceOf(EntityManagerInterface::class, $default);
        self::assertFalse($default->getMetadataFactory()->hasMetadataFor(StoredPushSubscription::class));
        self::assertFalse($default->getConnection()->createSchemaManager()->tablesExist(['host_push_subscription']));
    }

    public function testKeptAliveWorkerWithoutServicesResetterNeverMixesRecipients(): void
    {
        $client = $this->client(false);
        self::assertFalse($client->getContainer()->has('services_resetter'));

        $expected = ['alice' => [self::ALICE], 'bob' => [self::BOB], '' => []];
        $baseline = 0;
        $users    = ['alice', 'bob', null];
        $this->post($client, 'alice'); // drops alice's expired subscription
        $this->sender($client)->take();

        for ($round = 0; $round < self::WORKER_ROUNDS; ++$round) {
            $user            = $users[$round % 3];
            [$status, $body] = $this->post($client, $user);
            self::assertSame($user === null ? 401 : 200, $status, 'round ' . $round);
            self::assertSame($expected[$user ?? ''], $this->sender($client)->take(), 'round ' . $round);
            if ($user !== null) {
                self::assertCount(1, $body['results']);
            }

            if ($round === 30) {
                gc_collect_cycles();
                $baseline = memory_get_usage();
            }
        }

        gc_collect_cycles();
        self::assertLessThan($baseline + self::MEMORY_MARGIN_BYTES, memory_get_usage());
    }

    private function client(bool $servicesResetter): KernelBrowser
    {
        (new Filesystem())->remove(sys_get_temp_dir() . '/nowo-pwa-ddd-kernel');
        $this->kernel = new DddPushTestKernel($servicesResetter);
        $this->kernel->boot();

        $client = new KernelBrowser($this->kernel);
        $client->disableReboot();

        $em = $this->pushEntityManager($client);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        $em->persist(new StoredPushSubscription('alice', self::ALICE, 'p', 'a'));
        $em->persist(new StoredPushSubscription('alice', self::ALICE_GONE, 'p', 'a'));
        $em->persist(new StoredPushSubscription('bob', self::BOB, 'p', 'a'));
        $em->flush();
        $em->clear();

        return $client;
    }

    /**
     * @return array{int, array<string, mixed>}
     */
    private function post(KernelBrowser $client, ?string $user): array
    {
        $client->getCookieJar()->clear();
        $client->getHistory()->clear();
        if ($user !== null) {
            // The in-memory provider gives its users a random password: log the provider's own user in.
            $provider = $client->getContainer()->get('security.user.provider.concrete.users');
            self::assertInstanceOf(UserProviderInterface::class, $provider);
            $client->loginUser($provider->loadUserByIdentifier($user));
        }

        $client->request('POST', '/pwa/push/test', server: ['HTTP_X_CSRF_TOKEN' => FixedCsrfTokenManager::VALUE]);
        $response = $client->getResponse();

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return [$response->getStatusCode(), $body];
    }

    private function sender(KernelBrowser $client): RecordingWebPushSender
    {
        $sender = $client->getContainer()->get(RecordingWebPushSender::class);
        self::assertInstanceOf(RecordingWebPushSender::class, $sender);

        return $sender;
    }

    private function pushEntityManager(KernelBrowser $client): EntityManagerInterface
    {
        /** @var ManagerRegistry $doctrine */
        $doctrine = $client->getContainer()->get('doctrine');
        $em       = $doctrine->getManager('push');
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }

    /** @return list<string> */
    private function storedEndpoints(KernelBrowser $client, string $recipient): array
    {
        $em   = $this->pushEntityManager($client);
        $rows = $em->getRepository(StoredPushSubscription::class)->findBy(['recipientId' => $recipient], ['id' => 'ASC']);
        $em->clear();

        $endpoints = [];
        foreach ($rows as $row) {
            $endpoints[] = $row->endpoint();
        }

        return $endpoints;
    }
}
