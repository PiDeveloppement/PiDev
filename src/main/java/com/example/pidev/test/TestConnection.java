package com.example.pidev.test;

import com.example.pidev.utils.MyDatabase;
import java.sql.Connection;
import java.sql.Statement;

/**
 * Test de connexion à la base de données
 */
public class TestConnection {

    public static void main(String[] args) {

        // Essayer de se connecter
        Connection conn = MyDatabase.getInstance().getConnection();

        if (conn != null) {
            System.out.println("✅ CONNEXION RÉUSSIE!\n");

            try {
                Statement stmt = conn.createStatement();

                // Test simple: compter les catégories
                var rs = stmt.executeQuery("SELECT COUNT(*) as count FROM event_category");

                if (rs.next()) {
                    int count = rs.getInt("count");
                    System.out.println("📊 Nombre de catégories: " + count);
                }

                rs.close();
                stmt.close();

            } catch (Exception e) {
                System.err.println("❌ Erreur lors du test: " + e.getMessage());
                e.printStackTrace();
            }

        } else {
            System.err.println("❌ ÉCHEC DE LA CONNEXION!");
        }
    }
}