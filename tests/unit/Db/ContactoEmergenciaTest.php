<?php

declare(strict_types=1);

namespace OCA\Empleados\Tests\Unit\Db;

use OCA\Empleados\Controller\EmpleadosController;
use OCA\Empleados\Db\contactoemergencia;
use OCP\AppFramework\Db\Entity;
use PHPUnit\Framework\TestCase;

class ContactoEmergenciaTest extends TestCase {
	public function testEntityUsesInheritedIdAndMapsDatabaseColumns(): void {
		$entity = contactoemergencia::fromRow([
			'id' => '7',
			'id_empleado' => '12',
			'nombre' => 'Ana Pérez',
			'relacion' => 'Hermana',
			'numero_contacto' => '+52 871 123-4567',
			'medio_alternativo' => null,
			'tipo_ayuda' => 'Transporte',
			'notas' => null,
			'es_principal' => '1',
			'principal_empleado' => '12',
			'orden' => '0',
			'created_at' => '2026-08-01 09:00:00',
			'updated_at' => '2026-08-01 09:00:00',
		]);

		$this->assertSame(7, $entity->getId());
		$this->assertSame(12, $entity->getIdEmpleado());
		$this->assertSame('+52 871 123-4567', $entity->getNumeroContacto());
		$this->assertSame(1, $entity->getEsPrincipal());
		$this->assertNull($entity->getMedioAlternativo());
		$idProperty = (new \ReflectionClass(contactoemergencia::class))->getProperty('id');
		$this->assertSame(Entity::class, $idProperty->getDeclaringClass()->getName());
	}

	public function testContactValidationTrimsFields(): void {
		$result = $this->validate(['  Ana Pérez  ', '  Hermana ', ' +52 871 123-4567 ', ' correo@example.com ', ' Transporte ', ' Nota ']);

		$this->assertSame('Ana Pérez', $result['nombre']);
		$this->assertSame('Hermana', $result['relacion']);
		$this->assertSame('+52 871 123-4567', $result['numero']);
		$this->assertSame('correo@example.com', $result['alternativo']);
	}

	public function testContactValidationRejectsRequiredWhitespace(): void {
		$result = $this->validate(['  ', 'Madre', '871 123 4567', '', '', '']);

		$this->assertArrayHasKey('error', $result);
	}

	public function testContactValidationRejectsOversizedText(): void {
		$result = $this->validate([str_repeat('a', 201), 'Madre', '871 123 4567', '', '', '']);

		$this->assertArrayHasKey('error', $result);
	}

	private function validate(array $fields): array {
		$controller = (new \ReflectionClass(EmpleadosController::class))->newInstanceWithoutConstructor();
		$method = new \ReflectionMethod(EmpleadosController::class, 'validarContacto');
		return $method->invoke($controller, ...$fields);
	}
}
