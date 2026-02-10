package com.example.pidev.controller.role;

import com.example.pidev.model.role.Role;
import com.example.pidev.service.role.RoleService;
import com.example.pidev.service.user.UserService;
import javafx.beans.binding.Bindings;
import javafx.beans.property.SimpleStringProperty;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.event.ActionEvent;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.fxml.Initializable;
import javafx.scene.Node;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.HBox;
import javafx.stage.Stage;

import java.net.URL;
import java.sql.SQLException;
import java.util.ResourceBundle;

public class RoleController implements Initializable {


    /* ================= FIELDS ================= */

    
@FXML private TextField rolenameField;
    @FXML private TableView<Role> roleTable;
    @FXML private TableColumn<Role,Integer> id_column;
    @FXML private TableColumn<Role,String> roleName_column;
    @FXML private TableColumn<Role,Void> actions_column;
    @FXML private   Button resetButton ;
    @FXML private   Button modifyButton ;

    private UserService userService;
    private RoleService roleService;
    private ObservableList<Role> rolesList;


    /* ================= INITIALIZE ================= */

    @Override
    public void initialize(URL url, ResourceBundle rb) {
        try {
            userService = new UserService();
            roleService = new RoleService();
            rolesList = FXCollections.observableArrayList();

           

            initializeTableColumns();
            loadRoles();
            setupActionsColumn();

            roleTable.getSelectionModel().selectedItemProperty()
                    .addListener((obs,o,n) -> { if(n!=null) fillForm(n); });

            roleTable.setFixedCellSize(40);
            roleTable.prefHeightProperty().bind(
                    Bindings.size(roleTable.getItems())
                            .multiply(roleTable.getFixedCellSize())
                            .add(40)
            );

        } catch (SQLException e) {
            showAlert("Erreur", e.getMessage());
        }
    }


    /* ================= TABLE ================= */

    private void initializeTableColumns() {

        id_column.setCellValueFactory(new PropertyValueFactory<>("id_Role"));
        roleName_column.setCellValueFactory(new PropertyValueFactory<>("roleName"));


    }


    private void loadRoles() {
        rolesList.setAll(roleService.getAllRoles());
        roleTable.setItems(rolesList);

    }


    /* ================= CREATE ================= */

    @FXML
    private void addRoleButtonOnAction(ActionEvent e) {
        createRole();
    }

    private void createRole() {

        if (!validateFields()) return;

        Role role = new Role(
                rolenameField.getText()
        );

        if (roleService.addRole(role)) {
            showAlert("Succès", "Role créé avec succès");
            loadRoles();
            resetForm();
        }
    }


    /* ================= UPDATE ================= */

    @FXML
    private void modifyButtonOnAction(ActionEvent e) throws SQLException {

        Role selected = roleTable.getSelectionModel().getSelectedItem();

        System.out.println(selected); // debug

        if (selected == null) {
            showAlert("Erreur", "Sélectionnez un role");
            return;
        }

        updateRole(selected);
    }


    private void updateRole(Role role) throws SQLException {

        if (!validateFields()) return;

        role.setRoleName(rolenameField.getText());


        if (roleService.updateRole(role)) {
            showAlert("Succès", "Utilisateur modifié");
            loadRoles();
            resetForm();
        }
    }




    /* ================= DELETE ================= */

    private void deleteRole(Role role) {

        boolean confirmed = showConfirmation(
                "Confirmer",
                "Supprimer " + role.getRoleName() + " ?"
        );

        if (!confirmed) return;

        if (roleService.deleteRole(role.getId_Role())) {
            showAlert("Succès", "Role supprimé");
            loadRoles();
        }
    }




    /* ================= ACTION COLUMN ================= */

    private void setupActionsColumn() {

        actions_column.setCellFactory(param -> new TableCell<Role, Void>() {


            private final Button deleteBtn = new Button("🗑");
            private final HBox container = new HBox(10,  deleteBtn);

            {



                // Bouton Supprimer
                deleteBtn.setStyle(
                        "-fx-background-color: #ef4444;" +
                                "-fx-text-fill: white;" +
                                "-fx-background-radius: 8;" +
                                "-fx-cursor: hand;"
                );

                deleteBtn.setOnAction(e -> {
                    Role role = getTableView().getItems().get(getIndex());
                    deleteRole(role);
                });
            }

            @Override
            protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                setGraphic(empty ? null : container);
            }
        });
    }



    /* ================= FORM ================= */

    private void fillForm(Role u) {
        rolenameField.setText(u.getRoleName());

    }
    @FXML
    private void resetButton(ActionEvent e){
    resetForm();
}
    private void resetForm() {
        rolenameField.clear();

    }

    private boolean validateFields() {
        if (rolenameField.getText().isEmpty()) {
            showAlert("Champs manquants", "Veuillez remplir tous les champs");
            return false;
        }
        return true;
    }


    /* ================= UTILS ================= */

    private void showAlert(String title, String msg) {
        new Alert(Alert.AlertType.INFORMATION, msg).showAndWait();
    }

    private boolean showConfirmation(String title, String msg) {
        return new Alert(Alert.AlertType.CONFIRMATION, msg)
                .showAndWait().orElse(ButtonType.CANCEL) == ButtonType.OK;
    }


    /* ================= NAVIGATION ================= */

    @FXML
    private void goToLogin(ActionEvent event) {
        try {
            Parent root = FXMLLoader.load(
                    getClass().getResource("/com/example/pidev/fxml/auth/login.fxml")
            );
            Stage stage = (Stage) ((Node) event.getSource()).getScene().getWindow();
            stage.setScene(new Scene(root));
            stage.show();
        } catch (Exception ignored) {}
    }

    public void goToProfil(ActionEvent event) {
        try {
            Parent root = FXMLLoader.load(
                    getClass().getResource("/com/example/pidev/fxml/user/profil.fxml")
            );
            Stage stage = (Stage) ((Node) event.getSource()).getScene().getWindow();
            stage.setScene(new Scene(root));
            stage.show();
        } catch (Exception ignored) {}
    }
    public void goToDashboard(ActionEvent event) {
        try {
            Parent root = FXMLLoader.load(
                    getClass().getResource("/com/example/pidev/fxml/dashboard/dashboard.fxml")
            );
            Stage stage = (Stage) ((Node) event.getSource()).getScene().getWindow();
            stage.setScene(new Scene(root));
            stage.show();
        } catch (Exception ignored) {}
    }
    public void goToGestionUser(ActionEvent event) {
        try {
            Parent root = FXMLLoader.load(
                    getClass().getResource("/com/example/pidev/fxml/user/user.fxml")
            );
            Stage stage = (Stage) ((Node) event.getSource()).getScene().getWindow();
            stage.setScene(new Scene(root));
            stage.show();
        } catch (Exception ignored) {}
    }
}
