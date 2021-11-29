<?php
/**
 * Copyright © 2019 O2TI. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace O2TI\SocialLogin\Provider;

use Exception;
use Hybridauth\HybridauthFactory;
use Hybridauth\User\Profile as SocialProfile;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Provider section.
 *
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class Provider
{
    public const CONFIG_PATH_SOCIAL_LOGIN_ENABLED = 'social_login/general/enabled';
    public const CONFIG_PATH_SOCIAL_LOGIN_PROVIDER_ENABLED = 'social_login/general/%s/enabled';
    public const CONFIG_PATH_SOCIAL_LOGIN_PROVIDER_KEY = 'social_login/general/%s/api_key';
    public const CONFIG_PATH_SOCIAL_LOGIN_PROVIDER_SECRET = 'social_login/general/%s/api_secret';
    public const COOKIE_NAME = 'login_redirect';

    /**
     * The providers we currently support.
     */
    public const PROVIDERS = [
        'facebook',
        'google',
        'WindowsLive',
    ];

    /**
     * @var HybridauthFactory
     */
    private $hybridauthFactory;

    /**
     * @var UrlInterface
     */
    private $url;

    /**
     * @var CustomerFactory
     */
    private $customerFactory;

    /**
     * @var CustomerResource
     */
    private $customerResource;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     * @var CookieMetadataFactory
     */
    private $cookieMetadataFactory;

    /**
     * @var SessionManagerInterface
     */
    private $sessionManager;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var AccountRedirect
     */
    protected $accountRedirect;

    /**
     * @param HybridauthFactory           $hybridauthFactory
     * @param UrlInterface                $url
     * @param CustomerFactory             $customerFactory
     * @param CustomerResource            $customerResource
     * @param CustomerSession|null        $customerSession
     * @param StoreManagerInterface       $storeManager
     * @param ScopeConfigInterface        $scopeConfig
     * @param CookieManagerInterface      $cookieManager
     * @param CookieMetadataFactory       $cookieMetadataFactory
     * @param SessionManagerInterface     $sessionManager
     * @param CustomerRepositoryInterface $customerRepository
     * @param ManagerInterface            $messageManager
     * @param AccountRedirect             $accountRedirect
     */
    public function __construct(
        HybridauthFactory $hybridauthFactory,
        UrlInterface $url,
        CustomerFactory $customerFactory,
        CustomerResource $customerResource,
        ?CustomerSession $customerSession = null,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        CookieManagerInterface $cookieManager = null,
        CookieMetadataFactory $cookieMetadataFactory = null,
        SessionManagerInterface $sessionManager,
        CustomerRepositoryInterface $customerRepository,
        ManagerInterface $messageManager,
        AccountRedirect $accountRedirect
    ) {
        $this->hybridauthFactory = $hybridauthFactory;
        $this->url = $url;
        $this->customerFactory = $customerFactory;
        $this->customerResource = $customerResource;
        $this->customerSession = $customerSession ?? ObjectManager::getInstance()->get(CustomerSession::class);
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->cookieManager = $cookieManager ?:
            ObjectManager::getInstance()->get(CookieManagerInterface::class);
        $this->cookieMetadataFactory = $cookieMetadataFactory ?:
            ObjectManager::getInstance()->get(CookieMetadataFactory::class);
        $this->sessionManager = $sessionManager;
        $this->customerRepository = $customerRepository;
        $this->messageManager = $messageManager;
        $this->accountRedirect = $accountRedirect ?: ObjectManager::getInstance()->get(AccountRedirect::class);
    }

    /**
     * Implements Config Module.
     *
     * @param string $provider
     *
     * @return array
     */
    private function getProvidersConfig(string $provider): array
    {
        $config = [];
        $config[$provider] = [
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
        ];

        return $config;
    }

    /**
     * Generate Url Endpoint.
     *
     * @param string $provider
     *
     * @return string
     */
    private function getEndpoint(string $provider): string
    {
        $params = [
            '_secure'  => true,
            'provider' => $provider,
        ];

        return $this->url->getUrl('sociallogin/endpoint/index', $params);
    }

    /**
     * Gets customer data for a hybrid auth profile.
     *
     * @param SocialProfile $profile
     *
     * @return array
     */
    private function getCustomerData(SocialProfile $profile): array
    {
        $customerData = [];
        foreach (['firstName', 'lastName', 'email'] as $field) {
            $data = $profile->{$field};
            $customerData[strtolower($field)] = $data !== null ? $data : '-';
        }

        return $customerData;
    }

    /**
     * Set Customer.
     *
     * @param SocialProfile $socialProfile
     *
     * @return CustomerFactory
     */
    private function setCustomerData(SocialProfile $socialProfile)
    {
        $websiteId = $this->storeManager->getWebsite()->getId();
        $customer = $this->customerFactory->create();
        $customer->setWebsiteId($websiteId);
        if ($socialProfile->email) {
            $customer->loadByEmail($socialProfile->email);

            if (!$customer->getId()) {
                $customer->setData('email', $socialProfile->email);
                $customer->addData($this->getCustomerData($socialProfile));

                try {
                    $customer = $this->customerResource->save($customer);
                } catch (Exception $exc) {
                    $this->messageManager->addError(__('Unable to create account.'));
                    $this->messageManager->addError(__($exc->getMessage()));

                    return $customer;
                }
            }
        }

        return $customer;
    }

    /**
     * Login account.
     *
     * @param string $provider
     *
     * @throws LocalizedException
     *
     * @return void
     */
    public function login(string $provider): void
    {
        $hybridAuth = $this->hybridauthFactory->create([
            'config' => [
                'callback'  => $this->getEndpoint($provider),
                'providers' => $this->getProvidersConfig($provider),
            ],
        ]);
        $authenticate = $hybridAuth->authenticate($provider);
        if ($authenticate->isConnected()) {
            $socialProfile = $authenticate->getUserProfile();
            $customer = $this->setCustomerData($socialProfile);
            $this->customerSession->setCustomerDataAsLoggedIn($customer);

            if ($this->cookieManager->getCookie('mage-cache-sessid')) {
                $metadata = $this->cookieMetadataFactory->createCookieMetadata();
                $metadata->setPath('/');
                $this->cookieManager->deleteCookie('mage-cache-sessid', $metadata);
            }
        }
    }

    /**
     * Set Autenticate And Referer.
     *
     * @param string      $provider
     * @param int|null    $isSecure
     * @param string|null $referer
     *
     * @return array
     */
    public function setAutenticateAndReferer(string $provider, int $isSecure = 1, string $referer = null): array
    {
        if ($referer) {
            $this->accountRedirect->setRedirectCookie($referer);
        }

        $redirect = $this->accountRedirect->getRedirectCookie();

        $response['redirectUrl'] = $redirect;

        $hybridAuth = $this->hybridauthFactory->create([
            'config' => [
                'callback'  => $this->getEndpoint($provider),
                'providers' => $this->getProvidersConfig($provider),
            ],
        ]);

        try {
            $authenticate = $hybridAuth->authenticate($provider);
        } catch (Exception $e) {
            $this->messageManager->addError(__('Unable to login, try another way.'));

            return $response;
        }

        if ($authenticate->isConnected()) {
            $socialProfile = $authenticate->getUserProfile();
            $customer = $this->setCustomerData($socialProfile);
            if ($customer->getId()) {
                $this->customerSession->setCustomerAsLoggedIn($customer);
                $this->customerSession->getCustomerFormData(true);
                $customerId = $this->customerSession->getCustomerId();
                $customerDataObject = $this->customerRepository->getById($customerId);

                $this->customerSession->setCustomerDataAsLoggedIn($customerDataObject);

                if ($this->cookieManager->getCookie('mage-cache-sessid')) {
                    $metadata = $this->cookieMetadataFactory->createCookieMetadata();
                    $metadata->setPath('/');
                    $this->cookieManager->deleteCookie('mage-cache-sessid', $metadata);
                }

                return $response;
            }

            $this->messageManager->addError(__('Unable to login, try another way.'));

            return $response;
        }
    }
}
