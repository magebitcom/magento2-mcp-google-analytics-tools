<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpGoogleAnalyticsTools\Test\Unit\Tool;

use Google\Analytics\Data\V1beta\RunReportRequest;
use Google\ApiCore\ApiException;
use Google\Protobuf\Internal\Message;
use Magebit\Mcp\Api\LoggerInterface;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\McpGoogleAnalyticsTools\Model\Ga\ClientFactory;
use Magebit\McpGoogleAnalyticsTools\Model\Ga\PropertyResolver;
use Magebit\McpGoogleAnalyticsTools\Tool\AbstractAnalyticsTool;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Throwable;

class AbstractAnalyticsToolTest extends TestCase
{
    private ClientFactory&MockObject $clientFactory;
    private PropertyResolver&MockObject $resolver;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->clientFactory = $this->createMock(ClientFactory::class);
        $this->resolver = $this->createMock(PropertyResolver::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testReadModeDefaults(): void
    {
        $tool = $this->stub(['accounts' => []]);
        $this->assertSame(WriteMode::READ, $tool->getWriteMode());
        $this->assertFalse($tool->getConfirmationRequired());
    }

    public function testSuccessfulExecuteWrapsPayloadAsJsonText(): void
    {
        $tool = $this->stub(['accounts' => [['name' => 'a']]]);

        $result = $tool->execute([]);

        $this->assertFalse($result->isError());
        $text = $this->extractText($result->getContent());
        $decoded = json_decode($text, true);
        $this->assertSame(['accounts' => [['name' => 'a']]], $decoded);
        $this->assertSame(['accounts_count' => 1], $result->getAuditSummary());
    }

    public function testLocalizedExceptionBecomesSoftErrorResult(): void
    {
        $tool = $this->stub(new LocalizedException(__('Boom: missing config.')));

        $result = $tool->execute([]);

        $this->assertTrue($result->isError());
        $this->assertSame('Boom: missing config.', $this->extractText($result->getContent()));
        $this->assertSame(['error' => 'Boom: missing config.'], $result->getAuditSummary());
    }

    public function testApiExceptionBecomesSoftErrorResult(): void
    {
        $this->logger->expects($this->once())->method('warning');
        $tool = $this->stub(new ApiException('not found', 5, 'NOT_FOUND'));

        $result = $tool->execute([]);

        $this->assertTrue($result->isError());
        $text = $this->extractText($result->getContent());
        $this->assertStringContainsString('Google Analytics API error', $text);
        $this->assertStringContainsString('not found', $text);
    }

    public function testUnexpectedThrowableIsLoggedAndWrapped(): void
    {
        $this->logger->expects($this->once())->method('error');
        $tool = $this->stub(new \RuntimeException('boom'));

        $result = $tool->execute([]);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('Unexpected error', $this->extractText($result->getContent()));
    }

    public function testMergeArgumentsCannotOverrideResolvedProperty(): void
    {
        $tool = $this->stub([]);
        $request = new RunReportRequest();

        // A caller tries to smuggle a different property + a legitimate field.
        $merge = new \ReflectionMethod(AbstractAnalyticsTool::class, 'mergeArguments');
        $merge->invoke($tool, $request, ['property' => 'properties/999', 'limit' => 5]);
        // The tool sets the resolved property AFTER the merge.
        $request->setProperty('properties/123');

        $this->assertSame('properties/123', $request->getProperty());
        $this->assertSame(5, (int) $request->getLimit());
    }

    /**
     * @param array<int, array<string, mixed>> $content
     */
    private function extractText(array $content): string
    {
        $this->assertSame('text', $content[0]['type'] ?? null);
        $text = $content[0]['text'] ?? null;
        $this->assertIsString($text);
        return $text;
    }

    /**
     * @param array<string, mixed>|Throwable $payloadOrThrow
     */
    private function stub(array|Throwable $payloadOrThrow): AbstractAnalyticsTool
    {
        return new class ($this->clientFactory, $this->resolver, $this->logger, $payloadOrThrow) extends AbstractAnalyticsTool {
            /**
             * @param array<string, mixed>|Throwable $payloadOrThrow
             */
            public function __construct(
                ClientFactory $clientFactory,
                PropertyResolver $resolver,
                LoggerInterface $logger,
                private readonly array|Throwable $payloadOrThrow
            ) {
                parent::__construct($clientFactory, $resolver, $logger);
            }

            public function getName(): string
            {
                return 'google_analytics.test.stub';
            }

            public function getTitle(): string
            {
                return 'Stub';
            }

            public function getDescription(): string
            {
                return 'Stub tool for tests.';
            }

            public function getInputSchema(): array
            {
                return Schema::object()->toArray();
            }

            public function getAclResource(): string
            {
                return 'Magebit_McpGoogleAnalyticsTools::tool_google_analytics_test_stub';
            }

            protected function executeChecked(array $arguments): array
            {
                if ($this->payloadOrThrow instanceof Throwable) {
                    throw $this->payloadOrThrow;
                }
                return $this->payloadOrThrow;
            }

            /**
             * @param array<string, mixed> $arguments
             */
            public function callMergeArguments(Message $request, array $arguments): void
            {
                $this->mergeArguments($request, $arguments);
            }
        };
    }
}
