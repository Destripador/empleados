<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use OCP\IDBConnection;
use OCP\Security\ICrypto;
use PDO;
use PDOException;
use Exception;

class FirebirdSyncService
{
    public function __construct(
        private IDBConnection $dbConnection,
        private ICrypto $crypto
    ) {
    }

    /**
     * Obtiene la configuración de NOI y desencripta la contraseña.
     */
    private function getConfig(): array
    {
        $qb = $this->dbConnection->getQueryBuilder();

        $result = $qb->select('*')
            ->from('empleados_noi_conf')
            ->executeQuery();

        $config = [];

        while ($row = $result->fetch()) {
            if ($row['nombre'] === 'Contrasena_Aspel') {
                $config[$row['nombre']] = $this->crypto->decrypt($row['data']);
            } else {
                $config[$row['nombre']] = $row['data'];
            }
        }

        $result->closeCursor();

        return $config;
    }

    /**
     * Establece la conexión PDO con Firebird.
     */
    public function getFirebirdConnection(): PDO
    {
        $config = $this->getConfig();

        $servidor = $config['Servidor_NOI'] ?? '';
        $baseDatos = $config['Bd_NOI'] ?? '';
        $password = $config['Contrasena_Aspel'] ?? '';

        if ($servidor === '' || $baseDatos === '') {
            throw new Exception(
                'La configuración de conexión a Firebird está incompleta.'
            );
        }

        $dsn = "firebird:dbname={$servidor}:{$baseDatos};charset=UTF8";

        try {
            return new PDO(
                $dsn,
                'SYSDBA',
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            throw new Exception(
                'Error de conexión con Firebird (Aspel NOI): ' . $e->getMessage()
            );
        }
    }

    /**
     * Obtiene los datos de un empleado desde Aspel NOI.
     *
     * Primero intenta buscar por número de empleado.
     * Si no existe, intenta buscar por correo electrónico.
     */
    public function obtenerDatosEmpleadoNoi(
        ?string $numeroEmpleado,
        ?string $userEmail
    ): array {
        $config = $this->getConfig();
        $fbPdo = $this->getFirebirdConnection();

        // 1. Obtener tabla dinámica de trabajadores.
        $tablaTrabajadores = $this->getTablaTrabajadoresBase($fbPdo);

        $noCorreo = trim((string)($config['No_Correo'] ?? ''));
        $empNoi = null;

        // Excluir empleados dados de baja o finiquitados.
        $whereStatus = "
            AND TRIM(STATUS) <> 'B'
            AND TRIM(STATUS) <> 'F'
        ";

        /*
         * 2. Método de búsqueda 1:
         * Número de empleado.
         */
        if (!empty($numeroEmpleado)) {
            $stmt = $fbPdo->prepare(
                "
                SELECT *
                FROM {$tablaTrabajadores}
                WHERE TRIM(CLAVE) = TRIM(?)
                {$whereStatus}
                "
            );

            $stmt->execute([$numeroEmpleado]);
            $empNoi = $stmt->fetch();

            if (!$empNoi) {
                throw new Exception(
                    "No se encontró ningún empleado en NOI con el número: {$numeroEmpleado}"
                );
            }
        }

        /*
         * 3. Método de búsqueda 2:
         * Correo electrónico, ignorando el correo genérico
         * configurado como No_Correo.
         */
        elseif (!empty($userEmail)) {
            $sql = "
                SELECT *
                FROM {$tablaTrabajadores}
                WHERE (
                    LOWER(TRIM(EMAIL)) = LOWER(TRIM(?))
                    AND LOWER(TRIM(EMAIL)) <> LOWER(TRIM(?))
                )
                OR (
                    LOWER(TRIM(EMAIL2)) = LOWER(TRIM(?))
                    AND LOWER(TRIM(EMAIL2)) <> LOWER(TRIM(?))
                )
                {$whereStatus}
            ";

            $stmt = $fbPdo->prepare($sql);

            $stmt->execute([
                $userEmail,
                $noCorreo,
                $userEmail,
                $noCorreo,
            ]);

            $empNoi = $stmt->fetch();

            if (!$empNoi) {
                throw new Exception(
                    "No se encontró ningún empleado en NOI asociado al correo: {$userEmail}"
                );
            }
        } else {
            throw new Exception(
                'No hay un número de empleado ni correo electrónico para realizar la búsqueda en NOI.'
            );
        }

        // 4. Transformar y mapear campos de NOI.
        $datosNOI = $this->mapearCamposNoi($empNoi);

        /*
         * Si EMAIL contiene el correo genérico configurado en No_Correo,
         * se utiliza EMAIL2 para evitar mostrar el correo genérico
         * como correo del empleado.
         */
        $email1 = strtolower(
            trim((string)($datosNOI['Email'] ?? ''))
        );

        $email2 = strtolower(
            trim((string)($datosNOI['Email2'] ?? ''))
        );

        $noCorreoNormalizado = strtolower($noCorreo);

        if (
            $noCorreoNormalizado !== ''
            && $email1 === $noCorreoNormalizado
        ) {
            $datosNOI['Email'] = $datosNOI['Email2'];
        }

        return $datosNOI;
    }

    /**
     * Mapea los campos de Aspel NOI al formato utilizado
     * por el módulo de empleados.
     */
    private function mapearCamposNoi(array $emp): array
    {
        /*
         * Estado civil.
         */
        $edoCivilMap = [
            'S' => 'Soltero',
            'C' => 'Casado',
            'D' => 'Divorciado',
            'V' => 'Viudo',
            'U' => 'Unión Libre',
        ];

        $edoCivilKey = strtoupper(
            trim((string)($emp['EDO_CIVIL'] ?? ''))
        );

        $estadoCivil = $edoCivilMap[$edoCivilKey] ?? '';

        /*
         * Género.
         */
        $sexoKey = strtoupper(
            trim((string)($emp['SEXO'] ?? ''))
        );

        $genero = match ($sexoKey) {
            'M' => 'Masculino',
            'F' => 'Femenino',
            default => '',
        };

        /*
         * Obtener nombre de la entidad federativa.
         */
        $entFedNombre = '';

        $entFedClave = trim(
            (string)($emp['ENT_FED'] ?? '')
        );

        if (
            $entFedClave !== ''
            && $entFedClave !== '0'
        ) {
            /*
             * NOI puede guardar claves de un solo dígito con
             * espacio a la izquierda.
             *
             * Ejemplo:
             * "1" -> " 1"
             */
            $claveFormateada = str_pad(
                $entFedClave,
                2,
                ' ',
                STR_PAD_LEFT
            );

            $qb = $this->dbConnection->getQueryBuilder();

            $result = $qb->select('nombre')
                ->from('empleados_ent_fed_noi')
                ->where(
                    $qb->expr()->eq(
                        'clave',
                        $qb->createNamedParameter($claveFormateada)
                    )
                )
                ->executeQuery();

            $rowEnt = $result->fetch();

            $result->closeCursor();

            if ($rowEnt) {
                $entFedNombre = (string)$rowEnt['nombre'];
            }
        }

        /*
         * Construir dirección completa.
         */
        $partesDireccion = array_filter(
            [
                trim((string)($emp['CALLE'] ?? '')),
                trim((string)($emp['COLONIA'] ?? '')),
                trim((string)($emp['CD_POBLAC'] ?? '')),
                $entFedNombre,
                !empty($emp['COD_POST'])
                    ? 'C.P. ' . trim((string)$emp['COD_POST'])
                    : '',
            ],
            static fn($value): bool => $value !== ''
        );

        $direccionCompleta = implode(
            ', ',
            $partesDireccion
        );

        /*
         * Sueldo.
         */
        $sueldo = isset($emp['SUELDOXHORA'])
            && is_numeric($emp['SUELDOXHORA'])
            ? round((float)$emp['SUELDOXHORA'], 2)
            : 0;

        return [
            'Numero_empleado' => trim(
                (string)($emp['CLAVE'] ?? '')
            ),

            'Ingreso' => !empty($emp['FECH_ALTA'])
                ? date(
                    'Y-m-d',
                    strtotime((string)$emp['FECH_ALTA'])
                )
                : null,

            'Fondo_clave' => trim(
                (string)($emp['CLAVE'] ?? '')
            ),

            'Numero_cuenta' => trim(
                (string)($emp['CTRL_NOM'] ?? '')
            ),

            'Sueldo' => $sueldo,

            'Fecha_nacimiento' => !empty($emp['FECH_NACIM'])
                ? date(
                    'Y-m-d',
                    strtotime((string)$emp['FECH_NACIM'])
                )
                : null,

            'Direccion' => $direccionCompleta,

            'Estado_civil' => $estadoCivil,

            'Telefono_contacto' => trim(
                (string)($emp['TELEFONO'] ?? '')
            ),

            'Curp' => trim(
                (string)($emp['CURP'] ?? '')
            ),

            'Rfc' => trim(
                (string)($emp['R_F_C_'] ?? '')
            ),

            'Imss' => trim(
                (string)($emp['IMSS'] ?? '')
            ),

            'Genero' => $genero,

            'Email' => trim(
                (string)($emp['EMAIL'] ?? '')
            ),

            'Email2' => trim(
                (string)($emp['EMAIL2'] ?? '')
            ),
        ];
    }

    /**
     * Calcula y valida el nombre de la tabla dinámica
     * de trabajadores.
     *
     * Ejemplo:
     * TB15082601
     */
    public function getTablaTrabajadoresBase(PDO $fbPdo): string
    {
        $config = $this->getConfig();

        $numEmpresa = trim(
            (string)($config['No_Empresa_NOI'] ?? '')
        );

        $prefTablaNominas = trim(
            (string)($config['Pref_Tabla_Nominas'] ?? '')
        );

        $prefTablaTrabajadores = trim(
            (string)($config['Pref_Tabla_Trabajadores'] ?? '')
        );

        if (
            $numEmpresa === ''
            || $prefTablaNominas === ''
            || $prefTablaTrabajadores === ''
        ) {
            throw new Exception(
                'La configuración de las tablas de NOI está incompleta.'
            );
        }

        /*
         * 1. Tabla maestra de nóminas.
         *
         * Ejemplo:
         * NWNOMINAS01
         */
        $tablaNominas = $prefTablaNominas . $numEmpresa;

        /*
         * Validar que la tabla exista.
         */
        if (!$this->tablaExisteFirebird($fbPdo, $tablaNominas)) {
            throw new Exception(
                "La tabla maestra de nóminas {$tablaNominas} no existe en Firebird."
            );
        }

        /*
         * 2. Obtener fecha de la última nómina.
         */
        $stmt = $fbPdo->query(
            "
            SELECT MAX(FECH_NOMI) AS ULTIMA_FECHA
            FROM {$tablaNominas}
            "
        );

        $row = $stmt->fetch();

        if (
            !$row
            || empty($row['ULTIMA_FECHA'])
        ) {
            throw new Exception(
                "No se encontraron fechas de nómina en {$tablaNominas}."
            );
        }

        /*
         * 3. Formatear la fecha a ddMMyy.
         *
         * Ejemplo:
         * 2026-08-15 -> 150826
         */
        $fechaObj = new \DateTime(
            (string)$row['ULTIMA_FECHA']
        );

        $fechaFormateada = $fechaObj->format('dmy');

        /*
         * 4. Construir tabla de trabajadores.
         *
         * Ejemplo:
         * TB + 150826 + 01
         */
        $tablaTrabajadores =
            $prefTablaTrabajadores
            . $fechaFormateada
            . $numEmpresa;

        /*
         * 5. Validar que la tabla exista.
         */
        if (
            !$this->tablaExisteFirebird(
                $fbPdo,
                $tablaTrabajadores
            )
        ) {
            throw new Exception(
                "La tabla de trabajadores de esta nómina ({$tablaTrabajadores}) no se encontró."
            );
        }

        return $tablaTrabajadores;
    }

    /**
     * Comprueba si una tabla existe en Firebird
     * utilizando RDB$RELATIONS.
     */
    private function tablaExisteFirebird(
        PDO $pdo,
        string $nombreTabla
    ): bool {
        $stmt = $pdo->prepare(
            '
            SELECT 1
            FROM RDB$RELATIONS
            WHERE TRIM(RDB$RELATION_NAME) = UPPER(?)
            '
        );

        $stmt->execute([$nombreTabla]);

        return (bool)$stmt->fetch();
    }
}