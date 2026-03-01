package com.example.pidev.service.user;

import com.example.pidev.model.role.Role;
import com.example.pidev.model.user.UserModel;
import com.example.pidev.utils.DBConnection;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;

import java.sql.*;
import java.time.LocalDateTime;

public class UserService {

    public Connection connection; // Changé de final à non-final pour pouvoir gérer les exceptions

    public UserService() {
        try {
            this.connection = new DBConnection().getConnection();
        } catch (Exception e) {
            System.err.println("❌ Erreur de connexion à la base de données: " + e.getMessage());
            e.printStackTrace();
            this.connection = null;
        }
    }

    // 🔹 Créer un utilisateur avec gestion d'exceptions
    public boolean registerUser(UserModel user) {
        if (connection == null) {
            System.err.println("❌ Pas de connexion à la base de données");
            return false;
        }

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
            System.err.println("❌ Erreur lors de l'inscription: " + e.getMessage());
            e.printStackTrace();
            return false;
        }
    }

    // 🔹 Supprimer un utilisateur
    public boolean deleteUser(long id) {
        if (connection == null) return false;

        String query = "DELETE FROM user_model WHERE Id_User=?";
        try (PreparedStatement stmt = connection.prepareStatement(query)) {
            stmt.setLong(1, id);
            return stmt.executeUpdate() > 0;

        } catch (SQLException e) {
            System.err.println("❌ Erreur lors de la suppression: " + e.getMessage());
            e.printStackTrace();
            return false;
        }
    }

    // 🔹 Récupérer tous les utilisateurs
    public ObservableList<UserModel> getAllUsers() {
        ObservableList<UserModel> users = FXCollections.observableArrayList();

        if (connection == null) {
            System.err.println("❌ Pas de connexion à la base de données");
            return users;
        }

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
            System.err.println("❌ Erreur lors de la récupération des utilisateurs: " + e.getMessage());
            e.printStackTrace();
        }
        return users;
    }

    // 🔹 Modifier un utilisateur
    public boolean updateUser(UserModel user) {
        if (connection == null) return false;

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

            stmt.setLong(10, user.getId_User());

            return stmt.executeUpdate() > 0;

        } catch (SQLException e) {
            System.err.println("❌ Erreur lors de la mise à jour: " + e.getMessage());
            e.printStackTrace();
            return false;
        }
    }

    // 🔹 Récupérer un utilisateur par email
    public UserModel getUserByEmail(String email) {
        if (connection == null) return null;

        String query = "SELECT u.*, r.RoleName FROM user_model u " +
                "LEFT JOIN role r ON u.Role_Id = r.Id_Role WHERE u.Email = ?";

        try (PreparedStatement stmt = connection.prepareStatement(query)) {
            stmt.setString(1, email);
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
            System.err.println("❌ Erreur getUserByEmail: " + e.getMessage());
            e.printStackTrace();
        }
        return null;
    }

    // 🔹 Mettre à jour le mot de passe
    public boolean updateUserPassword(int userId, String newPassword) {
        if (connection == null) return false;

        String query = "UPDATE user_model SET password = ? WHERE Id_User = ?";

        try (PreparedStatement stmt = connection.prepareStatement(query)) {
            stmt.setString(1, newPassword);
            stmt.setInt(2, userId);

            int rowsAffected = stmt.executeUpdate();
            System.out.println("✅ Mot de passe mis à jour pour l'utilisateur ID: " + userId);
            return rowsAffected > 0;

        } catch (SQLException e) {
            System.err.println("❌ Erreur updateUserPassword: " + e.getMessage());
            e.printStackTrace();
            return false;
        }
    }

    // 🔹 Récupérer un utilisateur par ID
    public UserModel getUserById(int id) {
        if (connection == null) return null;

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
            System.err.println("❌ Erreur getUserById: " + e.getMessage());
            e.printStackTrace();
        }
        return null;
    }

    // 🔹 Authentification
    public UserModel authenticate(String email, String password) {
        if (connection == null) return null;

        String query = "SELECT u.*, r.RoleName FROM user_model u " +
                "LEFT JOIN role r ON u.Role_Id = r.Id_Role " +
                "WHERE u.Email = ? AND u.Password = ?";

        try (PreparedStatement stmt = connection.prepareStatement(query)) {
            stmt.setString(1, email);
            stmt.setString(2, password);

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
            System.err.println("❌ Erreur authenticate: " + e.getMessage());
            e.printStackTrace();
        }
        return null;
    }

    // 🔹 Récupérer toutes les facultés
    public ObservableList<String> getAllFacultes() {
        ObservableList<String> faculteList = FXCollections.observableArrayList();

        if (connection == null) return faculteList;

        String query = "SELECT DISTINCT faculte FROM user_model WHERE faculte IS NOT NULL AND faculte != '' ORDER BY faculte";

        try (Statement st = connection.createStatement();
             ResultSet rs = st.executeQuery(query)) {

            while (rs.next()) {
                faculteList.add(rs.getString("faculte"));
            }
        } catch (SQLException e) {
            System.err.println("❌ Erreur getAllFacultes: " + e.getMessage());
            e.printStackTrace();
        }
        return faculteList;
    }

    // 🔹 Vérifier si email existe
    public boolean isEmailExists(String email) {
        if (connection == null) return false;

        String query = "SELECT COUNT(*) FROM user_model WHERE Email = ?";
        try (PreparedStatement ps = connection.prepareStatement(query)) {
            ps.setString(1, email);
            ResultSet rs = ps.executeQuery();
            if (rs.next()) {
                return rs.getInt(1) > 0;
            }
        } catch (SQLException e) {
            System.err.println("❌ Erreur isEmailExists: " + e.getMessage());
            e.printStackTrace();
        }
        return false;
    }

    // 🔹 Compter les participants
    public int getTotalParticipantsCount() {
        if (connection == null) return 0;

        String query = "SELECT COUNT(*) FROM user_model";
        try (PreparedStatement stmt = connection.prepareStatement(query)) {
            ResultSet rs = stmt.executeQuery();
            if (rs.next()) {
                return rs.getInt(1);
            }
        } catch (SQLException e) {
            System.err.println("❌ Erreur getTotalParticipantsCount: " + e.getMessage());
            e.printStackTrace();
        }
        return 0;
    }
}