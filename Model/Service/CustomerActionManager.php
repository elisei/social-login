<?php
namespace O2TI\SocialLogin\Model\Service;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Customer\Model\Session as CustomerSession;

/**
 * Class CustomerActionManager
 * Responsável pelas ações relacionadas ao cliente
 */
class CustomerActionManager
{
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

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
     * @var SessionManager
     */
    private $sessionManager;

    /**
     * Constructor
     *
     * @param CustomerRepositoryInterface $customerRepository
     * @param CustomerFactory $customerFactory
     * @param CustomerResource $customerResource
     * @param CustomerSession $customerSession
     * @param SessionManager $sessionManager
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        CustomerFactory $customerFactory,
        CustomerResource $customerResource,
        CustomerSession $customerSession,
        SessionManager $sessionManager
    ) {
        $this->customerRepository = $customerRepository;
        $this->customerFactory = $customerFactory;
        $this->customerResource = $customerResource;
        $this->customerSession = $customerSession;
        $this->sessionManager = $sessionManager;
    }

    /**
     * Check if customer account is locked
     *
     * @param int $customerId
     * @return bool
     */
    public function isCustomerLocked(int $customerId): bool
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
     * Login customer 
     *
     * @param int $customerId
     * @return bool
     * @throws LocalizedException
     */
    public function loginCustomer(int $customerId): bool
    {
        try {
            $customer = $this->customerRepository->getById($customerId);
            
            if ($this->isCustomerLocked($customerId)) {
                throw new LocalizedException(__('Your account is locked. Please contact customer support.'));
            }
            
            $this->customerSession->setCustomerDataAsLoggedIn($customer);
            $this->sessionManager->refreshSections($this->customerSession->getCustomer());
            
            return true;
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(__('Customer does not exist.'));
        } catch (\Exception $e) {
            throw new LocalizedException(__('Unable to login: %1', $e->getMessage()));
        }
    }
}
