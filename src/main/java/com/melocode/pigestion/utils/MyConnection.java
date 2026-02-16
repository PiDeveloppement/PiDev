package com.melocode.pigestion.utils;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.SQLException;

public class MyConnection {
    private final String URL = "jdbc:mysql://localhost:3306/pi";
    private final String USER = "root";
    private final String PASS = "010203";
    private Connection cnx;
    private static MyConnection instance;

    private MyConnection() {
        try {
            cnx = DriverManager.getConnection(URL, USER, PASS);
        } catch (SQLException e) {
            System.err.println("Erreur Connexion: " + e.getMessage());
        }
    }

    public static MyConnection getInstance() {
        if (instance == null) instance = new MyConnection();
        return instance;
    }

    public Connection getCnx() { return cnx; }
}