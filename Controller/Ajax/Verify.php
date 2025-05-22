<?php
/**
 * Copyright © O2TI. All rights reserved.
 */
namespace O2TI\SocialLogin\Controller\Ajax;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use O2TI\SocialLogin\Model\VerifyCode;

class Verify implements HttpPostActionInterface
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
     * @var RedirectFactory
     */
    private $resultRedirectFactory;

    /**
     * @var RedirectInterface
     */
    private $redirect;

    /**
     * @var FormKeyValidator
     */
    private $formKeyValidator;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var VerifyCode
     */
    private $verifyCode;

    /**
     * Constructor
     *
     * @param RequestInterface $request
     * @param JsonFactory $resultJsonFactory
     * @param RedirectFactory $resultRedirectFactory
     * @param RedirectInterface $redirect
     * @param FormKeyValidator $formKeyValidator
     * @param ManagerInterface $messageManager
     * @param VerifyCode $verifyCode
     */
    public function __construct(
        RequestInterface $request,
        JsonFactory $resultJsonFactory,
        RedirectFactory $resultRedirectFactory,
        RedirectInterface $redirect,
        FormKeyValidator $formKeyValidator,
        ManagerInterface $messageManager,
        verifyCode $verifyCode
    ) {
        $this->request = $request;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->redirect = $redirect;
        $this->formKeyValidator = $formKeyValidator;
        $this->messageManager = $messageManager;
        $this->verifyCode = $verifyCode;
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        if ($this->request->isAjax()) {
            return $this->processAjaxRequest();
        }

        return $this->processFormRequest();
    }

    /**
     * Process Ajax request
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    private function processAjaxRequest()
    {
        $resultJson = $this->resultJsonFactory->create();

        if (!$this->formKeyValidator->validate($this->request)) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('Invalid form key.')
            ]);
        }

        $customerId = (int)$this->request->getParam('customer_id');
        $code = (string)$this->request->getParam('code');

        try {
            if (!$customerId || !$code) {
                throw new LocalizedException(__('Missing required parameters.'));
            }

            $this->verifyCode->validateCode($customerId, $code);
            $this->verifyCode->loginCustomer($customerId);

            return $resultJson->setData([
                'success' => true,
                'message' => __('You have been successfully logged in.'),
                'redirect' => $this->redirect->getRefererUrl()
            ]);
        } catch (LocalizedException $e) {
            return $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('An error occurred while processing your request. Please try again later.')
            ]);
        }
    }

    /**
     * Process form request
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    private function processFormRequest()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        
        if (!$this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage(__('Invalid form key.'));
            return $resultRedirect->setPath('*/*/');
        }
        
        $customerId = (int)$this->request->getParam('customer_id');
        $code = (string)$this->request->getParam('code');
        
        try {
            if (!$customerId || !$code) {
                throw new LocalizedException(__('Missing required parameters.'));
            }
            
            $validationResult = $this->verifyCode->validateCode($customerId, $code);
            $this->verifyCode->loginCustomer($customerId);
            
            $this->messageManager->addSuccessMessage(__('You have been successfully logged in.'));
            
            // Redirect based on referer
            $referer = $validationResult['referer'] ?? 'account';
            
            switch ($referer) {
                case 'checkout':
                    return $resultRedirect->setPath('checkout');
                case 'cart':
                    return $resultRedirect->setPath('checkout/cart');
                default:
                    return $resultRedirect->setPath('customer/account');
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $resultRedirect->setPath('customer/account/login');
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('An error occurred while processing your request. Please try again later.')
            );
            return $resultRedirect->setPath('customer/account/login');
        }
    }
}
