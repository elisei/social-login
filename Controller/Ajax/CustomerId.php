<?php
/**
 * Copyright © O2TI. All rights reserved.
 */
namespace O2TI\SocialLogin\Controller\Ajax;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use O2TI\SocialLogin\Model\VerifyCode;

class CustomerId implements HttpPostActionInterface
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var FormKeyValidator
     */
    private $formKeyValidator;

    /**
     * @var VerifyCode
     */
    private $verifyCode;

    /**
     * Constructor
     *
     * @param RequestInterface $request
     * @param JsonFactory $resultJsonFactory
     * @param FormKeyValidator $formKeyValidator
     * @param VerifyCode $verifyCode
     */
    public function __construct(
        RequestInterface $request,
        JsonFactory $resultJsonFactory,
        FormKeyValidator $formKeyValidator,
        VerifyCode $verifyCode
    ) {
        $this->request = $request;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->formKeyValidator = $formKeyValidator;
        $this->verifyCode = $verifyCode;
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        if (!$this->formKeyValidator->validate($this->request)) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('Invalid form key.')
            ]);
        }

        $email = $this->request->getParam('email');

        if (!$email) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('Email is required.')
            ]);
        }

        $customerId = $this->verifyCode->getCustomerIdByEmail($email);

        if (!$customerId) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('No customer account found with this email address.')
            ]);
        }

        return $resultJson->setData([
            'success' => true,
            'customer_id' => $customerId
        ]);
    }
}
