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

    public ObservableList<String> getAllEventTitles() {
        ObservableList<String> titles = FXCollections.observableArrayList();
        String sql = "SELECT title FROM event ORDER BY title";

        try (PreparedStatement ps = cnx().prepareStatement(sql);
             ResultSet rs = ps.executeQuery()) {

            while (rs.next()) titles.add(rs.getString(1));
            return titles;

        } catch (SQLException e) {
            throw new RuntimeException("getAllEventTitles failed", e);
        }
    }

    public int getEventIdByTitle(String title) {
        String sql = "SELECT id FROM event WHERE title = ?";
        try (PreparedStatement ps = cnx().prepareStatement(sql)) {
            ps.setString(1, title);
            try (ResultSet rs = ps.executeQuery()) {
                if (rs.next()) {
                    return rs.getInt("id");
                }
                throw new RuntimeException("Event not found: " + title);
            }
        } catch (SQLException e) {
            throw new RuntimeException("getEventIdByTitle failed", e);
        }
    }

    public String getEventTitleById(int eventId) {
        String sql = "SELECT title FROM event WHERE id = ?";
        try (PreparedStatement ps = cnx().prepareStatement(sql)) {
            ps.setInt(1, eventId);
            try (ResultSet rs = ps.executeQuery()) {
                if (rs.next()) {
                    return rs.getString("title");
                }
                throw new RuntimeException("Event not found: " + eventId);
            }
        } catch (SQLException e) {
            throw new RuntimeException("getEventTitleById failed", e);
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
