<?php

namespace MagePal\GoogleTagManager\Controller\Gtm;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;

class Index extends \Magento\Framework\App\Action\Action
{
    protected $jsonResultFactory;
    public $layout;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory,
        \Magento\Framework\View\LayoutInterface $layout
    )
    {
        parent::__construct($context);
        $this->jsonResultFactory = $jsonResultFactory;
        $this->layout = $layout;



    }

    /**
     * Dispatch request
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {

        $result = $this->jsonResultFactory->create();
        $data = [];
        $this->layout->getUpdate()->load(['magepalgtm_gtm_index']);
        $data['gtm'] = $this->layout->getBlock('gtm_code')->toHtml();
        $data['dataLayer'] = $this->layout->getBlock('magepal_gtm_datalayer')->toHtml();
        $result->setJsonData(json_encode($data));
        return $result;
    }
}