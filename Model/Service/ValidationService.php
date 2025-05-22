<?php
namespace O2TI\SocialLogin\Model\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Validator\EmailAddress;

/**
 * Class ValidationService
 * Responsável pela validação de dados
 */
class ValidationService
{
    /**
     * @var EmailAddress
     */
    private $emailValidator;

    /**
     * Constructor
     * 
     * @param EmailAddress $emailValidator
     */
    public function __construct(
        EmailAddress $emailValidator
    ) {
        $this->emailValidator = $emailValidator;
    }

    /**
     * Validate email format
     * 
     * @param string $email
     * @return bool
     * @throws LocalizedException
     */
    public function validateEmail($email)
    {
        if (!$email || !$this->emailValidator->isValid($email)) {
            throw new LocalizedException(__('Invalid email address.'));
        }
        
        return true;
    }
}
