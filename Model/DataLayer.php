<?php

/**
 * DataLayer
 * Copyright © 2016 MagePal. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace MagePal\GoogleTagManager\Model;

use Magento\Framework\DataObject;

class DataLayer extends DataObject {
    
    /**
     * @var Quote|null
     */
    protected $_quote = null;
    
    /**
     * Datalayer Variables
     * @var array
     */
    protected $_variables = [];

    /**
     * Customer session
     *
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_context;
    
    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry = null;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_checkoutSession;
    
    /**
     * @var string
     */
    protected $fullActionName;

    protected $_imageHelper;

    protected $catalogSession;

    protected $productFactory;

    protected $quoteItemFactory;

    protected $categoryFactory;

    protected $categoryId;

    /**
     * @param MessageInterface $message
     * @param null $parameters
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context, 
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig, 
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Registry $registry,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Magento\Catalog\Model\Session $catalogSession,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Quote\Model\Quote\ItemFactory $quoteItemFactory,
        \Magento\Catalog\Model\CategoryFactory $categoryFactory
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_customerSession = $customerSession;
        $this->_context = $context;
        $this->_coreRegistry = $registry;
        $this->_checkoutSession = $checkoutSession;
        $this->_imageHelper = $imageHelper;
        $this->catalogSession = $catalogSession;
        $this->productFactory = $productFactory;
        $this->quoteItemFactory = $quoteItemFactory;
        $this->categoryFactory = $categoryFactory;
    }

    public function setDataLayers($pageType, $categoryId)
    {
        $this->fullActionName = $pageType;
        $this->categoryId = $categoryId;
        $this->addVariable('pageType', $this->fullActionName);
        $this->addVariable('list', 'other');

        $this->setCustomerDataLayer();
        $this->setProductDataLayer();
        $this->setCategoryDataLayer();
        $this->setCartDataLayer();
    }

    /**
     * Return Data Layer Variables
     *
     * @return array
     */
    public function getVariables() {
        return $this->_variables;
    }

    /**
     * Add Variables
     * @param string $name
     * @param mix $value
     * @return MagePal\GoogleTagManager\Model\DataLayer
     */
    public function addVariable($name, $value) {

        if (!empty($name)) {
            $this->_variables[$name] = $value;
        }

        return $this;
    }

    
    /**
     * Set category Data Layer
     */
    protected function setCategoryDataLayer() {
        if($this->fullActionName === 'catalog_category_view') {
            if ($this->categoryId) {
                $_category = $this->categoryFactory->create()->load($this->categoryId);
                if ($_category) {
                    $category = [];
                    $category['id'] = $_category->getId();
                    $category['name'] = $_category->getName();
                    $this->addVariable('category', $category);
                    $this->addVariable('list', 'category');
                }
            }
        }

        return $this;
    }
    
    
    /**
     * Set product Data Layer
     */
    protected function setProductDataLayer() {
        if($this->fullActionName === 'catalog_product_view') {
            $productId = $this->catalogSession->getData('last_viewed_product_id');
            if ($productId) {
                $_product = $this->productFactory->create()->load($productId);
                if ($_product->getId()) {
                    $this->addVariable('list', 'detail');

                    $product = [];
                    $product['id'] = $_product->getId();
                    $product['sku'] = $_product->getSku();
                    $product['name'] = $_product->getName();
                    // $this->addVariable('productPrice', $_product->getPrice());

                    /**
                     * @mod: Adding of imageUrl and price.
                     */
                    $product['image_url'] = $this->_imageHelper->init($_product, 'product_base_image')->setImageFile($_product->getImage())->getUrl();
                    $product['price'] = number_format($_product->getFinalPrice(), '2', '.', ',');
                    $product['tags'] = $this->getProductCategoryNames($_product);

                    $this->addVariable('product', $product);
                }
            }
        }

        return $this;
    }

    /**
     * Set Customer Data Layer
     */
    protected function setCustomerDataLayer() {
        $customer = [];
        if ($this->_customerSession->isLoggedIn()) {
            $customer['isLoggedIn'] = true;
            $customer['id'] = $this->_customerSession->getCustomerId();
            $customer['groupId'] = $this->_customerSession->getCustomerGroupId();


            /**
             * @mod: Add Email, Title, FirstName and LastName.
             */
            $customerModel = $this->_customerSession->getCustomer();
            $customer['email'] = $customerModel->getEmail();
            $customer['title'] = $customerModel->getTitle();
            $customer['firstName'] = $customerModel->getFirstname();
            $customer['lastName'] = $customerModel->getLastname();

            //$customer['groupCode'] = ;
        } else {
            $customer['isLoggedIn'] = false;
        }
        
        $this->addVariable('customer', $customer);

        return $this;
    }
    
    public function getProductCategoryNames($product)
    {
        $categories = $product->getCategoryCollection();
        $categories->addFieldToSelect(array('name'));
        return $categories->getColumnValues('name');
    }

    /**
     * Set cart Data Layer
     */
    protected function setCartDataLayer() {
        if($this->fullActionName === 'checkout_index_index'){
            $this->addVariable('list', 'cart');
        }
        
        $quote = $this->getQuote();
        $cart = [];


        $addToCartProductQueue = $this->_checkoutSession->getData('add_to_cart_product_queue');

        $items = [];

        if ($quote->getItemsCount()) {
            $cart['hasItems'] = true;
            
            // set items
            foreach($quote->getAllVisibleItems() as $item) {
                if ($item->getParentItemId()) {
                    $parentItem = $this->quoteItemFactory->create()->load($item->getParentItemId());
                    $parentPrice = $parentItem->getPriceInclTax();
                }

                $items[] = [
                    'sku' => $item->getSku(),
                    'name' => $item->getName(),
                    'price' => (isset($parentPrice)) ? $parentPrice : $item->getPriceInclTax(),
                    'quantity' => $item->getQty(),
                    'product_id' => $item->getProductId(),
                    'image_url' => $this->_imageHelper->init($item->getProduct(), 'product_base_image')->setImageFile($item->getProduct()->getSmallImage())->getUrl(),
                    'added' => (in_array($item->getProductId(), $addToCartProductQueue)),
                    'tags' => $this->getProductCategoryNames($item->getProduct())
                ];

                if (in_array($item->getProductId(), $addToCartProductQueue)) {
                    unset($addToCartProductQueue[array_search($item->getProductId(), $addToCartProductQueue)]);
                    $this->_checkoutSession->setData('add_to_cart_product_queue', $addToCartProductQueue);
                }
            }

            $cart['quote_id'] = $quote->getId();
            $cart['items'] = $items;
            $cart['total'] = $quote->getGrandTotal();
            $cart['itemCount'] = $quote->getItemsCount();

            //set coupon code
            $coupon = $quote->getCouponCode();
            
            $cart['hasCoupons'] = $coupon ? true : false;

            if($coupon){
                $cart['couponCode'] = $coupon;
            }
        }
        else{
           $cart['hasItems'] = false;
        }
         $this->addVariable('cart', $cart);
        
        return $this;
    }
    
    
    /**
     * Get active quote
     *
     * @return Quote
     */
    public function getQuote()
    {
        if (null === $this->_quote) {
            $this->_quote = $this->_checkoutSession->getQuote();
        }
        return $this->_quote;
    }
    
    /**
     * Format Price
     *
     * @return float
     */
    public function formatPrice($price){
        return sprintf('%.2F', $price);
    }

}
