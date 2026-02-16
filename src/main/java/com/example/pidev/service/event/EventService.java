package com.example.pidev.service.event;

import com.example.pidev.utils.MyDatabase;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;

public class EventService {

    private Connection cnx() {
        // adapte selon ton projet : certains utilisent getInstance().getConnection()
        // d'autres getConnectionInstance()
        try {
            return MyDatabase.getInstance().getConnection();
        } catch (Exception e) {
            return MyDatabase.getConnectionInstance();
        }
    }

    public ObservableList<Integer> getAllEventIds() {
        ObservableList<Integer> ids = FXCollections.observableArrayList();
        String sql = "SELECT id FROM event ORDER BY id";

        try (PreparedStatement ps = cnx().prepareStatement(sql);
             ResultSet rs = ps.executeQuery()) {

            while (rs.next()) ids.add(rs.getInt(1));
            return ids;

        } catch (SQLException e) {
            throw new RuntimeException("getAllEventIds failed", e);
        }
    }

    public boolean eventExists(int eventId) {
        String sql = "SELECT 1 FROM event WHERE id=? LIMIT 1";
        try (PreparedStatement ps = cnx().prepareStatement(sql)) {
            ps.setInt(1, eventId);
            try (ResultSet rs = ps.executeQuery()) {
                return rs.next();
            }
        } catch (SQLException e) {
            throw new RuntimeException("eventExists failed", e);
        }
    }
}
