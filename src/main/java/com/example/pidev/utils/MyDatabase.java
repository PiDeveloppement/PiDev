package com.example.pidev.utils;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.SQLException;

public class MyDatabase {
    private static MyDatabase instance;
    private Connection connection;

    private static final String URL  = "jdbc:mysql://localhost:3306/pidev?useSSL=false&serverTimezone=UTC";
    private static final String USER = "root";
    private static final String PASS = "manaimanai";

    private MyDatabase() {
        try {
            connection = DriverManager.getConnection(URL, USER, PASS);
        } catch (SQLException e) {
            throw new RuntimeException("DB connection failed: " + e.getMessage(), e);
        }
    }

    public static MyDatabase getInstance() {
        if (instance == null) instance = new MyDatabase();
        return instance;
    }

    public Connection getConnection() {
        return connection;
    }

    // ✅ pratique
    public static Connection getConnectionInstance() {
        return getInstance().getConnection();
    }
}
