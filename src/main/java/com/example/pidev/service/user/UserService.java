package com.example.pidev.service.user;

import com.example.pidev.model.role.Role;
import com.example.pidev.model.user.UserModel;
import com.example.pidev.utils.DBConnection;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;

import java.sql.*;
import java.time.LocalDateTime;

public class UserService {

    private final Connection connection;

    public UserService() throws SQLException {
        this.connection = new DBConnection().getConnection();
    }

    // 🔹 Créer un utilisateur (version originale préservée)
    public boolean registerUser(UserModel user) {
        String query = "INSERT INTO user_model(First_Name, Last_Name, Email, Faculte, Password, Role_Id, phone, profile_picture_url, registration_date, bio) " +
                "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        try (PreparedStatement stmt = connection.prepareStatement(query)) {
            stmt.setString(1, user.getFirst_Name());
            stmt.setString(2, user.getLast_Name());
            stmt.setString(3, user.getEmail());
            stmt.setString(4, user.getFaculte());
            stmt.setString(5, user.getPassword());
            stmt.setInt(6, 1); // Role_Id par défaut

            // Nouveaux champs (gestion des valeurs null)
            if (user.getPhone() != null) {
                stmt.setString(7, user.getPhone());
            } else {
                stmt.setNull(7, Types.VARCHAR);
            }

            if (user.getProfilePictureUrl() != null) {
                stmt.setString(8, user.getProfilePictureUrl());
            } else {
                stmt.setNull(8, Types.VARCHAR);
            }

            // Date d'inscription (automatique si non fournie)
            if (user.getRegistrationDate() != null) {
                stmt.setTimestamp(9, Timestamp.valueOf(user.getRegistrationDate()));
            } else {
                stmt.setTimestamp(9, Timestamp.valueOf(LocalDateTime.now()));
            }

            if (user.getBio() != null) {
                stmt.setString(10, user.getBio());
            } else {
                stmt.setNull(10, Types.VARCHAR);
            }

            int rowsInserted = stmt.executeUpdate();
            return rowsInserted > 0;

        } catch (SQLException e) {
            e.printStackTrace();
            return false;
        }
    }

    // 🔹 Supprimer un utilisateur (inchangé)
    public boolean deleteUser(long id) {
        String query = "DELETE FROM user_model WHERE Id_User=?";
        try (PreparedStatement stmt = connection.prepareStatement(query)) {
            stmt.setLong(1, id);
            return stmt.executeUpdate() > 0;
        } catch (SQLException e) {
            e.printStackTrace();
            return false;
        }
    }

    // 🔹 Récupérer tous les utilisateurs (avec les nouveaux champs)
    public ObservableList<UserModel> getAllUsers() {
        ObservableList<UserModel> users = FXCollections.observableArrayList();
        String query = "SELECT u.*, r.RoleName " +
                "FROM user_model u " +
                "LEFT JOIN role r ON u.Role_Id = r.Id_Role";

        try (Statement stmt = connection.createStatement();
             ResultSet rs = stmt.executeQuery(query)) {

            while (rs.next()) {
                // Créer et remplir l'objet Role
                Role role = new Role();
                role.setId_Role(rs.getInt("Role_Id"));
                role.setRoleName(rs.getString("RoleName"));

                // Créer l'utilisateur avec les champs de base
                UserModel user = new UserModel(
                        rs.getInt("Id_User"),
                        rs.getString("First_Name"),
                        rs.getString("Last_Name"),
                        rs.getString("Email"),
                        rs.getString("Faculte"),
                        rs.getString("Password"),
                        rs.getInt("Role_Id")
                );

                // Ajouter les nouveaux champs
                user.setPhone(rs.getString("phone"));
                user.setProfilePictureUrl(rs.getString("profile_picture_url"));

                // Récupérer la date d'inscription (peut être null)
                Timestamp regDate = rs.getTimestamp("registration_date");
                if (regDate != null) {
                    user.setRegistrationDate(regDate.toLocalDateTime());
                }

                user.setBio(rs.getString("bio"));
                user.setRole(role); // Associer le rôle à l'utilisateur

                users.add(user);
            }

        } catch (SQLException e) {
            e.printStackTrace();
        }
        return users;
    }

    // 🔹 Modifier un utilisateur (avec les nouveaux champs)
    public boolean updateUser(UserModel user) {
        String query = "UPDATE user_model SET " +
                "First_Name=?, Last_Name=?, Email=?, Faculte=?, Password=?, Role_Id=?, " +
                "phone=?, profile_picture_url=?, bio=? " +
                "WHERE Id_User=?";

        try (PreparedStatement stmt = connection.prepareStatement(query)) {

            stmt.setString(1, user.getFirst_Name());
            stmt.setString(2, user.getLast_Name());
            stmt.setString(3, user.getEmail());
            stmt.setString(4, user.getFaculte());
            stmt.setString(5, user.getPassword());
            stmt.setInt(6, user.getRole_Id());

            // Nouveaux champs (gestion des valeurs null)
            if (user.getPhone() != null) {
                stmt.setString(7, user.getPhone());
            } else {
                stmt.setNull(7, Types.VARCHAR);
            }

            if (user.getProfilePictureUrl() != null) {
                stmt.setString(8, user.getProfilePictureUrl());
            } else {
                stmt.setNull(8, Types.VARCHAR);
            }

            if (user.getBio() != null) {
                stmt.setString(9, user.getBio());
            } else {
                stmt.setNull(9, Types.VARCHAR);
            }

            stmt.setLong(10, user.getId_User()); // VERY IMPORTANT

            return stmt.executeUpdate() > 0;

        } catch (SQLException e) {
            e.printStackTrace();
            return false;
        }
    }

    // 🔹 Méthode spécifique pour mettre à jour la photo de profil
    public boolean updateProfilePicture(int userId, String pictureUrl) {
        String query = "UPDATE user_model SET profile_picture_url=? WHERE Id_User=?";
        try (PreparedStatement stmt = connection.prepareStatement(query)) {
            stmt.setString(1, pictureUrl);
            stmt.setInt(2, userId);
            return stmt.executeUpdate() > 0;
        } catch (SQLException e) {
            e.printStackTrace();
            return false;
        }
    }

    // 🔹 Méthode pour récupérer un utilisateur par son ID (avec tous les champs)
    public UserModel getUserById(int id) {
        String query = "SELECT u.*, r.RoleName FROM user_model u " +
                "LEFT JOIN role r ON u.Role_Id = r.Id_Role WHERE u.Id_User=?";

        try (PreparedStatement stmt = connection.prepareStatement(query)) {
            stmt.setInt(1, id);
            ResultSet rs = stmt.executeQuery();

            if (rs.next()) {
                Role role = new Role();
                role.setId_Role(rs.getInt("Role_Id"));
                role.setRoleName(rs.getString("RoleName"));

                UserModel user = new UserModel(
                        rs.getInt("Id_User"),
                        rs.getString("First_Name"),
                        rs.getString("Last_Name"),
                        rs.getString("Email"),
                        rs.getString("Faculte"),
                        rs.getString("Password"),
                        rs.getInt("Role_Id")
                );

                user.setPhone(rs.getString("phone"));
                user.setProfilePictureUrl(rs.getString("profile_picture_url"));

                Timestamp regDate = rs.getTimestamp("registration_date");
                if (regDate != null) {
                    user.setRegistrationDate(regDate.toLocalDateTime());
                }

                user.setBio(rs.getString("bio"));
                user.setRole(role);

                return user;
            }
        } catch (SQLException e) {
            e.printStackTrace();
        }
        return null;
    }

    // 🔹 Rafraîchir la liste des utilisateurs (inchangé)
    public void refreshUsers(ObservableList<UserModel> usersList) {
        usersList.setAll(getAllUsers());
    }

    /**
     * Récupère toutes les facultés uniques (inchangé)
     */
    public ObservableList<String> getAllFacultes() {
        ObservableList<String> faculteList = FXCollections.observableArrayList();
        String query = "SELECT DISTINCT faculte FROM user_model WHERE faculte IS NOT NULL AND faculte != '' ORDER BY faculte";

        try (Statement st = connection.createStatement();
             ResultSet rs = st.executeQuery(query)) {

            while (rs.next()) {
                faculteList.add(rs.getString("faculte"));
            }
        } catch (SQLException e) {
            e.printStackTrace();
        }
        return faculteList;
    }

    /**
     * Vérifie si un email existe déjà (inchangé)
     */
    public boolean isEmailExists(String email) {
        String query = "SELECT COUNT(*) FROM user_model WHERE Email = ?";
        try (PreparedStatement ps = connection.prepareStatement(query)) {
            ps.setString(1, email);
            ResultSet rs = ps.executeQuery();
            if (rs.next()) {
                return rs.getInt(1) > 0;
            }
        } catch (SQLException e) {
            e.printStackTrace();
        }
        return false;
    }
    public UserModel authenticate(String email, String password) {
        String query = "SELECT u.*, r.RoleName, r.Id_Role as RoleId " +
                "FROM user_model u " +
                "LEFT JOIN role r ON u.Role_Id = r.Id_Role " +
                "WHERE u.Email = ? AND u.Password = ?";

        try (PreparedStatement stmt = connection.prepareStatement(query)) {
            stmt.setString(1, email);
            stmt.setString(2, password); // Note: Idéalement, le mot de passe devrait être hashé

            ResultSet rs = stmt.executeQuery();

            if (rs.next()) {
                // Créer le rôle
                Role role = new Role();
                role.setId_Role(rs.getInt("Role_Id"));
                role.setRoleName(rs.getString("RoleName"));

                // Créer l'utilisateur
                UserModel user = new UserModel(
                        rs.getInt("Id_User"),
                        rs.getString("First_Name"),
                        rs.getString("Last_Name"),
                        rs.getString("Email"),
                        rs.getString("Faculte"),
                        rs.getString("Password"),
                        rs.getInt("Role_Id")
                );

                // Ajouter les nouveaux champs
                user.setPhone(rs.getString("phone"));
                user.setProfilePictureUrl(rs.getString("profile_picture_url"));

                Timestamp regDate = rs.getTimestamp("registration_date");
                if (regDate != null) {
                    user.setRegistrationDate(regDate.toLocalDateTime());
                }

                user.setBio(rs.getString("bio"));
                user.setRole(role);

                return user;
            }
        } catch (SQLException e) {
            e.printStackTrace();
        }
        return null;
    }
}