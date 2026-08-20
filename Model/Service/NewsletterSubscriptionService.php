<?php
/**
 * Copyright © 2019 O2TI. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace O2TI\SocialLogin\Model\Service;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Newsletter\Model\SubscriptionManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

/**
 * Class NewsletterSubscriptionService
 * Subscribes customers created through social login to the newsletter
 */
class NewsletterSubscriptionService
{
    public const CONFIG_PATH_AUTO_SUBSCRIBE = 'social_login/general/auto_subscribe_newsletter';

    /**
     * @var SubscriptionManagerInterface
     */
    private $subscriptionManager;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor
     *
     * @param SubscriptionManagerInterface $subscriptionManager
     * @param ScopeConfigInterface         $scopeConfig
     * @param LoggerInterface              $logger
     */
    public function __construct(
        SubscriptionManagerInterface $subscriptionManager,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->subscriptionManager = $subscriptionManager;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    /**
     * Check if auto subscription is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH_AUTO_SUBSCRIBE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Subscribe customer to the newsletter
     *
     * Failures are logged and never interrupt the account creation flow.
     *
     * @param CustomerInterface $customer
     * @return bool
     */
    public function subscribe(CustomerInterface $customer): bool
    {
        $storeId = (int) $customer->getStoreId();

        if (!$this->isEnabled($storeId)) {
            return false;
        }

        $customerId = (int) $customer->getId();
        if (!$customerId) {
            return false;
        }

        try {
            $this->subscriptionManager->subscribeCustomer($customerId, $storeId);

            return true;
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf(
                    'Social Login: unable to subscribe customer %d to the newsletter: %s',
                    $customerId,
                    $e->getMessage()
                )
            );

            return false;
        }
    }
}
