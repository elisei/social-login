<?php
/**
 * Copyright © O2TI. All rights reserved.
 */
namespace O2TI\SocialLogin\Model\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\Random;
use Magento\Framework\Stdlib\DateTime\DateTime;
use O2TI\SocialLogin\Model\VerificationCodeFactory;
use O2TI\SocialLogin\Model\Service\CustomerLockService;
use O2TI\SocialLogin\Model\ResourceModel\VerificationCode as VerificationCodeResource;
use O2TI\SocialLogin\Model\ResourceModel\VerificationCode\CollectionFactory as VerificationCodeCollectionFactory;

class VerificationCodeService
{
    /**
     * Verification code length
     */
    private const CODE_LENGTH = 6;

    /**
     * Code expiration time in seconds (15 minutes)
     */
    private const CODE_EXPIRATION_TIME = 900;

    /**
     * @var Random
     */
    private $mathRandom;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var VerificationCodeFactory
     */
    private $verificationCodeFactory;

    /**
     * @var VerificationCodeResource
     */
    private $verificationCodeResource;

    /**
     * @var VerificationCodeCollectionFactory
     */
    private $verificationCodeCollectionFactory;

    /**
     * @var CustomerLockService
     */
    private $customerLockService;

    /**
     * Constructor
     *
     * @param Random $mathRandom
     * @param DateTime $dateTime
     * @param VerificationCodeFactory $verificationCodeFactory
     * @param VerificationCodeResource $verificationCodeResource
     * @param VerificationCodeCollectionFactory $verificationCodeCollectionFactory
     * @param CustomerLockService $customerLockService
     */
    public function __construct(
        Random $mathRandom,
        DateTime $dateTime,
        VerificationCodeFactory $verificationCodeFactory,
        VerificationCodeResource $verificationCodeResource,
        VerificationCodeCollectionFactory $verificationCodeCollectionFactory,
        CustomerLockService $customerLockService
    ) {
        $this->mathRandom = $mathRandom;
        $this->dateTime = $dateTime;
        $this->verificationCodeFactory = $verificationCodeFactory;
        $this->verificationCodeResource = $verificationCodeResource;
        $this->verificationCodeCollectionFactory = $verificationCodeCollectionFactory;
        $this->customerLockService = $customerLockService;
    }

    /**
     * Generate verification code
     *
     * @return string
     */
    public function generateCode()
    {
        return str_pad(
            (string)random_int(0, pow(10, self::CODE_LENGTH) - 1),
            self::CODE_LENGTH,
            '0',
            STR_PAD_LEFT
        );
    }

    /**
     * Create verification code for customer
     *
     * @param int $customerId
     * @param string $email
     * @param string|null $referer
     * @return string
     * @throws LocalizedException
     */
    public function createVerificationCode($customerId, $email, $referer = null)
    {

        if ($this->customerLockService->isCustomerBlocked($customerId)) {
            $remainingTime = $this->customerLockService->getRemainingBlockTime($customerId);
            throw new LocalizedException(__(
                'Sua conta está temporariamente bloqueada devido a múltiplas tentativas falhas. ' .
                'Por favor, tente novamente em %1.',
                $remainingTime
            ));
        }

        $this->invalidateExistingCodes($customerId);

        $code = $this->generateCode();
        $expiresAt = $this->calculateExpirationTime();

        $verificationCode = $this->verificationCodeFactory->create();
        $verificationCode->setData([
            'customer_id' => $customerId,
            'email' => $email,
            'verification_code' => $code,
            'referer' => $referer,
            'created_at' => $this->dateTime->gmtDate(),
            'expires_at' => $expiresAt,
            'is_used' => 0
        ]);

        try {
            $this->verificationCodeResource->save($verificationCode);
            return $code;
        } catch (\Exception $e) {
            throw new LocalizedException(__('Unable to generate verification code: %1', $e->getMessage()));
        }
    }

    /**
     * Invalidate existing active codes
     *
     * @param int $customerId
     * @return void
     */
    private function invalidateExistingCodes($customerId)
    {
        $collection = $this->verificationCodeCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('is_used', 0)
            ->addFieldToFilter('expires_at', ['gt' => $this->dateTime->gmtDate()]);

        foreach ($collection as $code) {
            $code->setData('is_used', 1);
            $this->verificationCodeResource->save($code);
        }
    }

    /**
     * Calculate expiration time
     *
     * @return string
     */
    private function calculateExpirationTime()
    {
        return date(
            'Y-m-d H:i:s',
            $this->dateTime->gmtTimestamp() + self::CODE_EXPIRATION_TIME
        );
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
        $collection = $this->verificationCodeCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('verification_code', $code)
            ->addFieldToFilter('is_used', 0)
            ->addFieldToFilter('expires_at', ['gt' => $this->dateTime->gmtDate()]);

        if ($collection->getSize() === 0) {
            $this->customerLockService->registerFailedAttempt($customerId);
            throw new LocalizedException(__('Código de verificação inválido ou expirado. Por favor, tente novamente.'));
        }

        $verificationCode = $collection->getFirstItem();
        
        // Mark code as used
        $verificationCode->setData('is_used', 1);
        $this->verificationCodeResource->save($verificationCode);
        $this->customerLockService->resetFailedAttempts($customerId);

        return [
            'success' => true,
            'email' => $verificationCode->getData('email'),
            'referer' => $verificationCode->getData('referer')
        ];
    }
}
