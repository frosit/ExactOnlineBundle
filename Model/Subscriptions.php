<?php

namespace ExactOnlineBundle\Model;

use ExactOnlineBundle\Model\Base\Model;
/**
 * Added deprecated Subscriptions class for extends Model backward compatibility
 */
class_alias(__NAMESPACE__ . '\Subscription', __NAMESPACE__ . '\Subscriptions');