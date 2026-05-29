<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpGoogleAnalyticsTools\Model\Ga;

use Magebit\McpGoogleAnalyticsTools\Model\Config\ModuleConfig;
use Magento\Framework\Exception\LocalizedException;

/**
 * Resolves a property_id tool argument to a fully-qualified GA4 resource name ("properties/{id}").
 */
class PropertyResolver
{
    /**
     * @param ModuleConfig $config
     */
    public function __construct(
        private readonly ModuleConfig $config
    ) {
    }

    /**
     * Returns the GA4 SDK resource name for the resolved property.
     *
     * @param mixed $argument
     * @return string
     * @throws LocalizedException
     */
    public function resolve(mixed $argument): string
    {
        return 'properties/' . $this->resolveId($argument);
    }

    /**
     * Returns the bare numeric property id, falling back to admin config when the argument is null/empty.
     *
     * @param mixed $argument
     * @return string
     * @throws LocalizedException
     */
    public function resolveId(mixed $argument): string
    {
        $id = $this->normalize($argument) ?? $this->config->getDefaultPropertyId();
        if ($id === null || $id === '') {
            throw new LocalizedException(__(
                'Google Analytics property_id is required. Pass it in the tool arguments or configure a '
                . 'default GA4 property under Stores → Configuration → Magebit → Google Analytics MCP.'
            ));
        }
        if (!ctype_digit($id)) {
            throw new LocalizedException(
                __('Google Analytics property_id must be numeric; got "%1".', $id)
            );
        }
        return $id;
    }

    /**
     * @param mixed $argument
     * @return ?string
     * @throws LocalizedException
     */
    private function normalize(mixed $argument): ?string
    {
        if ($argument === null) {
            return null;
        }
        if (is_int($argument)) {
            return (string) $argument;
        }
        if (is_string($argument)) {
            $trimmed = trim($argument);
            if ($trimmed === '') {
                return null;
            }
            if (str_starts_with($trimmed, 'properties/')) {
                $trimmed = substr($trimmed, strlen('properties/'));
            }
            return $trimmed;
        }
        throw new LocalizedException(
            __('Google Analytics property_id must be a string or integer.')
        );
    }
}
