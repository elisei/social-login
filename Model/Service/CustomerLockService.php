<?php
/**
 * Copyright © O2TI. All rights reserved.
 */
namespace O2TI\SocialLogin\Model\Service;

use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Class CustomerLockService
 * Serviço para gerenciar bloqueio de usuários após tentativas falhas
 */
class CustomerLockService
{
    /**
     * Número máximo de tentativas permitidas
     */
    public const MAX_ATTEMPTS = 3;

    /**
     * Duração do bloqueio em segundos (12 horas)
     */
    public const BLOCK_DURATION = 43200;

    /**
     * @var CustomerFactory
     */
    private $customerFactory;

    /**
     * @var CustomerResource
     */
    private $customerResource;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * Constructor
     *
     * @param CustomerFactory $customerFactory
     * @param CustomerResource $customerResource
     * @param DateTime $dateTime
     */
    public function __construct(
        CustomerFactory $customerFactory,
        CustomerResource $customerResource,
        DateTime $dateTime
    ) {
        $this->customerFactory = $customerFactory;
        $this->customerResource = $customerResource;
        $this->dateTime = $dateTime;
    }

    /**
     * Verifica se o usuário está bloqueado
     *
     * @param int $customerId
     * @return bool
     */
    public function isCustomerBlocked($customerId)
    {
        $customer = $this->getCustomerModel($customerId);
        $lockExpires = $customer->getLockExpires();
        
        if (!$lockExpires) {
            return false;
        }
        
        $currentTime = $this->dateTime->gmtDate();
        return $currentTime < $lockExpires;
    }
    
    /**
     * Obtém o tempo restante de bloqueio em formato legível
     *
     * @param int $customerId
     * @return string|null
     */
    public function getRemainingBlockTime($customerId)
    {
        $customer = $this->getCustomerModel($customerId);
        $lockExpires = $customer->getLockExpires();
        
        if (!$lockExpires) {
            return null;
        }
        
        $currentTime = strtotime($this->dateTime->gmtDate());
        $blockEndTime = strtotime($lockExpires);
        
        if ($currentTime >= $blockEndTime) {
            return null;
        }
        
        $remainingSeconds = $blockEndTime - $currentTime;
        $hours = floor($remainingSeconds / 3600);
        $minutes = floor(($remainingSeconds % 3600) / 60);
        
        return sprintf('%d horas e %d minutos', $hours, $minutes);
    }

    /**
     * Registra uma tentativa falha e bloqueia o usuário se necessário
     *
     * @param int $customerId
     * @return void
     * @throws LocalizedException
     */
    public function registerFailedAttempt($customerId)
    {
        $customer = $this->getCustomerModel($customerId);
        $lockExpires = $customer->getLockExpires();
        $currentTime = $this->dateTime->gmtDate();
        
        // Verifica se o bloqueio já expirou
        if ($lockExpires && $currentTime > $lockExpires) {
            // Bloqueio expirado, reinicia contagem
            $customer->setFailuresNum(1);
            $customer->setLockExpires(null);
            $this->customerResource->save($customer);
            return;
        }
        
        // Se já está bloqueado, mantém o bloqueio
        if ($lockExpires && $currentTime <= $lockExpires) {
            $remainingTime = $this->getRemainingBlockTime($customerId);
            throw new LocalizedException(__(
                'Sua conta está temporariamente bloqueada devido a múltiplas tentativas falhas. ' .
                'Por favor, tente novamente em %1.',
                $remainingTime
            ));
        }
        
        // Incrementa tentativas
        $failedAttempts = (int)$customer->getFailuresNum() + 1;
        $customer->setFailuresNum($failedAttempts);
        
        // Verifica se atingiu o limite de tentativas
        if ($failedAttempts >= self::MAX_ATTEMPTS) {
            // Bloqueia o usuário por 12 horas
            $blockUntil = date(
                'Y-m-d H:i:s',
                $this->dateTime->gmtTimestamp() + self::BLOCK_DURATION
            );
            $customer->setLockExpires($blockUntil);
            
            $this->customerResource->save($customer);
            
            throw new LocalizedException(__(
                'Sua conta foi bloqueada por 12 horas devido a múltiplas tentativas falhas de verificação de código.'
            ));
        }
        
        $this->customerResource->save($customer);
    }

    /**
     * Reseta as tentativas falhas de um usuário após login bem-sucedido
     *
     * @param int $customerId
     * @return void
     */
    public function resetFailedAttempts($customerId)
    {
        $customer = $this->getCustomerModel($customerId);
        $customer->setFailuresNum(0);
        $customer->setLockExpires(null);
        $this->customerResource->save($customer);
    }
    
    /**
     * Obtém o modelo de cliente por ID
     *
     * @param int $customerId
     * @return \Magento\Customer\Model\Customer
     */
    private function getCustomerModel($customerId)
    {
        $customer = $this->customerFactory->create();
        $this->customerResource->load($customer, $customerId);
        return $customer;
    }
}
