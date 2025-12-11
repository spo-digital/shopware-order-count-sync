<?php declare(strict_types=1);

namespace SpoCustomerOrderCountSync\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Customer\CustomerEntity;
use Psr\Log\LoggerInterface;

class CustomerOrderCountSyncSubscriber implements EventSubscriberInterface
{
    private EntityRepository $customerRepository;
    private LoggerInterface $logger;

    public function __construct(EntityRepository $customerRepository, LoggerInterface $logger)
    {
        $this->customerRepository = $customerRepository;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerLoginEvent::class => 'onCustomerLogin',
        ];
    }

    public function onCustomerLogin(CustomerLoginEvent $event): void
    {
        $context = $event->getSalesChannelContext()->getContext();
        $customer = $event->getSalesChannelContext()->getCustomer();

        if (!$customer instanceof CustomerEntity) {
            return;
        }

        // Lade Customer mit order_count
        $criteria = new Criteria([$customer->getId()]);
        $criteria->addFilter(new EqualsFilter('id', $customer->getId()));
        $criteria->addFields(['id', 'orderCount']);

        $result = $this->customerRepository->search($criteria, $context)->first();

        if (!$result) {
            $this->logger->warning('Customer not found in repository.', [
                'customerId' => $customer->getId()
            ]);
            return;
        }

        $orderCount = $result->getOrderCount();

        // 📝 Logge den geladenen Wert
        $this->logger->info('Order count loaded', [
            'customerId' => $customer->getId(),
            'orderCount' => $orderCount,
        ]);

        $customFields = $customer->getCustomFields() ?? [];
        $customFields['custom_ordercount_copy'] = $orderCount;

        $this->customerRepository->update([[
            'id' => $customer->getId(),
            'customFields' => $customFields,
        ]], $context);
    }
}
