<?php

declare(strict_types=1);

namespace OCA\Empleados\Tests\Unit\Service;

use OCA\Empleados\Config\PermissionCatalog;
use OCA\Empleados\Service\PermissionCatalogInitializer;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

final class PermissionCatalogInitializerTest extends TestCase {
	public function testCatalogContainsEveryPermissionUsedByPermisosService(): void {
		$keys = array_map(
			static fn (array $entry): string => $entry['module'] . '.' . $entry['permission'],
			PermissionCatalog::entries(),
		);

		foreach ([
			'empleados.hr',
			'empleados.admin',
			'clientes.admin',
			'clientes.view',
			'compras.admin',
			'compras.approve',
			'compras.request',
			'compras.accounting',
			'inventario.admin',
			'inventario.technician',
			'inventario.view',
			'reporte_tiempos.admin',
			'reporte_tiempos.view',
		] as $permission) {
			$this->assertContains($permission, $keys);
		}

		$this->assertContains('soporte.view', $keys, 'canSee("soporte") necesita una entrada habilitada.');
		$this->assertCount(count(array_unique(array_map(
			static fn (array $entry): string => PermissionCatalog::key($entry['module'], $entry['permission'], $entry['group_id']),
			PermissionCatalog::entries(),
		))), PermissionCatalog::entries(), 'El catálogo no debe contener triples duplicados.');
	}

	public function testEmptyCatalogIsFullyInitialized(): void {
		$rows = [];
		$initializer = $this->createInitializer($rows);

		$inserted = $initializer->initialize();

		$this->assertCount(count(PermissionCatalog::entries()), $inserted);
		$this->assertCount(count(PermissionCatalog::entries()), $rows);
	}

	public function testPartialCatalogOnlyAddsMissingRowsAndPreservesExistingValues(): void {
		$official = PermissionCatalog::entries()[0];
		$existing = $official;
		$existing['enabled'] = 0;
		$existing['description'] = 'Texto personalizado';
		$existing['label'] = 'Etiqueta personalizada';
		$custom = [
			'module' => 'personalizado',
			'permission' => 'special',
			'group_id' => 'grupo_personalizado',
			'label' => 'Personalizado',
			'description' => 'No borrar',
			'restricted' => 0,
			'enabled' => 1,
			'sort_order' => 999,
		];
		$rows = [$existing, $custom];
		$initializer = $this->createInitializer($rows);

		$initializer->initialize();

		$this->assertSame($existing, $rows[0]);
		$this->assertSame($custom, $rows[1]);
		$this->assertCount(count(PermissionCatalog::entries()) + 1, $rows);
	}

	public function testInitializationIsIdempotent(): void {
		$rows = [];
		$initializer = $this->createInitializer($rows);
		$initializer->initialize();
		$afterFirstRun = $rows;

		$this->assertSame([], $initializer->initialize());
		$this->assertSame($afterFirstRun, $rows);
	}

	/** @param list<array<string, mixed>> $rows */
	private function createInitializer(array &$rows): PermissionCatalogInitializer {
		$db = $this->createMock(IDBConnection::class);
		$db->method('getQueryBuilder')->willReturnCallback(function () use (&$rows): IQueryBuilder {
			return $this->createQueryBuilder($rows);
		});

		return new PermissionCatalogInitializer($db);
	}

	/** @param list<array<string, mixed>> $rows */
	private function createQueryBuilder(array &$rows): IQueryBuilder {
		$qb = $this->createMock(IQueryBuilder::class);
		$values = [];

		$qb->method('select')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('insert')->willReturnSelf();
		$qb->method('createNamedParameter')->willReturnCallback(static fn (mixed $value): mixed => $value);
		$qb->method('values')->willReturnCallback(function (array $newValues) use (&$values, $qb): IQueryBuilder {
			$values = $newValues;
			return $qb;
		});
		$qb->method('executeQuery')->willReturnCallback(function () use (&$rows): IResult {
			$result = $this->createMock(IResult::class);
			$result->method('fetchAll')->willReturnCallback(static fn (): array => array_map(
				static fn (array $row): array => [
					'module' => $row['module'],
					'permission' => $row['permission'],
					'group_id' => $row['group_id'],
				],
				$rows,
			));
			$result->method('closeCursor')->willReturn(true);
			return $result;
		});
		$qb->method('executeStatement')->willReturnCallback(static function () use (&$rows, &$values): int {
			$rows[] = $values;
			return 1;
		});

		return $qb;
	}
}
