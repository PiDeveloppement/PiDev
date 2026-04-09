<?php
// src/Service/DatabaseService.php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;

class DatabaseService
{
    private ?Connection $connection = null;
    private LoggerInterface $logger;
    private array $connectionParams;

    public function __construct(LoggerInterface $logger, string $databaseUrl)
    {
        $this->logger = $logger;
        
        // Parser l'URL de la base de données
        $this->connectionParams = $this->parseDatabaseUrl($databaseUrl);
        
        $this->logger->info('✅ Service de base de données initialisé');
    }

    /**
     * Parse une URL de base de données au format Doctrine
     */
    private function parseDatabaseUrl(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false) {
            throw new \InvalidArgumentException('DATABASE_URL invalide: format non reconnu.');
        }

        if (($parts['scheme'] ?? null) !== 'mysql') {
            throw new \InvalidArgumentException('DATABASE_URL invalide: seul le schéma mysql est supporté.');
        }

        $path = $parts['path'] ?? '';
        $dbName = ltrim($path, '/');

        if ($dbName === '') {
            throw new \InvalidArgumentException('DATABASE_URL invalide: nom de base manquant.');
        }

        return [
            'driver' => 'pdo_mysql',
            'charset' => 'utf8mb4',
            'host' => $parts['host'] ?? '127.0.0.1',
            'port' => (int) ($parts['port'] ?? 3306),
            'user' => $parts['user'] ?? '',
            'password' => $parts['pass'] ?? '',
            'dbname' => $dbName,
        ];
    }

    /**
     * Obtient la connexion à la base de données
     */
    public function getConnection(): Connection
    {
        if ($this->connection === null || !$this->connection->isConnected()) {
            try {
                $this->connection = DriverManager::getConnection($this->connectionParams);
                $this->logger->info('✅ Connexion à la base de données établie');
            } catch (Exception $e) {
                $this->logger->error('❌ Erreur de connexion à la base de données: ' . $e->getMessage());
                throw new \RuntimeException('Erreur de connexion à la base de données', 0, $e);
            }
        }
        return $this->connection;
    }

    /**
     * Alias pour getConnection()
     */
    public function getCnx(): Connection
    {
        return $this->getConnection();
    }

    /**
     * Ferme la connexion à la base de données
     */
    public function closeConnection(): void
    {
        if ($this->connection && $this->connection->isConnected()) {
            try {
                $this->connection->close();
                $this->logger->info('✅ Connexion fermée');
            } catch (Exception $e) {
                $this->logger->error('❌ Erreur lors de la fermeture de la connexion: ' . $e->getMessage());
            }
        }
    }

    /**
     * Vérifie si la connexion est valide
     */
    public function isConnected(): bool
    {
        return $this->connection !== null && $this->connection->isConnected();
    }

    /**
     * Teste la connexion
     */
    public function testConnection(): bool
    {
        try {
            $conn = $this->getConnection();
            $conn->executeQuery('SELECT 1');
            return true;
        } catch (\Exception $e) {
            $this->logger->error('❌ Test de connexion échoué: ' . $e->getMessage());
            return false;
        }
    }
}