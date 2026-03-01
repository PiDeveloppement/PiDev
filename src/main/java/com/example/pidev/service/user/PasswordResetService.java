package com.example.pidev.service.user;

import com.example.pidev.model.user.PasswordResetToken;
import com.example.pidev.model.user.UserModel;
import com.example.pidev.service.user.UserService;
import com.example.pidev.utils.DBConnection;

import java.sql.*;
import java.time.LocalDateTime;
import java.util.UUID;

public class PasswordResetService {

    private Connection connection;
    private UserService userService;

    public PasswordResetService() {
        this.connection = DBConnection.getConnection();
        this.userService = new UserService();
        createTableIfNotExists();
    }

    /**
     * Créer la table des tokens si elle n'existe pas
     */
    private void createTableIfNotExists() {
        String sql = "CREATE TABLE IF NOT EXISTS password_reset_tokens (" +
                "id INT PRIMARY KEY AUTO_INCREMENT," +
                "user_id INT NOT NULL," +
                "token VARCHAR(255) NOT NULL UNIQUE," +
                "expiry_date TIMESTAMP NOT NULL," +
                "used BOOLEAN DEFAULT FALSE," +
                "FOREIGN KEY (user_id) REFERENCES user_model(id_User)" +
                ")";
        try (Statement stmt = connection.createStatement()) {
            stmt.execute(sql);
            System.out.println("✅ Table password_reset_tokens vérifiée/créée");
        } catch (SQLException e) {
            System.err.println("❌ Erreur création table: " + e.getMessage());
        }
    }

    /**
     * Récupérer un utilisateur par son email (utilise UserService existant)
     */
    public UserModel getUserByEmail(String email) {
        try {
            return userService.getUserByEmail(email);
        } catch (Exception e) {
            System.err.println("❌ Erreur recherche email: " + e.getMessage());
            return null;
        }
    }

    /**
     * Créer un token de réinitialisation pour un utilisateur
     */
    public PasswordResetToken createResetToken(int userId) {
        // Désactiver les anciens tokens
        invalidateOldTokens(userId);

        // Générer un token unique
        String token = UUID.randomUUID().toString();
        LocalDateTime expiryDate = LocalDateTime.now().plusHours(1);

        String sql = "INSERT INTO password_reset_tokens (user_id, token, expiry_date, used) VALUES (?, ?, ?, ?)";

        try (PreparedStatement ps = connection.prepareStatement(sql, Statement.RETURN_GENERATED_KEYS)) {
            ps.setInt(1, userId);
            ps.setString(2, token);
            ps.setTimestamp(3, Timestamp.valueOf(expiryDate));
            ps.setBoolean(4, false);

            int affectedRows = ps.executeUpdate();

            if (affectedRows > 0) {
                ResultSet rs = ps.getGeneratedKeys();
                if (rs.next()) {
                    PasswordResetToken resetToken = new PasswordResetToken(userId);
                    resetToken.setId(rs.getInt(1));
                    resetToken.setToken(token);
                    resetToken.setExpiryDate(expiryDate);

                    System.out.println("✅ Token créé pour l'utilisateur ID: " + userId);
                    return resetToken;
                }
            }
        } catch (SQLException e) {
            System.err.println("❌ Erreur création token: " + e.getMessage());
        }
        return null;
    }

    /**
     * Désactiver les anciens tokens d'un utilisateur
     */
    private void invalidateOldTokens(int userId) {
        String sql = "UPDATE password_reset_tokens SET used = TRUE WHERE user_id = ? AND used = FALSE";
        try (PreparedStatement ps = connection.prepareStatement(sql)) {
            ps.setInt(1, userId);
            int updated = ps.executeUpdate();
            if (updated > 0) {
                System.out.println("🔄 " + updated + " ancien(s) token(s) désactivé(s)");
            }
        } catch (SQLException e) {
            System.err.println("❌ Erreur désactivation tokens: " + e.getMessage());
        }
    }

    /**
     * Valider un token
     */
    public PasswordResetToken validateToken(String token) {
        String sql = "SELECT * FROM password_reset_tokens WHERE token = ? AND used = FALSE AND expiry_date > NOW()";

        try (PreparedStatement ps = connection.prepareStatement(sql)) {
            ps.setString(1, token);
            ResultSet rs = ps.executeQuery();

            if (rs.next()) {
                PasswordResetToken resetToken = new PasswordResetToken(rs.getInt("user_id"));
                resetToken.setId(rs.getInt("id"));
                resetToken.setToken(rs.getString("token"));
                resetToken.setExpiryDate(rs.getTimestamp("expiry_date").toLocalDateTime());
                resetToken.setUsed(rs.getBoolean("used"));

                System.out.println("✅ Token valide pour l'utilisateur ID: " + resetToken.getUserId());
                return resetToken;
            } else {
                System.out.println("❌ Token invalide ou expiré");
            }
        } catch (SQLException e) {
            System.err.println("❌ Erreur validation token: " + e.getMessage());
        }
        return null;
    }

    /**
     * Marquer un token comme utilisé
     */
    public boolean markTokenAsUsed(String token) {
        String sql = "UPDATE password_reset_tokens SET used = TRUE WHERE token = ?";
        try (PreparedStatement ps = connection.prepareStatement(sql)) {
            ps.setString(1, token);
            int updated = ps.executeUpdate();
            if (updated > 0) {
                System.out.println("✅ Token marqué comme utilisé");
                return true;
            }
        } catch (SQLException e) {
            System.err.println("❌ Erreur mise à jour token: " + e.getMessage());
        }
        return false;
    }

    /**
     * Mettre à jour le mot de passe d'un utilisateur (utilise UserService existant)
     */
    public boolean updatePassword(int userId, String newPassword) {
        try {
            UserModel user = userService.getUserById(userId);
            if (user != null) {
                user.setPassword(newPassword);
                // Si vous avez une méthode update dans UserService
                // return userService.updateUser(user);

                // Sinon, requête directe
                String sql = "UPDATE user_model SET password = ? WHERE id_User = ?";
                try (PreparedStatement ps = connection.prepareStatement(sql)) {
                    ps.setString(1, newPassword);
                    ps.setInt(2, userId);
                    int updated = ps.executeUpdate();

                    if (updated > 0) {
                        System.out.println("✅ Mot de passe mis à jour pour l'utilisateur ID: " + userId);
                        return true;
                    }
                }
            }
        } catch (Exception e) {
            System.err.println("❌ Erreur mise à jour mot de passe: " + e.getMessage());
        }
        return false;
    }

    /**
     * Nettoyer les tokens expirés (à appeler périodiquement)
     */
    public void cleanExpiredTokens() {
        String sql = "DELETE FROM password_reset_tokens WHERE expiry_date < NOW()";
        try (Statement stmt = connection.createStatement()) {
            int deleted = stmt.executeUpdate(sql);
            if (deleted > 0) {
                System.out.println("🧹 " + deleted + " token(s) expiré(s) supprimé(s)");
            }
        } catch (SQLException e) {
            System.err.println("❌ Erreur nettoyage tokens: " + e.getMessage());
        }
    }
}
