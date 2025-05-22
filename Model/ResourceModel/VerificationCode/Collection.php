<?php
/**
 * Copyright © O2TI. All rights reserved.
 */
namespace O2TI\SocialLogin\Model\ResourceModel\VerificationCode;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use O2TI\SocialLogin\Model\VerificationCode as Model;
use O2TI\SocialLogin\Model\ResourceModel\VerificationCode as ResourceModel;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'o2ti_social_login_verification_code_collection';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
