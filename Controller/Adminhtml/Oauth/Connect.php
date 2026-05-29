<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpGoogleAnalyticsTools\Controller\Adminhtml\Oauth;

use Magebit\Mcp\Api\LoggerInterface;
use Magebit\McpGoogleAnalyticsTools\Model\OAuth\CallbackUrlProvider;
use Magebit\McpGoogleAnalyticsTools\Model\OAuth\GoogleOAuthService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Starts the Connect-with-Google flow: mints a CSRF state, stashes it in the admin session,
 * and redirects the operator to Google's consent screen.
 */
class Connect extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Magebit_McpGoogleAnalyticsTools::config';
    public const STATE_SESSION_KEY = 'magebit_mcp_ga_oauth_state';

    /**
     * @param Context $context
     * @param GoogleOAuthService $oauthService
     * @param CallbackUrlProvider $callbackUrlProvider
     * @param Session $backendSession
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly GoogleOAuthService $oauthService,
        private readonly CallbackUrlProvider $callbackUrlProvider,
        private readonly Session $backendSession,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $redirect = $this->resultRedirectFactory->create();
        $redirect->setPath('adminhtml/system_config/edit', ['section' => 'magebit_mcp_google_analytics']);

        try {
            $state = bin2hex(random_bytes(16));
            $this->backendSession->setData(self::STATE_SESSION_KEY, $state);
            $authUrl = $this->oauthService->buildAuthUrl($this->callbackUrlProvider->get(), $state);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $redirect;
        } catch (\Throwable $e) {
            $this->logger->error('GA OAuth connect failed: ' . $e->getMessage());
            $this->messageManager->addErrorMessage((string) __('Could not start the Google connection. See the log.'));
            return $redirect;
        }

        $redirect->setUrl($authUrl);
        return $redirect;
    }
}
