<?php
/**
 * Copyright © O2TI. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace O2TI\SocialLogin\Controller\Ajax;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use O2TI\SocialLogin\Model\VerifyCode;

class RequestVerify extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var VerifyCode
     */
    protected $verifyCode;
    
    /**
     * @var FormKeyValidator
     */
    protected $formKeyValidator;

    /**
     * Constructor
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param VerifyCode $verifyCode
     * @param FormKeyValidator $formKeyValidator
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        VerifyCode $verifyCode,
        FormKeyValidator $formKeyValidator
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->verifyCode = $verifyCode;
        $this->formKeyValidator = $formKeyValidator;
    }

    /**
     * Process ajax verify code request
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        
        // Validate form key
        if (!$this->formKeyValidator->validate($this->getRequest())) {
            return $result->setData([
                'success' => false,
                'message' => __('Invalid form key. Please refresh the page and try again.')
            ]);
        }
        
        $email = $this->getRequest()->getParam('email');
        $referer = $this->getRequest()->getParam('referer');
        
        if (!$email) {
            return $result->setData([
                'success' => false,
                'message' => __('Email address is required.')
            ]);
        }
        
        try {
            $response = $this->verifyCode->processVerificationCodeRequest($email, $referer);
            return $result->setData($response);
        } catch (LocalizedException $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => __('An error occurred. Please try again later.')
            ]);
        }
    }
}
