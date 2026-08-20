<?php
/**
 * Copyright © 2019 O2TI. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace O2TI\SocialLogin\Provider;

use Exception;
use Hybridauth\HybridauthFactory;
use Hybridauth\User\Profile as SocialProfile;
use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\Config\ConfigInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use O2TI\SocialLogin\Model\Service\NewsletterSubscriptionService;

/**
 * Provider class for social login functionality
 */
class Provider
{
    public const CONFIG_PATH_SOCIAL_LOGIN_ENABLED = 'social_login/general/enabled';
    public const CONFIG_PATH_SOCIAL_LOGIN_PROVIDER_ENABLED = 'social_login/general/%s/enabled';
    public const CONFIG_PATH_SOCIAL_LOGIN_PROVIDER_KEY = 'social_login/general/%s/api_key';
    public const CONFIG_PATH_SOCIAL_LOGIN_PROVIDER_SECRET = 'social_login/general/%s/api_secret';
    public const COOKIE_NAME = 'login_redirect';

    /**
     * @var array
     */
    private array $supportedProviders = ['facebook', 'google', 'WindowsLive'];

    /**
     * @var HybridauthFactory
     */
    private $hybridauthFactory;

    /**
     * @var UrlInterface
     */
    private $url;

    /**
     * @var AccountManagementInterface
     */
    private $accountManagement;

    /**
     * @var CustomerInterfaceFactory
     */
    private $customerDataFactory;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CustomerFactory
     */
    private $customerFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var AccountRedirect
     */
    private $accountRedirect;

    /**
     * @var EventManager
     */
    private $eventManager;

    /**
     * @var ConfigInterface
     */
    private $sessionConfig;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     * @var CookieMetadataFactory
     */
    private $cookieMetadataFactory;

    /**
     * @var CustomerUrl
     */
    private $customerUrl;

    /**
     * @var CustomerResource
     */
    private $customerResource;

    /**
     * @var NewsletterSubscriptionService
     */
    private $newsletterSubscriptionService;

    /**
     * Constructor
     *
     * @param HybridauthFactory             $hybridauthFactory
     * @param UrlInterface                  $url
     * @param AccountManagementInterface    $accountManagement
     * @param CustomerInterfaceFactory      $customerDataFactory
     * @param CustomerRepositoryInterface   $customerRepository
     * @param CustomerFactory               $customerFactory
     * @param StoreManagerInterface         $storeManager
     * @param ScopeConfigInterface          $scopeConfig
     * @param ManagerInterface              $messageManager
     * @param AccountRedirect               $accountRedirect
     * @param EventManager                  $eventManager
     * @param ConfigInterface               $sessionConfig
     * @param CustomerSession               $customerSession
     * @param CookieManagerInterface        $cookieManager
     * @param CookieMetadataFactory         $cookieMetadataFactory
     * @param CustomerUrl                   $customerUrl
     * @param CustomerResource              $customerResource
     * @param NewsletterSubscriptionService $newsletterSubscriptionService
     */
    public function __construct(
        HybridauthFactory $hybridauthFactory,
        UrlInterface $url,
        AccountManagementInterface $accountManagement,
        CustomerInterfaceFactory $customerDataFactory,
        CustomerRepositoryInterface $customerRepository,
        CustomerFactory $customerFactory,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        ManagerInterface $messageManager,
        AccountRedirect $accountRedirect,
        EventManager $eventManager,
        ConfigInterface $sessionConfig,
        CustomerSession $customerSession,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        CustomerUrl $customerUrl,
        CustomerResource $customerResource,
        NewsletterSubscriptionService $newsletterSubscriptionService
    ) {
        $this->hybridauthFactory = $hybridauthFactory;
        $this->url = $url;
        $this->accountManagement = $accountManagement;
        $this->customerDataFactory = $customerDataFactory;
        $this->customerRepository = $customerRepository;
        $this->customerFactory = $customerFactory;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->messageManager = $messageManager;
        $this->eventManager = $eventManager;
        $this->accountRedirect = $accountRedirect;
        $this->sessionConfig = $sessionConfig;
        $this->customerSession = $customerSession;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->customerUrl = $customerUrl;
        $this->customerResource = $customerResource;
        $this->newsletterSubscriptionService = $newsletterSubscriptionService;
    }

    /**
     * Get provider configuration
     *
     * @param string $provider
     * @return array
     */
    private function getProvidersConfig(string $provider): array
    {
        return [
            $provider => [
                'enabled' => (bool) $this->scopeConfig->getValue(
                    sprintf(self::CONFIG_PATH_SOCIAL_LOGIN_PROVIDER_ENABLED, $provider),
                    ScopeInterface::SCOPE_STORE
                ),
                'keys' => [
                    'key' => $this->scopeConfig->getValue(
                        sprintf(self::CONFIG_PATH_SOCIAL_LOGIN_PROVIDER_KEY, $provider),
                        ScopeInterface::SCOPE_STORE
                    ),
                    'secret' => $this->scopeConfig->getValue(
                        sprintf(self::CONFIG_PATH_SOCIAL_LOGIN_PROVIDER_SECRET, $provider),
                        ScopeInterface::SCOPE_STORE
                    ),
                ],
            ]
        ];
    }

    /**
     * Get endpoint URL
     *
     * @param string $provider
     * @return string
     */
    private function getEndpoint(string $provider): string
    {
        return $this->url->getUrl('sociallogin/endpoint/index', [
            '_secure' => true,
            'provider' => $provider
        ]);
    }

    /**
     * Create customer from social profile
     *
     * @param SocialProfile $profile
     * @return CustomerInterface
     * @throws LocalizedException
     */
    private function createCustomerFromProfile(SocialProfile $profile): CustomerInterface
    {
        if (empty($profile->email)) {
            throw new LocalizedException(__('Email is required for social login'));
        }

        $customer = $this->customerDataFactory->create();
        $firstName = $profile->firstName ?? '-';
        $lastName = $profile->lastName ?? $firstName;

        $customer->setEmail($profile->email);
        $customer->setFirstname($firstName);
        $customer->setLastname($lastName);

        $store = $this->storeManager->getStore();
        $customer->setStoreId($store->getId());
        $customer->setWebsiteId($store->getWebsiteId());

        return $customer;
    }

    /**
     * Create new customer account
     *
     * @param CustomerInterface $customer
     * @return CustomerInterface
     * @throws LocalizedException
     */
    private function createNewAccount(CustomerInterface $customer): CustomerInterface
    {
        try {
            $customer = $this->accountManagement->createAccount($customer);

            $this->newsletterSubscriptionService->subscribe($customer);

            if ($this->accountManagement->getConfirmationStatus($customer->getId()) ===
                AccountManagementInterface::ACCOUNT_CONFIRMATION_REQUIRED) {
                $this->messageManager->addComplexSuccessMessage(
                    'checkoutConfirmAccountSuccessMessage',
                    [
                        'url' => $this->customerUrl->getEmailConfirmationUrl(
                            $customer->getEmail()
                        )
                    ]
                );
            }
            
            return $customer;
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage(__('Unable to create account: %1', $e->getMessage()));
            throw new LocalizedException(__('Account creation failed'));
        }
    }

    /**
     * Get existing customer or create new one
     *
     * @param SocialProfile $socialProfile
     * @return CustomerInterface
     * @throws LocalizedException
     */
    private function getOrCreateCustomer(SocialProfile $socialProfile): CustomerInterface
    {
        $websiteId = $this->storeManager->getWebsite()->getId();
        $customer = $this->customerFactory->create();
        $customer->setWebsiteId($websiteId);

        if ($socialProfile->email) {
            $customer->loadByEmail($socialProfile->email);
            if ($customer->getId()) {
                return $this->customerRepository->getById($customer->getId());
            }
        }

        return $this->createNewAccount($this->createCustomerFromProfile($socialProfile));
    }

    /**
     * Check if customer account is locked
     *
     * @param int $customerId
     * @return bool
     */
    private function isCustomerLocked(int $customerId): bool
    {
        $customerModel = $this->customerFactory->create();
        $this->customerResource->load($customerModel, $customerId);
        
        $lockExpires = $customerModel->getLockExpires();
        
        if (!$lockExpires) {
            return false;
        }
        
        $now = new \DateTime();
        $lockExpiresDate = new \DateTime($lockExpires);
        
        return ($now < $lockExpiresDate);
    }

    /**
     * Refresh customer sections and clean session cache
     *
     * @return void
     */
    private function refreshSections(): void
    {
        $this->eventManager->dispatch('customer_login', [
            'customer' => $this->customerSession->getCustomer()
        ]);
        $this->eventManager->dispatch('customer_data_object_login', [
            'customer' => $this->customerSession->getCustomer()
        ]);

        $metadata = $this->cookieMetadataFactory->createPublicCookieMetadata()
            ->setPath($this->sessionConfig->getCookiePath())
            ->setSecure($this->sessionConfig->getCookieSecure())
            ->setDuration($this->sessionConfig->getCookieLifetime());

        $this->cookieManager->setPublicCookie(
            'social-login-refresh-sessions',
            'true',
            $metadata
        );

        // Remove mage-cache-sessid cookie if exists
        if ($this->cookieManager->getCookie('mage-cache-sessid')) {
            $this->cookieManager->deleteCookie('mage-cache-sessid', $metadata);
        }
    }

    /**
     * Perform login
     *
     * @param string $provider
     * @return void
     * @throws LocalizedException
     */
    public function login(string $provider): void
    {
        if (!in_array($provider, $this->supportedProviders, true)) {
            throw new LocalizedException(__('Unsupported provider'));
        }

        $hybridAuth = $this->hybridauthFactory->create([
            'config' => [
                'callback' => $this->getEndpoint($provider),
                'providers' => $this->getProvidersConfig($provider),
            ],
        ]);

        $authenticate = $hybridAuth->authenticate($provider);
        if ($authenticate->isConnected()) {
            $customer = $this->getOrCreateCustomer($authenticate->getUserProfile());
            
            // Check if the customer account is locked
            if ($this->isCustomerLocked($customer->getId())) {
                $authenticate->disconnect();
                throw new LocalizedException(__('Your account is locked. Please contact customer support.'));
            }
            
            $this->customerSession->setCustomerDataAsLoggedIn($customer);
            $this->refreshSections();
        }
    }

    /**
     * Set authentication and handle redirect
     *
     * @param string $provider
     * @param bool $isSecure
     * @param string|null $referer
     * @return array
     */
    public function setAutenticateAndReferer(string $provider, bool $isSecure = true, ?string $referer = null): array
    {
        if ($referer) {
            $this->accountRedirect->setRedirectCookie($referer);
        }

        $response = ['redirectUrl' => $this->accountRedirect->getRedirectCookie()];

        try {
            $hybridAuth = $this->hybridauthFactory->create([
                'config' => [
                    'callback' => $this->getEndpoint($provider),
                    'providers' => $this->getProvidersConfig($provider),
                ],
            ]);

            $authenticate = $hybridAuth->authenticate($provider);
            if (!$authenticate->isConnected()) {
                throw new LocalizedException(__('Authentication failed'));
            }

            $customer = $this->getOrCreateCustomer($authenticate->getUserProfile());

            // Check if the customer account is locked
            if ($this->isCustomerLocked($customer->getId())) {
                $authenticate->disconnect();
                throw new LocalizedException(__('Your account is locked. Please contact customer support.'));
            }

            $this->customerSession->setCustomerDataAsLoggedIn($customer);
            $this->refreshSections();

        } catch (Exception $e) {
            $this->messageManager->addErrorMessage(__('Unable to login: %1', $e->getMessage()));
        }

        return $response;
    }
}
