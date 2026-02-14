<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

class SessionHandlerFactory
{
    public function __construct(
        private Connection $connection
    ) {}

    public function createHandler(): PdoSessionHandler
    {
        // Obtenir la connexion PDO native depuis Doctrine DBAL
        $pdo = $this->connection->getNativeConnection();
        
        // Si vous utilisez Doctrine DBAL 3.x, utilisez getWrappedConnection()
        // $pdo = $this->connection->getWrappedConnection();
        
        return new PdoSessionHandler(
            $pdo,
            [
                'db_table'        => 'sessions',
                'db_id_col'       => 'sess_id',
                'db_data_col'     => 'sess_data',
                'db_lifetime_col' => 'sess_lifetime',
                'db_time_col'     => 'sess_time',
            ]
        );
    }
}
