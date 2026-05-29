<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpGoogleAnalyticsTools\Tool;

use Google\ApiCore\ApiException;
use Google\Protobuf\Internal\Message;
use JsonException;
use Magebit\Mcp\Api\LoggerInterface;
use Magebit\Mcp\Api\ToolInterface;
use Magebit\Mcp\Api\ToolResultInterface;
use Magebit\Mcp\Model\Tool\Schema\Builder\ObjectBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\StringBuilder;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\McpGoogleAnalyticsTools\Model\Ga\ClientFactory;
use Magebit\McpGoogleAnalyticsTools\Model\Ga\PropertyResolver;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

/**
 * Shared plumbing for the Google Analytics tools: schema helpers, proto serialization, and uniform error wrapping.
 */
abstract class AbstractAnalyticsTool implements ToolInterface
{
    /**
     * @param ClientFactory $clientFactory
     * @param PropertyResolver $propertyResolver
     * @param LoggerInterface $logger
     */
    public function __construct(
        protected readonly ClientFactory $clientFactory,
        protected readonly PropertyResolver $propertyResolver,
        protected readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getWriteMode(): WriteMode
    {
        return WriteMode::READ;
    }

    /**
     * @inheritDoc
     */
    public function getConfirmationRequired(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function execute(array $arguments): ToolResultInterface
    {
        try {
            $payload = $this->executeChecked($arguments);
            $auditSummary = $this->buildAuditSummary($payload);
        } catch (LocalizedException $e) {
            return $this->errorResult($e->getMessage());
        } catch (ApiException $e) {
            $this->logger->warning(sprintf(
                '%s: Google Analytics API error: %s',
                $this->getName(),
                $e->getMessage()
            ));
            return $this->errorResult(sprintf('Google Analytics API error: %s', $e->getMessage()));
        } catch (Throwable $e) {
            $this->logger->error(sprintf(
                '%s: unexpected error: %s',
                $this->getName(),
                $e->getMessage()
            ));
            return $this->errorResult(sprintf('Unexpected error: %s', $e->getMessage()));
        }

        $json = $this->encode($payload);
        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: $auditSummary
        );
    }

    /**
     * Run the tool and return the raw response payload to serialize as JSON.
     *
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     * @throws LocalizedException
     * @throws ApiException
     */
    abstract protected function executeChecked(array $arguments): array;

    /**
     * Per-tool audit summary; default counts top-level entries when the payload's first array is a list of items.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function buildAuditSummary(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value) && array_is_list($value)) {
                return [$key . '_count' => count($value)];
            }
        }
        return [];
    }

    /**
     * Adds an optional `property_id` field to the input schema.
     *
     * @param ObjectBuilder $schema
     * @return ObjectBuilder
     */
    protected function withPropertyIdField(ObjectBuilder $schema): ObjectBuilder
    {
        return $schema->string('property_id', fn (StringBuilder $s) => $s
            ->pattern('^(properties/)?\\d+$')
            ->description(
                'GA4 numeric property id (e.g. "123456789") or fully-qualified name ("properties/123456789"). '
                . 'Optional — falls back to the default property configured under '
                . 'Stores → Configuration → Magebit → Google Analytics MCP.'
            ));
    }

    /**
     * Merges caller arguments into a GA request proto, stripping server-controlled fields first.
     * Call setProperty() AFTER this so a caller-supplied `property` key can never override the
     * resolved, validated property.
     *
     * @param Message $request
     * @param array<string, mixed> $arguments
     * @return void
     * @throws LocalizedException
     */
    protected function mergeArguments(Message $request, array $arguments): void
    {
        unset($arguments['property_id'], $arguments['property']);
        try {
            $request->mergeFromJsonString(json_encode($arguments, JSON_THROW_ON_ERROR), true);
        } catch (JsonException $e) {
            throw new LocalizedException(
                __('Failed to encode Google Analytics report arguments: %1', $e->getMessage())
            );
        }
    }

    /**
     * Round-trips a protobuf message through its JSON serializer into an associative array.
     *
     * @param Message $message
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    protected function protoToArray(Message $message): array
    {
        try {
            $json = $message->serializeToJsonString();
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new LocalizedException(
                __('Failed to serialize Google Analytics response: %1', $e->getMessage())
            );
        }
        return $decoded;
    }

    /**
     * @param iterable<mixed> $messages
     * @return list<array<string, mixed>>
     * @throws LocalizedException
     */
    protected function protosToList(iterable $messages): array
    {
        $out = [];
        foreach ($messages as $message) {
            if (!$message instanceof Message) {
                continue;
            }
            $out[] = $this->protoToArray($message);
        }
        return $out;
    }

    /**
     * @param string $message
     * @return ToolResult
     */
    private function errorResult(string $message): ToolResult
    {
        return new ToolResult(
            content: [['type' => 'text', 'text' => $message]],
            isError: true,
            auditSummary: ['error' => $message]
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return string
     * @throws LocalizedException
     */
    private function encode(array $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        } catch (JsonException $e) {
            throw new LocalizedException(
                __('Failed to encode tool response as JSON: %1', $e->getMessage())
            );
        }
    }
}
