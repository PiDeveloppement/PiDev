package com.example.pidev.utils;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.SQLException;

public class DBConnection {
    // Coordonnées de la base de données
    private static final String URL = "jdbc:mysql://localhost:3306/pidev";
    private static final String USER = "root";
    private static final String PASSWORD = "souhail123";

    // Instance unique (Singleton)
    private static Connection connection;

    // Méthode STATIQUE pour récupérer la connexion
    public static Connection getConnection() {
        if (connection == null) {
            try {
                // Chargement du driver (optionnel sur les versions récentes de JDBC)
                Class.forName("com.mysql.cj.jdbc.Driver");
                connection = DriverManager.getConnection(URL, USER, PASSWORD);
                System.out.println("✅ Connexion à la base de données réussie !");
            } catch (ClassNotFoundException | SQLException e) {
                System.err.println("❌ Erreur de connexion : " + e.getMessage());
            }
        }
        return connection;
    }
}