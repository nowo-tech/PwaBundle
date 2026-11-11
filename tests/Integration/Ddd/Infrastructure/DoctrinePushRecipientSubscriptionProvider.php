<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Integration\Ddd\Infrastructure;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\PwaBundle\Tests\Integration\Ddd\Domain\StoredPushSubscription;
use Nowo\PwaBundle\WebPush\PushRecipientSubscriptionProviderInterface;
use Nowo\PwaBundle\WebPush\PushSubscriptionData;

/**
 * DDD host adapter: implements only the identifier-keyed port, reads the host's dedicated "push" entity manager and
 * maps rows to the bundle VO. No Symfony security type is involved.
 */
final readonly class DoctrinePushRecipientSubscriptionProvider implements PushRecipientSubscriptionProviderInterface
{
    public function __construct(private EntityManagerInterface $pushEntityManager)
    {
    }

    public function findRecipientSubscriptions(string $userIdentifier): iterable
    {
        $rows = $this->pushEntityManager->getRepository(StoredPushSubscription::class)->findBy(['recipientId' => $userIdentifier], ['id' => 'ASC']);
        $this->pushEntityManager->clear();

        foreach ($rows as $row) {
            yield new PushSubscriptionData($row->endpoint(), $row->p256dh(), $row->authToken());
        }
    }

    public function removeRecipientSubscription(string $userIdentifier, PushSubscriptionData $subscription): void
    {
        $this->pushEntityManager->createQueryBuilder()
            ->delete(StoredPushSubscription::class, 's')
            ->where('s.recipientId = :recipient')
            ->andWhere('s.endpoint = :endpoint')
            ->setParameter('recipient', $userIdentifier)
            ->setParameter('endpoint', $subscription->endpoint)
            ->getQuery()
            ->execute();
    }
}
