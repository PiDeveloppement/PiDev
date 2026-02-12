package com.example.pidev.service.user;

import com.example.pidev.model.role.Role;
import com.example.pidev.model.user.UserModel;
import com.example.pidev.utils.DBConnection;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;

import java.sql.*;

public class UserService {

    private final Connection connection;

    public UserService() throws SQLException {
        this.connection = new DBConnection().getConnection();
    }

    // 🔹 Créer un utilisateur
    public boolean registerUser(UserModel user) {
        String query = "INSERT INTO user_model(First_Name, Last_Name, Email, Faculte, Password, Role_Id) " +
                "VALUES (?, ?, ?, ?, ?, ?)";

        try (PreparedStatement stmt = connection.prepareStatement(query)) {
            stmt.setString(1, user.getFirst_Name());
            stmt.setString(2, user.getLast_Name());
            stmt.setString(3, user.getEmail());
            stmt.setString(4, user.getFaculte());
            stmt.setString(5, user.getPassword());
            stmt.setInt(6, 1);

            int rowsInserted = stmt.executeUpdate();
            return rowsInserted > 0;

        } catch (SQLException e) {
            e.printStackTrace();
            return false;
        }
    }

    // 🔹 Supprimer un utilisateur
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

    // 🔹 Récupérer tous les utilisateurs (pour TableView)
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
                UserModel user = new UserModel(
                        rs.getInt("Id_User"),
                        rs.getString("First_Name"),
                        rs.getString("Last_Name"),
                        rs.getString("Email"),
                        rs.getString("Faculte"),
                        rs.getString("Password"),
                        rs.getInt("Role_Id")
                );



                user.setRole(role); // Associer le rôle à l'utilisateur

                users.add(user);
            }

        } catch (SQLException e) {
            e.printStackTrace();
        }
        return users;
    }

    // 🔹 Modifier un utilisateur
    public boolean updateUser(UserModel user) {

        String query = "UPDATE user_model SET " +
                "First_Name=?, Last_Name=?, Email=?, Faculte=?, Password=?, Role_Id=? " +
                "WHERE Id_User=?";

        try (PreparedStatement stmt = connection.prepareStatement(query)) {

            stmt.setString(1, user.getFirst_Name());
            stmt.setString(2, user.getLast_Name());
            stmt.setString(3, user.getEmail());
            stmt.setString(4, user.getFaculte());
            stmt.setString(5, user.getPassword());
            stmt.setInt(6, user.getRole_Id()); // not "DEFAULT"

            stmt.setLong(7, user.getId_User()); // VERY IMPORTANT

            return stmt.executeUpdate() > 0;

        } catch (SQLException e) {
            e.printStackTrace();
            return false;
        }
    }
    public void refreshUsers(ObservableList<UserModel> usersList) {
        usersList.setAll(getAllUsers());
    }

    /**
     * Récupère toutes les facultés uniques
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

}



