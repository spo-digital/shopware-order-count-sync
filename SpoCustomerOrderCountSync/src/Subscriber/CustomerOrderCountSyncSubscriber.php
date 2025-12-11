<?php declare(strict_types=1);

namespace SpoCustomerOrderCountSync\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginSuccessEvent;
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
            CustomerLoginSuccessEvent::class => 'onCustomerLogin',
        ];
    }

    public function onCustomerLogin(CustomerLoginSuccessEvent $event): void
    {
        $context = $event->getSalesChannelContext()->getContext();
        $customer = $event->getCustomer();

        if (!$customer instanceof CustomerEntity) {
            return;
        }

        $orderCount = $customer->getOrderCount();

        if ($orderCount === null) {
            $criteria = new Criteria([$customer->getId()]);
            $criteria->addFields(['id', 'orderCount']);

            $loadedCustomer = $this->customerRepository->search($criteria, $context)->first();

            if (!$loadedCustomer) {
                $this->logger->warning('Customer not found in repository.', [
                    'customerId' => $customer->getId(),
                ]);
                return;
            }

            $orderCount = $loadedCustomer->getOrderCount();
        }

        if ($orderCount === null) {
            $this->logger->warning('Customer order count is not available.', [
                'customerId' => $customer->getId(),
            ]);
            return;
        }

        $customFields = $customer->getCustomFields() ?? [];
        $customFields['custom_ordercount_copy'] = $orderCount;

        $this->customerRepository->update([
            [
                'id' => $customer->getId(),
                'customFields' => $customFields,
            ],
        ], $context);
    }
}
