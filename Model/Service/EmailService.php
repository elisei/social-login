<?php
/**
 * Copyright © O2TI. All rights reserved.
 */
namespace O2TI\SocialLogin\Model\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Mail\TransportInterface;

/**
 * Class EmailService
 * Responsável pelo envio de emails com códigos de verificação
 */
class EmailService
{
    /**
     * Configuration paths for email settings
     */
    public const XML_PATH_EMAIL_ENABLED = 'social_login/general/verify_code/enabled';
    public const XML_PATH_EMAIL_TEMPLATE = 'social_login/general/verify_code/email_verify/template';
    public const XML_PATH_EMAIL_IDENTITY = 'social_login/general/verify_code/email_verify/email_identity';
    public const XML_PATH_EMAIL_COPY_METHOD = 'social_login/general/verify_code/email_verify/copy_method';
    public const XML_PATH_EMAIL_COPY_TO = 'social_login/general/verify_code/email_verify/copy_to';
    public const XML_PATH_EXPIRATION = 'social_login/general/verify_code/link_expiration';
    
    /**
     * Default template ID if config is not set
     */
    public const DEFAULT_EMAIL_TEMPLATE = 'social_login_verification_template';

    /**
     * @var TransportBuilder
     */
    private $transportBuilder;
    
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Constructor
     *
     * @param TransportBuilder $transportBuilder
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Send verification code email
     *
     * @param string $email
     * @param string $code
     * @param string $referer
     * @return bool
     * @throws LocalizedException
     */
    public function sendVerificationCodeEmail($email, $code, $referer = 'account')
    {
        try {
            $storeId = $this->storeManager->getStore()->getId();
            
            if (!$this->isEmailEnabled($storeId)) {
                return false;
            }
            
            $templateId = $this->getEmailTemplate($storeId);
            $from = $this->getSenderIdentity($storeId);
            
            $templateVars = [
                'code' => $code,
                'expiration' => $this->getExpirationTime($storeId),
                'store' => $this->storeManager->getStore(),
                'action_type' => $referer
            ];
            
            // Send email to customer
            $this->sendEmail($templateId, $email, $templateVars, $from, $storeId);
            
            // Send copy emails if configured
            $this->sendCopyEmails($templateId, $templateVars, $from, $storeId);
            
            return true;
        } catch (LocalizedException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new LocalizedException(__('Unable to send email: %1', $e->getMessage()));
        }
    }

    /**
     * Check if email sending is enabled
     *
     * @param int $storeId
     * @return bool
     */
    private function isEmailEnabled($storeId)
    {
        return (bool)$this->scopeConfig->getValue(
            self::XML_PATH_EMAIL_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get Expiration time
     *
     * @param int $storeId
     * @return bool
     */
    private function getExpirationTime($storeId)
    {
        return (bool)$this->scopeConfig->getValue(
            self::XML_PATH_EXPIRATION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get email template ID
     *
     * @param int $storeId
     * @return string
     */
    private function getEmailTemplate($storeId)
    {
        $templateId = $this->scopeConfig->getValue(
            self::XML_PATH_EMAIL_TEMPLATE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        if (empty($templateId)) {
            return self::DEFAULT_EMAIL_TEMPLATE;
        }
        
        return (string)$templateId;
    }
    
    /**
     * Get sender identity
     *
     * @param int $storeId
     * @return array
     */
    private function getSenderIdentity($storeId)
    {
        $identity = $this->scopeConfig->getValue(
            self::XML_PATH_EMAIL_IDENTITY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if (empty($identity)) {
            $identity = 'general';
        }

        $email = $this->getIdentityEmail($storeId, $identity);
        $name = $this->getIdentityName($storeId, $identity);

        if (empty($email) || empty($name)) {
            $email = $this->scopeConfig->getValue(
                'trans_email/ident_general/email',
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
            
            $name = $this->scopeConfig->getValue(
                'trans_email/ident_general/name',
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        
        return [
            'email' => $email,
            'name' => $name
        ];
    }
    
    /**
     * Get identity email
     *
     * @param int $storeId
     * @param string $identity
     * @return string
     */
    private function getIdentityEmail($storeId, $identity)
    {
        return $this->scopeConfig->getValue(
            'trans_email/ident_' . $identity . '/email',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get identity name
     *
     * @param int $storeId
     * @param string $identity
     * @return string
     */
    private function getIdentityName($storeId, $identity)
    {
        return $this->scopeConfig->getValue(
            'trans_email/ident_' . $identity . '/name',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Send email to recipient
     *
     * @param string $templateId
     * @param string $email
     * @param array $templateVars
     * @param array $from
     * @param int $storeId
     * @return void
     * @throws MailException
     */
    private function sendEmail($templateId, $email, $templateVars, $from, $storeId)
    {
        $transport = $this->transportBuilder
            ->setTemplateIdentifier($templateId)
            ->setTemplateOptions(['area' => 'frontend', 'store' => $storeId])
            ->setTemplateVars($templateVars)
            ->setFrom($from)
            ->addTo($email)
            ->getTransport();
        
        $transport->sendMessage();
    }
    
    /**
     * Send copy emails if configured
     *
     * @param string $templateId
     * @param array $templateVars
     * @param array $from
     * @param int $storeId
     * @return void
     * @throws MailException
     */
    private function sendCopyEmails($templateId, $templateVars, $from, $storeId)
    {
        $copyTo = $this->getCopyToEmails($storeId);
        
        if (empty($copyTo)) {
            return;
        }
        
        $copyMethod = $this->getCopyMethod($storeId);
        
        if ($copyMethod === 'bcc') {
            // Use the first email as the main recipient and others as BCC
            $mainEmail = array_shift($copyTo);
            $transport = $this->prepareTransportWithBcc($templateId, $mainEmail, $templateVars, $from, $copyTo, $storeId);
            $transport->sendMessage();
        } else {
            // Send separate emails to each recipient
            foreach ($copyTo as $email) {
                if (!empty($email)) {
                    $this->sendEmail($templateId, $email, $templateVars, $from, $storeId);
                }
            }
        }
    }
    
    /**
     * Get copy to emails from config
     *
     * @param int $storeId
     * @return array
     */
    private function getCopyToEmails($storeId)
    {
        $copyTo = $this->scopeConfig->getValue(
            self::XML_PATH_EMAIL_COPY_TO,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        if (!$copyTo) {
            return [];
        }
        
        return array_map('trim', explode(',', $copyTo));
    }
    
    /**
     * Get copy method from config
     *
     * @param int $storeId
     * @return string
     */
    private function getCopyMethod($storeId)
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_EMAIL_COPY_METHOD,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Prepare transport with BCC recipients
     *
     * @param string $templateId
     * @param string $mainRecipient
     * @param array $templateVars
     * @param array $from
     * @param array $bccRecipients
     * @param int $storeId
     * @return TransportInterface
     */
    private function prepareTransportWithBcc(
        $templateId,
        $mainRecipient,
        $templateVars,
        $from,
        $bccRecipients,
        $storeId
    ) {
        $transportBuilder = $this->transportBuilder
            ->setTemplateIdentifier($templateId)
            ->setTemplateOptions(['area' => 'frontend', 'store' => $storeId])
            ->setTemplateVars($templateVars)
            ->setFrom($from)
            ->addTo($mainRecipient);
        
        foreach ($bccRecipients as $email) {
            if (!empty($email)) {
                $transportBuilder->addBcc($email);
            }
        }
        
        return $transportBuilder->getTransport();
    }
}
