<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpGoogleAnalyticsTools\Model\OAuth;

use Google\Auth\OAuth2;
use Magebit\McpGoogleAnalyticsTools\Model\Config\ModuleConfig;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

/**
 * Drives the "Connect with Google" OAuth 2.0 authorization-code flow against Google's endpoints.
 */
class GoogleOAuthService
{
    private const AUTHORIZATION_URI = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URI = 'https://oauth2.googleapis.com/token';

    /** @var list<string> */
    private const SCOPES = [
        'https://www.googleapis.com/auth/analytics.readonly',
        'openid',
        'email',
    ];

    /**
     * @param ModuleConfig $config
     */
    public function __construct(
        private readonly ModuleConfig $config
    ) {
    }

    /**
     * Builds the Google consent-screen URL the admin is redirected to.
     *
     * @param string $redirectUri
     * @param string $state
     * @return string
     * @throws LocalizedException
     */
    public function buildAuthUrl(string $redirectUri, string $state): string
    {
        $oauth = $this->oauth2($redirectUri);
        $oauth->setState($state);

        // access_type=offline + prompt=consent guarantee Google returns a refresh token,
        // even on a re-connect where consent was previously granted.
        $uri = $oauth->buildFullAuthorizationUri([
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
        ]);

        return (string) $uri;
    }

    /**
     * Exchanges an authorization code for tokens and returns the refresh token plus connected email.
     * A missing refresh token is treated as an error (Google only returns it on first consent).
     *
     * @param string $code
     * @param string $redirectUri
     * @return GoogleConnectionResult
     * @throws LocalizedException
     */
    public function exchangeCode(string $code, string $redirectUri): GoogleConnectionResult
    {
        $oauth = $this->oauth2($redirectUri);
        $oauth->setCode($code);

        try {
            /** @var array<string, mixed> $token */
            $token = $oauth->fetchAuthToken();
        } catch (Throwable $e) {
            throw new LocalizedException(
                __('Failed to exchange the Google authorization code for tokens: %1', $e->getMessage())
            );
        }

        $refreshToken = $token['refresh_token'] ?? null;
        if (!is_string($refreshToken) || $refreshToken === '') {
            throw new LocalizedException(__(
                'Google did not return a refresh token. Remove this app\'s access at '
                . 'https://myaccount.google.com/permissions and click Connect again.'
            ));
        }

        return new GoogleConnectionResult($refreshToken, $this->extractEmail($token));
    }

    /**
     * Reads the email claim from a token response's id_token, for admin display only.
     * No signature verification — the token came directly from Google's token endpoint over TLS.
     *
     * @param array<string, mixed> $token
     * @return ?string
     */
    public function extractEmail(array $token): ?string
    {
        $idToken = $token['id_token'] ?? null;
        if (!is_string($idToken)) {
            return null;
        }
        $segments = explode('.', $idToken);
        if (count($segments) < 2) {
            return null;
        }
        $payloadJson = base64_decode(strtr($segments[1], '-_', '+/'), true);
        if ($payloadJson === false) {
            return null;
        }
        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return null;
        }
        $email = $payload['email'] ?? null;
        return is_string($email) && $email !== '' ? $email : null;
    }

    /**
     * @param string $redirectUri
     * @return OAuth2
     * @throws LocalizedException
     */
    private function oauth2(string $redirectUri): OAuth2
    {
        $clientId = $this->config->getOAuthClientId();
        $clientSecret = $this->config->getOAuthClientSecret();
        if ($clientId === null || $clientSecret === null) {
            throw new LocalizedException(__(
                'Set the OAuth Client ID and Client Secret, and Save Config, before connecting to Google.'
            ));
        }

        return new OAuth2([
            'authorizationUri' => self::AUTHORIZATION_URI,
            'tokenCredentialUri' => self::TOKEN_URI,
            'redirectUri' => $redirectUri,
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'scope' => self::SCOPES,
        ]);
    }
}
