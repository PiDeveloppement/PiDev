package com.example.pidev.utils;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.SQLException;

/**
 * Classe Singleton pour gérer la connexion à la base de données MySQL
 *
 * @author Ons Abdesslem
 * @version 1.0
 */
public class MyDatabase {

    // ==================== CONFIGURATION BDD ====================

    private static final String URL = "jdbc:mysql://localhost:3306/pidev";
    private static final String USERNAME = "root";
    private static final String PASSWORD = "admin";

    // ==================== SINGLETON ====================

    private static MyDatabase instance;
    private Connection connection;

    /**
     * Constructeur privé pour empêcher l'instanciation directe
     */
    private MyDatabase() {
        try {
            // Charger le driver MySQL
            Class.forName("com.mysql.cj.jdbc.Driver");

            // Établir la connexion
            this.connection = DriverManager.getConnection(URL, USERNAME, PASSWORD);

            System.out.println("✅ Connexion à la base de données réussie!");

        } catch (ClassNotFoundException e) {
            System.err.println("❌ Driver MySQL non trouvé!");
            e.printStackTrace();
        } catch (SQLException e) {
            System.err.println("❌ Erreur de connexion à la base de données!");
            System.err.println("Vérifiez: URL=" + URL + ", User=" + USERNAME);
            e.printStackTrace();
        }
    }

    /**
     * Obtenir l'instance unique de MyDatabase
     * @return L'instance singleton
     */
    public static MyDatabase getInstance() {
        if (instance == null) {
            synchronized (MyDatabase.class) {
                if (instance == null) {
                    instance = new MyDatabase();
                }
            }
        }
        return instance;
    }

    /**
     * Obtenir la connexion à la base de données
     * @return L'objet Connection
     */
    public Connection getConnection() {
        try {
            // Vérifier si la connexion est toujours valide
            if (connection == null || connection.isClosed()) {
                System.out.println("⚠️ Reconnexion à la base de données...");
                connection = DriverManager.getConnection(URL, USERNAME, PASSWORD);
            }
        } catch (SQLException e) {
            System.err.println("❌ Erreur lors de la vérification de la connexion!");
            e.printStackTrace();
        }
        return connection;
    }

    /**
     * Fermer la connexion (à appeler à la fin de l'application)
     */
    public void closeConnection() {
        if (connection != null) {
            try {
                connection.close();
                System.out.println("✅ Connexion fermée avec succès");
            } catch (SQLException e) {
                System.err.println("❌ Erreur lors de la fermeture de la connexion");
                e.printStackTrace();
            }
        }
    }

    /**
     * Tester la connexion
     * @return true si connecté, false sinon
     */
    public boolean testConnection() {
        try {
            return connection != null && !connection.isClosed();
        } catch (SQLException e) {
            return false;
        }
    }
}