<?php

namespace MagePal\GoogleTagManager\Observer\Frontend;

use Magento\Framework\Event\Observer;

class AddToCartObserver implements \Magento\Framework\Event\ObserverInterface
{
    protected $_checkoutSession;

    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession
    )
    {
        $this->_checkoutSession = $checkoutSession;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $productId = $observer->getProduct()->getId();
        $productQueue = ($this->_checkoutSession->getData('add_to_cart_product_queue')) ? $this->_checkoutSession->getData('add_to_cart_product_queue') : array();
        $productQueue[] = $productId;
        $this->_checkoutSession->setData('add_to_cart_product_queue', $productQueue);
    }
}