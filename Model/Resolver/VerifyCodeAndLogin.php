<?php
/**
 * Copyright © O2TI. All rights reserved.
 */
namespace O2TI\SocialLogin\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Exception\LocalizedException;
use O2TI\SocialLogin\Model\VerifyCode;
use O2TI\SocialLogin\Model\Service\CustomerTokenGenerator;

/**
 * Resolver para validar código e realizar login
 */
class VerifyCodeAndLogin implements ResolverInterface
{
    /**
     * @var VerifyCode
     */
    private $verifyCode;

    /**
     * @var CustomerTokenGenerator
     */
    private $customerTokenGenerator;

    /**
     * @param VerifyCode $verifyCode
     * @param CustomerTokenGenerator $customerTokenGenerator
     */
    public function __construct(
        VerifyCode $verifyCode,
        CustomerTokenGenerator $customerTokenGenerator
    ) {
        $this->verifyCode = $verifyCode;
        $this->customerTokenGenerator = $customerTokenGenerator;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (empty($args['email'])) {
            throw new GraphQlInputException(__('Email is required.'));
        }

        if (empty($args['code'])) {
            throw new GraphQlInputException(__('Verification code is required.'));
        }

        $email = $args['email'];
        $code = (string)$args['code'];

        try {
            $validationResult = $this->verifyCode->validateCodeByEmail($email, $code);
            
            $this->verifyCode->loginCustomer($validationResult['customer_id']);

            $customerToken = $this->customerTokenGenerator->generateCustomerToken(
                $validationResult['customer_id'], 
                $validationResult['email']
            );

            $referer = $validationResult['referer'] ?? 'account';
            $redirect = $this->getRedirectUrl($referer);

            return [
                'success' => true,
                'message' => __('You have been successfully logged in.'),
                'redirect' => $redirect,
                'customer_token' => $customerToken
            ];
        } catch (LocalizedException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'redirect' => null,
                'customer_token' => null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => __('An error occurred while processing your request. Please try again later.'),
                'redirect' => null,
                'customer_token' => null
            ];
        }
    }

    /**
     * Obtém a URL de redirecionamento com base no referer
     *
     * @param string $referer
     * @return string
     */
    private function getRedirectUrl($referer)
    {
        switch ($referer) {
            case 'checkout':
                return 'checkout';
            case 'cart':
                return 'checkout/cart';
            default:
                return 'customer/account';
        }
    }
}
