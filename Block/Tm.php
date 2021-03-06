<?php

/**
 * DataLayer
 * Copyright © 2016 MagePal. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace MagePal\GoogleTagManager\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Cookie\Helper\Cookie as CookieHelper;
use MagePal\GoogleTagManager\Helper\Data as GtmHelper;


/**
 * Google Tag Manager Block
 */
class Tm extends Template {

    /**
     * Google Tag Manager Helper
     *
     * @var \MagePal\TagManager\Helper\Data
     */
    protected $_gtmHelper = null;

    /**
     * Cookie Helper
     *
     * @var \Magento\Cookie\Helper\Cookie
     */
    protected $_cookieHelper = null;

    /**
     * Cookie Helper
     *
     * @var \MagePal\TagManager\Model\DataLayer
     */
    protected $_dataLayerModel = null;
    
    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    protected $_salesOrderCollection;
    
    private $_orderCollection;

    protected $_imageHelper;

    protected $_customVariables = array();

    protected $checkoutSession;
    /**
     * @param Context $context
     * @param CookieHelper $cookieHelper
     * @param GtmHelper $gtmHelper
     * @param \MagePal\GoogleTagManager\Model\DataLayer $dataLayer
     * @param array $data
     */
    public function __construct(
        Context $context, 
        GtmHelper $gtmHelper, 
        CookieHelper $cookieHelper, 
        \MagePal\GoogleTagManager\Model\DataLayer $dataLayer,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $salesOrderCollection,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Magento\Checkout\Model\Session $checkoutSession,
        array $data = []
    ) {
        $this->_cookieHelper = $cookieHelper;
        $this->_gtmHelper = $gtmHelper;
        $this->_dataLayerModel = $dataLayer;
        $this->_salesOrderCollection = $salesOrderCollection;
        $this->_imageHelper = $imageHelper;
        parent::__construct($context, $data);
        
        $this->addVariable('ecommerce', ['currencyCode' => $this->_storeManager->getStore()->getCurrentCurrency()->getCode()]);

        $this->_dataLayerModel->setDataLayers($this->_request->getParam('pageType'), $this->_request->getParam('categoryId'));
        $this->checkoutSession = $checkoutSession;
    }
    
    /**
     * Render information about specified orders and their items
     * 
     * @return void|string
     */
    public function getOrdersTrackingCode()
    {
        if ($this->_request->getParam('pageType') == "checkout_onepage_success") {
            $collection = $this->getOrderCollection();

            if (!$collection) {
                return;
            }

            $result = [];

            foreach ($collection as $order) {

                $product = [];

                foreach ($order->getAllVisibleItems() as $item) {

                    $product[] = array(
                        'sku' => $item->getSku(),
                        'name' => $item->getName(),
                        'price' => floatval($item->getPriceInclTax()),
                        'quantity' => floatval($item->getQtyOrdered()),
                        'product_id' => $item->getProductId(),
                        'image_url' => $this->_imageHelper->init($item->getProduct(), 'product_base_image')->setImageFile($item->getProduct()->getImage())->getUrl(),
                        'tags' => $this->_dataLayerModel->getProductCategoryNames($item->getProduct())
                    );
                }

                $transaction = array('transaction' => array(
                    'transactionId' => $order->getIncrementId(),
                    'transactionAffiliation' => $this->escapeJsQuote($this->_storeManager->getStore()->getFrontendName()),
                    'transactionTotal' => floatval($order->getGrandTotal()),
                    'transactionShipping' => floatval($order->getShippingInclTax()),
                    'transactionShippingMethod' => $order->getShippingMethod(),
                    'transactionDiscountAmount' => floatval($order->getDiscountAmount()),
                    'transactionProducts' => $product
                )
                );


                $result[] = sprintf("dataLayer.push(%s);", json_encode($transaction));
            }

            return implode("\n", $result) . "\n";
        }

        return "";
    }

    /**
     * Render tag manager script
     *
     * @return string
     */
    protected function _toHtml() {
        if ($this->_cookieHelper->isUserNotAllowSaveCookie() || !$this->_gtmHelper->isEnabled()) {
            return '';
        }

        return parent::_toHtml();
    }

    /**
     * Return data layer json
     *
     * @return json
     */
    public function getGtmTrackingCode() {
        $this->_eventManager->dispatch(
            'magepal_datalayer',
            ['dataLayer' => $this]
        );

        $result = [];
        $result[] = sprintf("dataLayer.push(%s);\n", json_encode($this->_dataLayerModel->getVariables()));
        
        if(!empty($this->_customVariables) && is_array($this->_customVariables)){
           
            foreach($this->_customVariables as $custom){
                $result[] = sprintf("dataLayer.push(%s);\n", json_encode($custom));
            }
        }
        
        return implode("\n", $result) . "\n";
    }

    /**
     * Add variable to the default data layer
     *
     * @return $this
     */
    public function addVariable($name, $value) {
        $this->_dataLayerModel->addVariable($name, $value);
        
        return $this;
    }
    
    /**
     * Add variable to the custom push data layer
     *
     * @return $this
     */
    public function addCustomVariable($name, $value = null) {
       if(is_array($name)){
          $this->_customVariables[] = $name;
       }
       else{
           $this->_customVariables[] = [$name => $value];
       }
        
        return $this;
    }
    
    /**
     * Format Price
     *
     * @return float
     */
    public function formatPrice($price){
        return $this->_dataLayerModel->formatPrice($price);
    }
    
    
    /**
     * Get order collection
     *
     * @return $this
     */
    public function getOrderCollection(){
        $lastOrderId = $this->checkoutSession->getLastOrderId();
        if (!$lastOrderId) {
            return;
        }
        $orderIds = array($this->checkoutSession->getLastOrderId());
        if(!$this->_orderCollection){
            $this->_orderCollection = $this->_salesOrderCollection->create();
            $this->_orderCollection->addFieldToFilter('entity_id', ['in' => $orderIds]);
        }
        
        return $this->_orderCollection;
    }
}
