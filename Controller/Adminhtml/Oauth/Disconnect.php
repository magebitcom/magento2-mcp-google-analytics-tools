<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpGoogleAnalyticsTools\Controller\Adminhtml\Oauth;

use Magebit\McpGoogleAnalyticsTools\Model\OAuth\GoogleOAuthStorage;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;

/**
 * Clears the stored Google connection (refresh token + connected email).
 */
class Disconnect extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Magebit_McpGoogleAnalyticsTools::config';

    /**
     * @param Context $context
     * @param GoogleOAuthStorage $storage
     */
    public function __construct(
        Context $context,
        private readonly GoogleOAuthStorage $storage
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $this->storage->clear();
        $this->messageManager->addSuccessMessage((string) __('Disconnected from Google Analytics.'));

        $redirect = $this->resultRedirectFactory->create();
        $redirect->setPath('adminhtml/system_config/edit', ['section' => 'magebit_mcp_google_analytics']);
        return $redirect;
    }
}
