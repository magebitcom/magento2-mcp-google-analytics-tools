<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpGoogleAnalyticsTools\Block\Adminhtml\System\Config;

use Magebit\McpGoogleAnalyticsTools\Model\Config\ModuleConfig;
use Magebit\McpGoogleAnalyticsTools\Model\OAuth\CallbackUrlProvider;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\FormKey;

/**
 * Renders the Google-connection status plus Connect / Disconnect buttons and the redirect URI to register in GCP.
 */
class GoogleConnect extends Field
{
    /**
     * @param Context $context
     * @param ModuleConfig $moduleConfig
     * @param CallbackUrlProvider $callbackUrlProvider
     * @param FormKey $formKeyProvider
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly ModuleConfig $moduleConfig,
        private readonly CallbackUrlProvider $callbackUrlProvider,
        private readonly FormKey $formKeyProvider,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $redirectUri = $this->escapeHtml($this->callbackUrlProvider->get());
        $redirectHint = '<p style="margin-top:8px;color:#666;">'
            . $this->escapeHtml((string) __('Authorized redirect URI to register in your Google OAuth client:'))
            . '<br/><code style="user-select:all;">' . $redirectUri . '</code></p>';

        if ($this->moduleConfig->isOAuthConnected()) {
            return $this->connectedHtml() . $redirectHint;
        }
        return $this->disconnectedHtml() . $redirectHint;
    }

    /**
     * @return string
     */
    private function connectedHtml(): string
    {
        $email = $this->moduleConfig->getOAuthConnectedEmail();
        $status = $email !== null
            ? $this->escapeHtml((string) __('Connected as %1', $email))
            : $this->escapeHtml((string) __('Connected'));

        $disconnectUrl = $this->escapeUrl($this->getUrl('magebit_mcp_ga/oauth/disconnect'));
        $formKey = $this->escapeHtmlAttr($this->formKeyProvider->getFormKey());

        return '<p style="color:#3c763d;font-weight:600;margin-bottom:8px;">&#10004; <strong>' . $status . '</strong></p>'
            . '<form method="post" action="' . $disconnectUrl . '" style="display:inline;">'
            . '<input type="hidden" name="form_key" value="' . $formKey . '"/>'
            . '<button type="submit" class="action-default scalable">'
            . $this->escapeHtml((string) __('Disconnect')) . '</button>'
            . '</form>';
    }

    /**
     * @return string
     */
    private function disconnectedHtml(): string
    {
        $hasClient = $this->moduleConfig->getOAuthClientId() !== null
            && $this->moduleConfig->getOAuthClientSecret() !== null;

        if (!$hasClient) {
            return '<p style="color:#8a6d3b;">'
                . $this->escapeHtml((string) __('Enter the OAuth Client ID and Secret above, then Save Config to enable connecting.'))
                . '</p>';
        }

        $connectUrl = $this->escapeUrl($this->getUrl('magebit_mcp_ga/oauth/connect'));
        return '<p style="color:#a94442;margin-bottom:8px;">' . $this->escapeHtml((string) __('Not connected.')) . '</p>'
            . '<a href="' . $connectUrl . '" class="action-primary scalable" style="display:inline-block;">'
            . $this->escapeHtml((string) __('Connect with Google')) . '</a>';
    }
}
