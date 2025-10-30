<?php
/**
 * Copyright © 2019 O2TI. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace O2TI\SocialLogin\Controller\Endpoint;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Url\DecoderInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use O2TI\SocialLogin\Provider\Provider;

class Index extends Action
{
    /**
     * @var DecoderInterface
     */
    protected $urlDecoder;

    /**
     * @var Provider
     */
    protected $provider;

    /**
     * @var RedirectFactory
     */
    private $resultRedirectFactory;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * Construct.
     *
     * @param Context $context
     * @param Provider $provider
     * @param DecoderInterface $urlDecoder
     * @param RedirectFactory $resultRedirectFactory
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        Context $context,
        Provider $provider,
        DecoderInterface $urlDecoder,
        RedirectFactory $resultRedirectFactory,
        ManagerInterface $messageManager
    ) {
        parent::__construct($context);
        $this->provider = $provider;
        $this->urlDecoder = $urlDecoder;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->messageManager = $messageManager;
    }

    /**
     * Dispatch request.
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $provider = $this->_request->getParam('provider');
        
        if (empty($provider)) {
            $this->messageManager->addErrorMessage(__('Autenticação é obrigatória.'));
            $resultRedirect = $this->resultRedirectFactory->create();
            return $resultRedirect->setPath('customer/account/login');
        }

        $isSecure = $this->_request->isSecure();
        $referer = $this->_request->getParam('referer');

        $response = $this->provider->setAutenticateAndReferer($provider, $isSecure, $referer);

        $this->getResponse()->setHeader('Referrer-Policy', 'no-referrer');

        $redirect = $response['redirectUrl'];
        
        if (!preg_match('/^https?:\/\//', $redirect)) {
            $redirect = $this->urlDecoder->decode($redirect);
        }

        return $this->_redirect($redirect);
    }
}