<?php
namespace O2TI\SocialLogin\Model\Service;

use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Session\Config\ConfigInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;

/**
 * Class SessionManager
 * Responsável pelo gerenciamento de sessão e cookies
 */
class SessionManager
{
    /**
     * @var EventManager
     */
    private $eventManager;

    /**
     * @var ConfigInterface
     */
    private $sessionConfig;

    /**
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     * @var CookieMetadataFactory
     */
    private $cookieMetadataFactory;

    /**
     * Constructor
     *
     * @param EventManager $eventManager
     * @param ConfigInterface $sessionConfig
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     */
    public function __construct(
        EventManager $eventManager,
        ConfigInterface $sessionConfig,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory
    ) {
        $this->eventManager = $eventManager;
        $this->sessionConfig = $sessionConfig;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
    }

    /**
     * Refresh customer sections and clean session cache
     *
     * @param object $customer
     * @return void
     */
    public function refreshSections($customer): void
    {
        $this->dispatchLoginEvents($customer);
        $this->manageCookies();
    }
    
    /**
     * Dispatch customer login events
     *
     * @param object $customer
     * @return void
     */
    private function dispatchLoginEvents($customer): void
    {
        $this->eventManager->dispatch('customer_login', [
            'customer' => $customer
        ]);
        $this->eventManager->dispatch('customer_data_object_login', [
            'customer' => $customer
        ]);
    }
    
    /**
     * Manage session cookies
     *
     * @return void
     */
    private function manageCookies(): void
    {
        $metadata = $this->createCookieMetadata();
        
        $this->cookieManager->setPublicCookie(
            'social-login-refresh-sessions',
            'true',
            $metadata
        );

        if ($this->cookieManager->getCookie('mage-cache-sessid')) {
            $this->cookieManager->deleteCookie('mage-cache-sessid', $metadata);
        }
    }
    
    /**
     * Create cookie metadata
     *
     * @return \Magento\Framework\Stdlib\Cookie\PublicCookieMetadata
     */
    private function createCookieMetadata()
    {
        return $this->cookieMetadataFactory->createPublicCookieMetadata()
            ->setPath($this->sessionConfig->getCookiePath())
            ->setSecure($this->sessionConfig->getCookieSecure())
            ->setDuration($this->sessionConfig->getCookieLifetime());
    }
}
