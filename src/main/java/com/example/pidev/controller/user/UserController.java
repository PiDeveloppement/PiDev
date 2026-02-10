package com.example.pidev.controller.user;

import com.example.pidev.model.role.Role;
import com.example.pidev.model.user.UserModel;
import com.example.pidev.service.role.RoleService;
import com.example.pidev.service.user.UserService;

import javafx.beans.binding.Bindings;
import javafx.beans.property.SimpleStringProperty;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.event.ActionEvent;
import javafx.fxml.*;
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

public class UserController implements Initializable {

    /* ================= FIELDS ================= */

    @FXML private TextField firstnameField, lastnameField, emailField, faculteField, passwordField;
    @FXML private ComboBox<String> roleComboBox;

    @FXML private TableView<UserModel> userTable;
    @FXML private TableColumn<UserModel,Integer> id_column;
    @FXML private TableColumn<UserModel,String> firstname_column, lastname_column,
            email_column, faculte_column, role_id_column, password_column;
    @FXML private TableColumn<UserModel,Void> actions_column;

    private UserService userService;
    private RoleService roleService;
    private ObservableList<UserModel> usersList;


    /* ================= INITIALIZE ================= */

    @Override
    public void initialize(URL url, ResourceBundle rb) {
        try {
            userService = new UserService();
            roleService = new RoleService();
            usersList = FXCollections.observableArrayList();

            roleComboBox.setItems(roleService.getAllRoleNames());

            initializeTableColumns();
            loadUsers();
            setupActionsColumn();

            userTable.getSelectionModel().selectedItemProperty()
                    .addListener((obs,o,n) -> { if(n!=null) fillForm(n); });

            userTable.setFixedCellSize(40);
            userTable.prefHeightProperty().bind(
                    Bindings.size(userTable.getItems())
                            .multiply(userTable.getFixedCellSize())
                            .add(40)
            );

        } catch (SQLException e) {
            showAlert("Erreur", e.getMessage());
        }
    }


    /* ================= TABLE ================= */

    private void initializeTableColumns() {

        id_column.setCellValueFactory(new PropertyValueFactory<>("id_User"));
        firstname_column.setCellValueFactory(new PropertyValueFactory<>("first_Name"));
        lastname_column.setCellValueFactory(new PropertyValueFactory<>("last_Name"));
        email_column.setCellValueFactory(new PropertyValueFactory<>("email"));
        faculte_column.setCellValueFactory(new PropertyValueFactory<>("faculte"));
        password_column.setCellValueFactory(new PropertyValueFactory<>("password"));

        role_id_column.setCellValueFactory(cell -> {
            Role r = cell.getValue().getRole();
            return new SimpleStringProperty(r != null ? r.getRoleName() : "");

        });

    }


    private void loadUsers() {
        usersList.setAll(userService.getAllUsers());
        userTable.setItems(usersList);

    }


    /* ================= CREATE ================= */

    @FXML
    private void registerButtonOnAction(ActionEvent e) {
        createUser();
    }

    private void createUser() {

        if (!validateFields()) return;

        UserModel user = new UserModel(
                firstnameField.getText(),
                lastnameField.getText(),
                emailField.getText(),
                faculteField.getText(),
                passwordField.getText(),
                1
        );

        if (userService.registerUser(user)) {
            showAlert("Succès", "Utilisateur créé avec succès");
            loadUsers();
            resetForm();
        }
    }


    /* ================= UPDATE ================= */

    @FXML
    private void modifyButtonOnAction(ActionEvent e) throws SQLException {

        UserModel selected = userTable.getSelectionModel().getSelectedItem();

        System.out.println(selected); // debug

        if (selected == null) {
            showAlert("Erreur", "Sélectionnez un utilisateur");
            return;
        }

        updateUser(selected);
    }


    private void updateUser(UserModel userModel) throws SQLException {

        if (!validateFields()) return;

        userModel.setFirst_Name(firstnameField.getText());
        userModel.setLast_Name(lastnameField.getText());
        userModel.setEmail(emailField.getText());
        userModel.setFaculte(faculteField.getText());
        userModel.setPassword(passwordField.getText());

        String roleName = roleComboBox.getValue();
        userModel.setRole_Id(roleService.getRoleIdByName(roleName));

        if (userService.updateUser(userModel)) {
            showAlert("Succès", "Utilisateur modifié");
            loadUsers();
            resetForm();
        }
    }




    /* ================= DELETE ================= */

    private void deleteUser(UserModel user) {

        boolean confirmed = showConfirmation(
                "Confirmer",
                "Supprimer " + user.getFirst_Name() + " ?"
        );

        if (!confirmed) return;

        if (userService.deleteUser(user.getId_User())) {
            showAlert("Succès", "Utilisateur supprimé");
            loadUsers();
        }
    }


    /* ================= ACTION COLUMN ================= */

    private void setupActionsColumn() {

        actions_column.setCellFactory(param -> new TableCell<UserModel, Void>() {


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
                    UserModel user = getTableView().getItems().get(getIndex());
                    deleteUser(user);
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

    private void fillForm(UserModel u) {
        firstnameField.setText(u.getFirst_Name());
        lastnameField.setText(u.getLast_Name());
        emailField.setText(u.getEmail());
        faculteField.setText(u.getFaculte());
        passwordField.setText(u.getPassword());
    }

    private void resetForm() {
        firstnameField.clear();
        lastnameField.clear();
        emailField.clear();
        faculteField.clear();
        passwordField.clear();
        roleComboBox.getSelectionModel().clearSelection();
    }

    private boolean validateFields() {
        if (firstnameField.getText().isEmpty()
                || lastnameField.getText().isEmpty()
                || emailField.getText().isEmpty()) {
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
    @FXML
    private void goToRole(ActionEvent event) {
        try {
            Parent root = FXMLLoader.load(
                    getClass().getResource("/com/example/pidev/fxml/role/role.fxml")
            );
            Stage stage = (Stage) ((Node) event.getSource()).getScene().getWindow();
            stage.setScene(new Scene(root));
            stage.show();
        } catch (Exception ignored) {}
    }
    @FXML
    private void goToGestionUser(ActionEvent event) {
        try {
            Parent root = FXMLLoader.load(
                    getClass().getResource("/com/example/pidev/fxml/user/user.fxml")
            );
            Stage stage = (Stage) ((Node) event.getSource()).getScene().getWindow();
            stage.setScene(new Scene(root));
            stage.show();
        } catch (Exception ignored) {}
    }
    @FXML
    private void goToDashboard(ActionEvent event) {
        try {
            Parent root = FXMLLoader.load(
                    getClass().getResource("/com/example/pidev/fxml/dashboard/dashboard.fxml")
            );
            Stage stage = (Stage) ((Node) event.getSource()).getScene().getWindow();
            stage.setScene(new Scene(root));
            stage.show();
        } catch (Exception ignored) {}
    }
    @FXML
    private void goToProfil(ActionEvent event) {
        try {
            Parent root = FXMLLoader.load(
                    getClass().getResource("/com/example/pidev/fxml/")
            );
            Stage stage = (Stage) ((Node) event.getSource()).getScene().getWindow();
            stage.setScene(new Scene(root));
            stage.show();
        } catch (Exception ignored) {}
    }
}
