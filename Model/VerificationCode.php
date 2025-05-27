<?php
/**
 * Copyright © O2TI. All rights reserved.
 */
namespace O2TI\SocialLogin\Model;

use Magento\Framework\Model\AbstractModel;
use O2TI\SocialLogin\Model\ResourceModel\VerificationCode as ResourceModel;

class VerificationCode extends AbstractModel
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'o2ti_social_login_verification_code';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(ResourceModel::class);
    }
}
