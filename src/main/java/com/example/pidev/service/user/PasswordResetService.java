package com.example.pidev.service.user;

import com.example.pidev.model.user.PasswordResetToken;
import com.example.pidev.utils.DBConnection;

import java.sql.*;
import java.time.LocalDateTime;

public class PasswordResetService {

    private Connection cnx;

    public PasswordResetService() {
        cnx = DBConnection.getInstance().getCnx();
    }

    public void createToken(PasswordResetToken token) {
        String query = "INSERT INTO password_reset_tokens (user_id, token, expiry_date, used) VALUES (?, ?, ?, ?)";
        try (PreparedStatement stmt = cnx.prepareStatement(query)) {
            stmt.setInt(1, token.getUserId());
            stmt.setString(2, token.getToken());
            stmt.setTimestamp(3, Timestamp.valueOf(token.getExpiryDate()));
            stmt.setBoolean(4, token.isUsed());
            stmt.executeUpdate();
        } catch (SQLException e) {
            e.printStackTrace();
        }
    }

    public PasswordResetToken findByToken(String token) {
        String query = "SELECT * FROM password_reset_tokens WHERE token = ?";
        try (PreparedStatement stmt = cnx.prepareStatement(query)) {
            stmt.setString(1, token);
            ResultSet rs = stmt.executeQuery();

            if (rs.next()) {
                PasswordResetToken resetToken = new PasswordResetToken(rs.getInt("user_id"));
                resetToken.setId(rs.getInt("id"));
                resetToken.setToken(rs.getString("token"));
                resetToken.setExpiryDate(rs.getTimestamp("expiry_date").toLocalDateTime());
                resetToken.setUsed(rs.getBoolean("used"));
                return resetToken;
            }
        } catch (SQLException e) {
            e.printStackTrace();
        }
        return null;
    }

    public void markTokenAsUsed(String token) {
        String query = "UPDATE password_reset_tokens SET used = true WHERE token = ?";
        try (PreparedStatement stmt = cnx.prepareStatement(query)) {
            stmt.setString(1, token);
            stmt.executeUpdate();
        } catch (SQLException e) {
            e.printStackTrace();
        }
    }

    public void deleteExpiredTokens() {
        String query = "DELETE FROM password_reset_tokens WHERE expiry_date < ?";
        try (PreparedStatement stmt = cnx.prepareStatement(query)) {
            stmt.setTimestamp(1, Timestamp.valueOf(LocalDateTime.now()));
            stmt.executeUpdate();
        } catch (SQLException e) {
            e.printStackTrace();
        }
    }
}