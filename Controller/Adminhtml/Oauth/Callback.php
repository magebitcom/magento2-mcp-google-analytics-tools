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
use Magebit\McpGoogleAnalyticsTools\Model\OAuth\GoogleOAuthStorage;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Handles Google's redirect back from the consent screen, exchanges the code for a refresh token,
 * and stores it. Listed in $_publicActions so the registered redirect URI stays free of the admin
 * secret-key segment; the state nonce + admin session are the CSRF gate.
 */
class Callback extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Magebit_McpGoogleAnalyticsTools::config';

    /**
     * Bypasses the secret-key URL check; the state nonce + admin session authenticate.
     *
     * @var string[]
     */
    protected $_publicActions = ['callback'];

    /**
     * @param Context $context
     * @param GoogleOAuthService $oauthService
     * @param GoogleOAuthStorage $storage
     * @param CallbackUrlProvider $callbackUrlProvider
     * @param Session $backendSession
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly GoogleOAuthService $oauthService,
        private readonly GoogleOAuthStorage $storage,
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

        $request = $this->getRequest();
        $expectedState = $this->backendSession->getData(Connect::STATE_SESSION_KEY, true);
        $providedState = $request->getParam('state');

        if (!is_string($expectedState) || $expectedState === ''
            || !is_string($providedState) || !hash_equals($expectedState, $providedState)
        ) {
            $this->messageManager->addErrorMessage(
                (string) __('The Google connection request expired or could not be verified. Please try again.')
            );
            return $redirect;
        }

        $error = $request->getParam('error');
        if (is_string($error) && $error !== '') {
            $this->messageManager->addErrorMessage((string) __('Google returned an error: %1', $error));
            return $redirect;
        }

        $code = $request->getParam('code');
        if (!is_string($code) || $code === '') {
            $this->messageManager->addErrorMessage((string) __('Google did not return an authorization code.'));
            return $redirect;
        }

        try {
            $result = $this->oauthService->exchangeCode($code, $this->callbackUrlProvider->get());
            $this->storage->store($result->refreshToken, $result->email);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $redirect;
        } catch (\Throwable $e) {
            $this->logger->error('GA OAuth callback failed: ' . $e->getMessage());
            $this->messageManager->addErrorMessage(
                (string) __('Could not complete the Google connection. See the log.')
            );
            return $redirect;
        }

        $this->messageManager->addSuccessMessage(
            $result->email !== null
                ? (string) __('Connected to Google Analytics as %1.', $result->email)
                : (string) __('Connected to Google Analytics.')
        );
        return $redirect;
    }
}
