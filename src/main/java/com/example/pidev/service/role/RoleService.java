package com.example.pidev.service.role;

import com.example.pidev.utils.DBConnection;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;

import java.sql.*;

public class RoleService {

    public int getRoleIdByName(String roleName) throws SQLException {
        String query = "SELECT id_role FROM role WHERE rolename = ?";

        Connection connection1  =DBConnection.getConnection();
        PreparedStatement ps = connection1.prepareStatement(query);
        ps.setString(1, roleName);

        ResultSet rs = ps.executeQuery();

        if (rs.next()) {
            return rs.getInt("id_role");
        }
        return -1;
    }
    public ObservableList<String> getAllRoleNames() throws SQLException {

        ObservableList<String> roles = FXCollections.observableArrayList();

        String query = "SELECT rolename FROM role";

        Connection conn = DBConnection.getConnection();
        PreparedStatement ps = conn.prepareStatement(query);

        ResultSet rs = ps.executeQuery();

        while (rs.next()) {
            roles.add(rs.getString("rolename"));
        }

        return roles;
    }


}
