<?php
/**
 * Copyright © O2TI. All rights reserved.
 */
namespace O2TI\SocialLogin\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use O2TI\SocialLogin\Model\Service\VerificationCodeService;
use O2TI\SocialLogin\Model\Service\EmailService;
use O2TI\SocialLogin\Model\Service\CustomerActionManager;
use O2TI\SocialLogin\Model\Service\ValidationService;

/**
 * Class VerifyCode
 * Coordena os serviços para gerenciamento de login por código de verificação
 */
class VerifyCode
{
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;
    
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    
    /**
     * @var CollectionFactory
     */
    private $customerCollectionFactory;

    /**
     * @var VerificationCodeService
     */
    private $verificationCodeService;

    /**
     * @var EmailService
     */
    private $emailService;

    /**
     * @var CustomerActionManager
     */
    private $customerActionManager;

    /**
     * @var ValidationService
     */
    private $validationService;

    /**
     * Constructor
     *
     * @param CustomerRepositoryInterface $customerRepository
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param CollectionFactory $customerCollectionFactory
     * @param VerificationCodeService $verificationCodeService
     * @param EmailService $emailService
     * @param CustomerActionManager $customerActionManager
     * @param ValidationService $validationService
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        CollectionFactory $customerCollectionFactory,
        VerificationCodeService $verificationCodeService,
        EmailService $emailService,
        CustomerActionManager $customerActionManager,
        ValidationService $validationService
    ) {
        $this->customerRepository = $customerRepository;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->verificationCodeService = $verificationCodeService;
        $this->emailService = $emailService;
        $this->customerActionManager = $customerActionManager;
        $this->validationService = $validationService;
    }

    /**
     * Get customer ID by email
     *
     * @param string $email
     * @return int|null
     */
    public function getCustomerIdByEmail($email)
    {
        try {
            $customerCollection = $this->customerCollectionFactory->create();
            $customerCollection->addFieldToFilter('email', $email);
            $customer = $customerCollection->getFirstItem();
            
            return $customer->getId() ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Process verification code request
     *
     * @param string $email
     * @param string $referer
     * @return array
     */
    public function processVerificationCodeRequest($email, $referer = 'account')
    {
        try {
            $this->validationService->validateEmail($email);
            
            $customerId = $this->getCustomerIdByEmail($email);
            
            if (!$customerId) {
                throw new LocalizedException(__('No customer account found with this email address.'));
            }
            
            $code = $this->verificationCodeService->createVerificationCode($customerId, $email, $referer);
            
            $this->emailService->sendVerificationCodeEmail($email, $code, $referer);
            
            return [
                'success' => true,
                'message' => __('Verification code was sent successfully! Please check your email.')
            ];
        } catch (LocalizedException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => __('An error occurred while processing your request. Please try again later.')
            ];
        }
    }
    
    /**
     * Validate verification code by email
     *
     * @param string $email
     * @param string $code
     * @return array
     * @throws LocalizedException
     */
    public function validateCodeByEmail($email, $code)
    {
        return $this->verificationCodeService->validateCodeByEmail($email, $code);
    }
    
    /**
     * Validate verification code
     *
     * @param int $customerId
     * @param string $code
     * @return array
     * @throws LocalizedException
     */
    public function validateCode($customerId, $code)
    {
        return $this->verificationCodeService->validateCode($customerId, $code);
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
        return $this->customerActionManager->loginCustomer($customerId);
    }
}
