<?php
/**
 * Copyright © O2TI. All rights reserved.
 */
namespace O2TI\SocialLogin\Model\Service;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Integration\Model\Oauth\Token;
use Magento\Integration\Model\Oauth\TokenFactory;
use Magento\Integration\Model\ResourceModel\Oauth\Token as TokenResourceModel;
use Magento\Integration\Model\Oauth\Token\RequestThrottler;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

/**
 * Serviço para geração de token JWT do cliente sem exigir senha
 */
class CustomerTokenGenerator
{
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var EventManager
     */
    private $eventManager;

    /**
     * @var RequestThrottler
     */
    private $requestThrottler;

    /**
     * @var TokenFactory
     */
    private $tokenFactory;

    /**
     * @var TokenResourceModel
     */
    private $tokenResourceModel;

    /**
     * @var DateTime
     */
    private $dateTime;
    
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param CustomerRepositoryInterface $customerRepository
     * @param EventManager $eventManager
     * @param RequestThrottler $requestThrottler
     * @param TokenFactory $tokenFactory
     * @param TokenResourceModel $tokenResourceModel
     * @param DateTime $dateTime
     * @param LoggerInterface $logger
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        EventManager $eventManager,
        RequestThrottler $requestThrottler,
        TokenFactory $tokenFactory,
        TokenResourceModel $tokenResourceModel,
        DateTime $dateTime,
        LoggerInterface $logger
    ) {
        $this->customerRepository = $customerRepository;
        $this->eventManager = $eventManager;
        $this->requestThrottler = $requestThrottler;
        $this->tokenFactory = $tokenFactory;
        $this->tokenResourceModel = $tokenResourceModel;
        $this->dateTime = $dateTime;
        $this->logger = $logger;
    }

    /**
     * Gera um token JWT para o cliente sem exigir senha
     *
     * @param int $customerId
     * @param string $email
     * @return string
     * @throws LocalizedException
     */
    public function generateCustomerToken($customerId, $email)
    {
        try {
            $this->logger->info("Iniciando geração de token para cliente ID: " . $customerId);
            
            $customerDataObject = $this->customerRepository->getById($customerId);
            
            $this->eventManager->dispatch('customer_login', ['customer' => $customerDataObject]);
            
            $this->requestThrottler->resetAuthenticationFailuresCount($email, RequestThrottler::USER_TYPE_CUSTOMER);
            
            $this->revokeCustomerTokens($customerId);
            
            /** @var Token $token */
            $token = $this->tokenFactory->create();
            $tokenData = $token->createCustomerToken($customerId);
            
            if (!$tokenData) {
                throw new LocalizedException(__('Generated token is empty'));
            }

            return $tokenData;
        } catch (\Exception $e) {
            throw new LocalizedException(__('Could not generate customer token: %1', $e->getMessage()));
        }
    }

    /**
     * Revoga tokens existentes do cliente
     *
     * @param int $customerId
     * @return void
     */
    private function revokeCustomerTokens($customerId)
    {
        try {
            /** @var Token $token */
            $token = $this->tokenFactory->create();
            $token->revokeCustomerAccessToken($customerId);
        } catch (\Exception $e) {
            $this->logger->error("Erro ao revogar tokens: " . $e->getMessage());
        }
    }
}
