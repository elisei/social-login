<?php
/**
 * Copyright © O2TI. All rights reserved.
 */
namespace O2TI\SocialLogin\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use O2TI\SocialLogin\Model\VerifyCode;

/**
 * Resolver para obter o ID do cliente pelo email
 */
class GetCustomerIdByEmail implements ResolverInterface
{
    /**
     * @var VerifyCode
     */
    private $verifyCode;

    /**
     * @param VerifyCode $verifyCode
     */
    public function __construct(
        VerifyCode $verifyCode
    ) {
        $this->verifyCode = $verifyCode;
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
            throw new GraphQlInputException(__('Email address is required.'));
        }

        $email = $args['email'];
        $customerId = $this->verifyCode->getCustomerIdByEmail($email);

        if (!$customerId) {
            return [
                'success' => false,
                'customer_id' => null,
                'message' => __('No customer account found with this email address.')
            ];
        }

        return [
            'success' => true,
            'customer_id' => $customerId,
            'message' => null
        ];
    }
}
