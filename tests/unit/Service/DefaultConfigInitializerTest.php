<?php

declare(strict_types=1);

namespace OCA\Empleados\Tests\Unit\Service;

use OCA\Empleados\Config\DefaultConfig;
use OCA\Empleados\Service\DefaultConfigInitializer;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

final class DefaultConfigInitializerTest extends TestCase {
	public function testInitializesAllDefaultsWhenConfigurationIsEmpty(): void {
		$table = [];
		$app = [];
		$initializer = $this->createInitializer($table, $app);

		$inserted = $initializer->initialize();

		$this->assertSame(DefaultConfig::TABLE_DEFAULTS, $table);
		$this->assertSame(DefaultConfig::APP_DEFAULTS, $app);
		$this->assertSame(array_keys(DefaultConfig::TABLE_DEFAULTS), $inserted['table']);
		$this->assertSame(array_keys(DefaultConfig::APP_DEFAULTS), $inserted['app']);
		$this->assertArrayHasKey('usuario_almacenamiento', $table);
		$this->assertNull($table['usuario_almacenamiento']);
	}

	public function testPreservesExistingTableAndAppValues(): void {
		$table = ['modulo_clientes' => 'true'];
		$app = [
			'reportes_recordatorios_enabled' => 'false',
			'reportes_recordatorios_zona_horaria' => '',
		];
		$initializer = $this->createInitializer($table, $app);

		$initializer->initialize();

		$this->assertSame('true', $table['modulo_clientes']);
		$this->assertSame('false', $app['reportes_recordatorios_enabled']);
		$this->assertSame('', $app['reportes_recordatorios_zona_horaria']);
	}

	public function testRepeatedInitializationDoesNotInsertOrChangeAnything(): void {
		$table = [];
		$app = [];
		$initializer = $this->createInitializer($table, $app);

		$initializer->initialize();
		$tableAfterFirstRun = $table;
		$appAfterFirstRun = $app;
		$secondRun = $initializer->initialize();

		$this->assertSame(['table' => [], 'app' => []], $secondRun);
		$this->assertSame($tableAfterFirstRun, $table);
		$this->assertSame($appAfterFirstRun, $app);
		$this->assertCount(count(DefaultConfig::TABLE_DEFAULTS), $table);
	}

	/**
	 * @param array<string, string|null> $table
	 * @param array<string, string> $app
	 */
	private function createInitializer(array &$table, array &$app): DefaultConfigInitializer {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppKeys')
			->with(DefaultConfig::APP_ID)
			->willReturnCallback(static function () use (&$app): array {
				return array_keys($app);
			});
		$config->method('setAppValue')
			->willReturnCallback(static function (string $appId, string $key, string $value) use (&$app): void {
				self::assertSame(DefaultConfig::APP_ID, $appId);
				$app[$key] = $value;
			});

		$db = $this->createMock(IDBConnection::class);
		$db->method('getQueryBuilder')
			->willReturnCallback(function () use (&$table): IQueryBuilder {
				return $this->createQueryBuilder($table);
			});

		return new DefaultConfigInitializer($db, $config);
	}

	/** @param array<string, string|null> $table */
	private function createQueryBuilder(array &$table): IQueryBuilder {
		$qb = $this->createMock(IQueryBuilder::class);
		$values = [];

		$qb->method('selectAlias')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('insert')->willReturnSelf();
		$qb->method('createNamedParameter')->willReturnCallback(static fn (mixed $value): mixed => $value);
		$qb->method('values')->willReturnCallback(function (array $newValues) use (&$values, $qb): IQueryBuilder {
			$values = $newValues;
			return $qb;
		});

		$qb->method('executeQuery')->willReturnCallback(function () use (&$table): IResult {
			$result = $this->createMock(IResult::class);
			$result->method('fetchAll')->willReturnCallback(static function () use (&$table): array {
				return array_map(
					static fn (string $key): array => ['config_name' => $key],
					array_keys($table),
				);
			});
			$result->method('closeCursor')->willReturn(true);
			return $result;
		});
		$qb->method('executeStatement')->willReturnCallback(static function () use (&$table, &$values): int {
			$table[(string)$values['Nombre']] = $values['Data'];
			return 1;
		});

		return $qb;
	}
}
